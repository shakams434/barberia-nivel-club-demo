<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->text('phone_ciphertext')->nullable()->after('phone_e164');
            $table->string('phone_hash', 64)->nullable()->after('phone_ciphertext');
            $table->string('phone_last4', 4)->nullable()->after('phone_hash');
        });

        DB::table('customers')->orderBy('id')->chunkById(100, function ($customers): void {
            foreach ($customers as $customer) {
                $phone = (string) $customer->phone_e164;
                $hash = self::phoneHash($phone);

                DB::table('customers')->where('id', $customer->id)->update([
                    'phone_raw' => '•••• '.substr($phone, -4),
                    'phone_e164' => 'enc_'.substr($hash, 0, 20),
                    'phone_ciphertext' => Crypt::encryptString($phone),
                    'phone_hash' => $hash,
                    'phone_last4' => substr($phone, -4),
                ]);
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['business_id', 'phone_hash'], 'customers_business_phone_hash_unique');
            $table->index(['business_id', 'phone_last4'], 'customers_business_phone_last4_index');
        });

        Schema::table('loyalty_programs', function (Blueprint $table): void {
            $table->string('campaign_window_start', 5)->default('09:00')->after('marketing_frequency_days');
            $table->string('campaign_window_end', 5)->default('20:00')->after('campaign_window_start');
        });

        Schema::table('rewards', function (Blueprint $table): void {
            $table->foreignId('minimum_tier_id')->nullable()->after('required_level')->constrained('tiers')->nullOnDelete();
        });

        Schema::table('reward_redemptions', function (Blueprint $table): void {
            $table->string('status')->default('completed')->after('idempotency_key');
            $table->foreignId('reversed_by')->nullable()->after('redeemed_by')->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable()->after('note');
            $table->timestamp('reversed_at')->nullable()->after('redeemed_at');
            $table->index(['business_id', 'status', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        DB::table('customers')->whereNotNull('phone_ciphertext')->orderBy('id')->chunkById(100, function ($customers): void {
            foreach ($customers as $customer) {
                DB::table('customers')->where('id', $customer->id)->update([
                    'phone_e164' => Crypt::decryptString($customer->phone_ciphertext),
                ]);
            }
        });

        Schema::table('reward_redemptions', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'status', 'redeemed_at']);
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['status', 'reversal_reason', 'reversed_at']);
        });

        Schema::table('rewards', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('minimum_tier_id');
        });

        Schema::table('loyalty_programs', function (Blueprint $table): void {
            $table->dropColumn(['campaign_window_start', 'campaign_window_end']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_business_phone_hash_unique');
            $table->dropIndex('customers_business_phone_last4_index');
            $table->dropColumn(['phone_ciphertext', 'phone_hash', 'phone_last4']);
        });
    }

    private static function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', $phone, (string) config('app.key'));
    }
};
