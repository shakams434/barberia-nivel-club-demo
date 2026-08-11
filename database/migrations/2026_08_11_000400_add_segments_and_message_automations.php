<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('gender', 30)->nullable()->after('name');
            $table->index(['business_id', 'gender']);
        });

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('technical_name');
        });

        Schema::create('message_automations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'event_key']);
            $table->index(['business_id', 'active']);
        });

        $names = [
            'loyalty_welcome' => 'Bienvenida al programa',
            'loyalty_xp_update' => 'Resumen después de una atención',
            'loyalty_level_up' => 'Aviso de subida de nivel',
            'loyalty_reward_redeemed' => 'Confirmación de canje',
            'loyalty_opt_out' => 'Confirmación de baja promocional',
            'campaign_level_discount' => 'Promoción por nivel',
        ];

        foreach ($names as $technicalName => $displayName) {
            DB::table('whatsapp_templates')->where('technical_name', $technicalName)->update(['display_name' => $displayName]);
        }

        $events = [
            'customer_registered' => 'loyalty_welcome',
            'visit_registered' => 'loyalty_xp_update',
            'level_increased' => 'loyalty_level_up',
            'reward_redeemed' => 'loyalty_reward_redeemed',
        ];

        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            foreach ($events as $eventKey => $technicalName) {
                $templateId = DB::table('whatsapp_templates')
                    ->where('business_id', $businessId)
                    ->where('technical_name', $technicalName)
                    ->value('id');

                if ($templateId) {
                    DB::table('message_automations')->insert([
                        'business_id' => $businessId,
                        'whatsapp_template_id' => $templateId,
                        'event_key' => $eventKey,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_automations');

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'gender']);
            $table->dropColumn('gender');
        });
    }
};
