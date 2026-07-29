<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('event_key');
            $table->string('event_type');
            $table->string('payload_hash', 64);
            $table->string('status')->default('processed');
            $table->timestamp('processed_at');
            $table->timestamps();
            $table->unique(['business_id', 'event_key']);
            $table->index(['business_id', 'event_type', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
