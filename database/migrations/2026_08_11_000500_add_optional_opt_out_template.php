<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            $exists = DB::table('whatsapp_templates')
                ->where('business_id', $businessId)
                ->where('technical_name', 'loyalty_opt_out')
                ->where('language', 'es_PE')
                ->exists();

            if ($exists) {
                continue;
            }

            $isFake = DB::table('whatsapp_accounts')
                ->where('business_id', $businessId)
                ->where('provider', 'fake')
                ->exists();

            DB::table('whatsapp_templates')->insert([
                'business_id' => $businessId,
                'public_id' => (string) Str::uuid(),
                'technical_name' => 'loyalty_opt_out',
                'display_name' => 'Confirmación de baja promocional',
                'category' => 'utility',
                'language' => 'es_PE',
                'header_type' => 'none',
                'body' => 'Hola {{1}}. Dejaste de recibir promociones de {{2}}. Tu nivel y recompensas se mantienen.',
                'variables' => json_encode([1, 2]),
                'samples' => json_encode(['Cliente', 'Mi barbería']),
                'status' => $isFake ? 'approved' : 'draft',
                'last_synced_at' => $isFake ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $usedTemplateIds = DB::table('message_automations')->pluck('whatsapp_template_id');

        DB::table('whatsapp_templates')
            ->where('technical_name', 'loyalty_opt_out')
            ->whereNotIn('id', $usedTemplateIds)
            ->delete();
    }
};
