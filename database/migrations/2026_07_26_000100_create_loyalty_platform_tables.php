<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->unsignedInteger('xp')->default(100);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'active', 'sort_order']);
        });

        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp_per_level')->default(100);
            $table->unsignedSmallInteger('recent_visit_window_minutes')->default(10);
            $table->unsignedSmallInteger('campaign_batch_size')->default(20);
            $table->unsignedSmallInteger('marketing_frequency_limit')->default(2);
            $table->unsignedSmallInteger('marketing_frequency_days')->default(30);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->unsignedInteger('min_level');
            $table->unsignedInteger('max_level')->nullable();
            $table->string('color', 16);
            $table->string('icon')->default('shield');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'active', 'min_level']);
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('required_level');
            $table->unsignedInteger('valid_days')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->boolean('one_time')->default(true);
            $table->boolean('important')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'active', 'required_level']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tier_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('phone_raw');
            $table->string('phone_e164', 24);
            $table->string('source')->default('admin');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('xp_total')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'phone_e164']);
            $table->index(['business_id', 'status', 'last_visit_at']);
            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'level']);
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('status');
            $table->string('source');
            $table->string('text_version')->nullable();
            $table->text('consent_text')->nullable();
            $table->text('evidence')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['business_id', 'customer_id', 'type', 'recorded_at']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('idempotency_key');
            $table->unsignedInteger('xp_awarded');
            $table->string('status')->default('registered');
            $table->text('duplicate_reason')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('visited_at');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['business_id', 'customer_id', 'visited_at']);
            $table->index(['business_id', 'status', 'visited_at']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('type');
            $table->integer('xp_delta')->default(0);
            $table->unsignedBigInteger('balance_after');
            $table->string('idempotency_key');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['business_id', 'customer_id', 'created_at']);
        });

        Schema::create('customer_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('status')->default('available');
            $table->timestamp('unlocked_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_redeemed_at')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'customer_id', 'reward_id']);
            $table->index(['business_id', 'status', 'expires_at']);
        });

        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_reward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('redeemed_by')->constrained('users')->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('idempotency_key');
            $table->text('note')->nullable();
            $table->timestamp('redeemed_at');
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key']);
        });

        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('fake');
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id')->nullable()->index();
            $table->string('phone_e164')->nullable();
            $table->text('access_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->text('webhook_verify_token')->nullable();
            $table->boolean('send_enabled')->default(false);
            $table->timestamp('configuration_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('technical_name');
            $table->string('category');
            $table->string('language')->default('es_PE');
            $table->string('header_type')->default('none');
            $table->text('header')->nullable();
            $table->text('body');
            $table->text('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->json('variables')->nullable();
            $table->json('samples')->nullable();
            $table->string('meta_id')->nullable();
            $table->string('status')->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'technical_name', 'language']);
            $table->index(['business_id', 'category', 'status']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->string('audience_type')->default('filter');
            $table->json('filters')->nullable();
            $table->json('variables')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('estimated_recipients')->default(0);
            $table->timestamps();
            $table->index(['business_id', 'status', 'scheduled_at']);
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('exclusion_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'customer_id']);
            $table->index(['business_id', 'campaign_id', 'status']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('direction')->default('outbound');
            $table->string('message_type')->default('template');
            $table->string('phone_e164', 24);
            $table->string('status')->default('queued');
            $table->text('body_preview')->nullable();
            $table->json('variables')->nullable();
            $table->string('meta_message_id')->nullable();
            $table->string('idempotency_key');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key']);
            $table->unique(['business_id', 'meta_message_id']);
            $table->index(['business_id', 'status', 'created_at']);
        });

        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('meta_message_id');
            $table->string('from_phone_e164', 24);
            $table->string('command')->nullable();
            $table->text('message_text')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'meta_message_id']);
            $table->index(['business_id', 'status', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'action', 'created_at']);
            $table->index(['business_id', 'auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('inbound_messages');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_accounts');
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('customer_rewards');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('tiers');
        Schema::dropIfExists('loyalty_programs');
        Schema::dropIfExists('services');
    }
};
