<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->string('verified_name')->nullable()->after('phone_e164');
            $table->string('quality_rating')->nullable()->after('verified_name');
            $table->string('connection_status')->default('pending')->after('send_enabled');
            $table->text('last_error')->nullable()->after('connection_status');
            $table->timestamp('webhook_subscribed_at')->nullable()->after('configuration_checked_at');
            $table->timestamp('last_webhook_at')->nullable()->after('webhook_subscribed_at');
            $table->unique('phone_number_id');
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('phone_e164', 24);
            $table->string('contact_name')->nullable();
            $table->string('status')->default('open');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'phone_e164']);
            $table->index(['business_id', 'status', 'last_message_at']);
        });

        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->foreignId('whatsapp_conversation_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
        Schema::table('inbound_messages', function (Blueprint $table): void {
            $table->foreignId('whatsapp_conversation_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->timestamp('read_at')->nullable()->after('processed_at');
            $table->timestamp('replied_at')->nullable()->after('read_at');
        });

        $events = collect(DB::table('whatsapp_messages')->get()->map(fn ($row) => [
            'table' => 'whatsapp_messages', 'id' => $row->id, 'business_id' => $row->business_id,
            'customer_id' => $row->customer_id, 'phone' => $row->phone_e164,
            'direction' => 'outbound', 'created_at' => $row->created_at,
        ]))->merge(DB::table('inbound_messages')->get()->map(fn ($row) => [
            'table' => 'inbound_messages', 'id' => $row->id, 'business_id' => $row->business_id,
            'customer_id' => $row->customer_id, 'phone' => $row->from_phone_e164,
            'direction' => 'inbound', 'created_at' => $row->created_at,
        ]))->sortBy('created_at');

        $conversationIds = [];
        foreach ($events as $event) {
            $key = $event['business_id'].'|'.$event['phone'];
            if (! isset($conversationIds[$key])) {
                $conversationIds[$key] = DB::table('whatsapp_conversations')->insertGetId([
                    'business_id' => $event['business_id'],
                    'customer_id' => $event['customer_id'],
                    'public_id' => (string) Str::uuid(),
                    'phone_e164' => $event['phone'],
                    'status' => 'open',
                    'unread_count' => 0,
                    'last_message_at' => $event['created_at'],
                    $event['direction'] === 'inbound' ? 'last_inbound_at' : 'last_outbound_at' => $event['created_at'],
                    'created_at' => $event['created_at'],
                    'updated_at' => now(),
                ]);
            } else {
                $conversationUpdate = [
                    'last_message_at' => $event['created_at'],
                    $event['direction'] === 'inbound' ? 'last_inbound_at' : 'last_outbound_at' => $event['created_at'],
                    'updated_at' => now(),
                ];
                if ($event['customer_id']) {
                    $conversationUpdate['customer_id'] = $event['customer_id'];
                }
                DB::table('whatsapp_conversations')->where('id', $conversationIds[$key])->update($conversationUpdate);
            }
            DB::table($event['table'])->where('id', $event['id'])->update([
                'whatsapp_conversation_id' => $conversationIds[$key],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('whatsapp_conversation_id');
            $table->dropColumn(['read_at', 'replied_at']);
        });
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('whatsapp_conversation_id');
        });
        Schema::dropIfExists('whatsapp_conversations');
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->dropUnique(['phone_number_id']);
            $table->dropColumn(['verified_name', 'quality_rating', 'connection_status', 'last_error', 'webhook_subscribed_at', 'last_webhook_at']);
        });
    }
};
