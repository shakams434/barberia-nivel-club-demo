<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\CustomerReward;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;
use App\Models\MessageAutomation;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Service;
use App\Models\Tier;
use App\Models\User;
use App\Models\Visit;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\MessageAutomationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LocalSampleSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::create([
            'public_id' => (string) Str::uuid(),
            'name' => 'Barbería Central',
            'slug' => 'barberia-central',
            'timezone' => 'America/Lima',
            'country_code' => 'PE',
            'phone_country_code' => '51',
            'primary_color' => '#D4AF37',
            'secondary_color' => '#111318',
            'contact_phone' => '+51900000999',
            'contact_email' => 'hola@barberia.local',
            'active' => true,
        ]);

        $program = LoyaltyProgram::withoutGlobalScope('business')->create([
            'business_id' => $business->id,
            'xp_per_level' => 100,
            'recent_visit_window_minutes' => 10,
            'campaign_batch_size' => 20,
            'marketing_frequency_limit' => 2,
            'marketing_frequency_days' => 30,
            'active' => true,
        ]);

        $admin = User::create([
            'business_id' => $business->id,
            'name' => config('demo.admin.name'),
            'username' => config('demo.admin.username'),
            'email' => config('demo.admin.email'),
            'password' => Hash::make(config('demo.admin.password')),
            'role' => 'admin',
            'active' => true,
        ]);

        $tiers = collect([
            ['Bronce', 1, 4, '#CD7F32'],
            ['Plata', 5, 9, '#C0C0C0'],
            ['Oro', 10, 19, '#D4AF37'],
            ['Diamante', 20, 34, '#67E8F9'],
            ['Leyenda', 35, null, '#C084FC'],
        ])->mapWithKeys(function (array $row, int $index) use ($business) {
            $tier = Tier::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'public_id' => (string) Str::uuid(),
                'name' => $row[0],
                'min_level' => $row[1],
                'max_level' => $row[2],
                'color' => $row[3],
                'icon' => 'shield',
                'sort_order' => $index,
                'active' => true,
            ]);

            return [$row[0] => $tier];
        });

        $services = collect([
            ['Corte', 100, 40],
            ['Barba', 70, 25],
            ['Corte + Barba', 160, 60],
        ])->map(function (array $row, int $index) use ($business) {
            return Service::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'public_id' => (string) Str::uuid(),
                'name' => $row[0],
                'xp' => $row[1],
                'duration_minutes' => $row[2],
                'active' => true,
                'sort_order' => $index,
            ]);
        });

        $rewards = collect([
            ['Perfilado premium', 'Perfilado de contornos sin costo en tu próxima visita.', 3, true],
            ['Upgrade Plata', 'Tratamiento capilar incluido al alcanzar el rango Plata.', 5, true],
            ['Barba de cortesía', 'Servicio de barba de cortesía para clientes Oro.', 10, true],
            ['Experiencia Diamante', 'Atención prioritaria y ritual completo.', 20, true],
        ])->map(function (array $row) use ($business) {
            return Reward::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'public_id' => (string) Str::uuid(),
                'name' => $row[0],
                'description' => $row[1],
                'required_level' => $row[2],
                'one_time' => true,
                'important' => $row[3],
                'active' => true,
            ]);
        });

        $templates = $this->createTemplates($business);
        foreach (MessageAutomationService::DEFINITIONS as $eventKey => $definition) {
            MessageAutomation::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'whatsapp_template_id' => $templates[$definition['default_template']]->id,
                'event_key' => $eventKey,
                'active' => true,
            ]);
        }
        $account = WhatsAppAccount::withoutGlobalScope('business')->create([
            'business_id' => $business->id,
            'provider' => 'fake',
            'phone_number_id' => 'FAKE_PHONE_NUMBER_ID',
            'phone_e164' => '+51900000999',
            'send_enabled' => false,
        ]);

        $names = ['Marco Vidal', 'Camila Torres', 'Luis Mendoza', 'Diego Rojas', 'Andrés Paredes', 'Rafael León', 'Jorge Núñez', 'Paolo Vega', 'Renzo Silva', 'Mateo Flores', 'Lucía Campos', 'Sofía Arias'];
        $customers = collect();

        foreach ($names as $index => $name) {
            $xp = [300, 850, 1200, 210, 480, 1000, 0, 640, 1900, 90, 3400, 140][$index];
            $level = intdiv($xp, $program->xp_per_level) + 1;
            $tier = $tiers->first(fn (Tier $tier) => $level >= $tier->min_level && ($tier->max_level === null || $level <= $tier->max_level));
            $customer = Customer::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'tier_id' => $tier->id,
                'public_id' => (string) Str::uuid(),
                'name' => $name,
                'gender' => in_array($index, [1, 10, 11], true) ? 'female' : 'male',
                'phone_raw' => '900000'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'phone_e164' => '+51900000'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'source' => $index % 3 === 0 ? 'whatsapp_qr' : 'admin',
                'status' => $index === 6 ? 'pending' : 'active',
                'notes' => $index === 0 ? 'Prefiere citas por la tarde.' : null,
                'xp_total' => $xp,
                'level' => $level,
                'joined_at' => now()->subDays(100 - $index * 5),
                'last_visit_at' => $xp ? now()->subDays($index * 6 + 2) : null,
            ]);
            $customers->push($customer);

            Consent::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'admin_user_id' => $admin->id,
                'type' => Consent::LOYALTY,
                'status' => 'granted',
                'source' => 'local_seed',
                'text_version' => 'local-v1',
                'consent_text' => 'Consentimiento de práctica para el entorno local.',
                'recorded_at' => $customer->joined_at,
            ]);
            Consent::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'admin_user_id' => $admin->id,
                'type' => Consent::MARKETING,
                'status' => $index % 4 === 3 ? 'revoked' : 'granted',
                'source' => 'local_seed',
                'text_version' => 'local-v1',
                'consent_text' => 'Autorización de práctica para el entorno local.',
                'recorded_at' => $customer->joined_at->addMinute(),
            ]);

            if ($xp > 0) {
                $service = $services[$index % $services->count()];
                $visit = Visit::withoutGlobalScope('business')->create([
                    'business_id' => $business->id,
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'registered_by' => $admin->id,
                    'public_id' => (string) Str::uuid(),
                    'idempotency_key' => 'seed-visit-'.$customer->id,
                    'xp_awarded' => $xp,
                    'status' => 'registered',
                    'visited_at' => $customer->last_visit_at,
                ]);
                LoyaltyTransaction::withoutGlobalScope('business')->create([
                    'business_id' => $business->id,
                    'customer_id' => $customer->id,
                    'visit_id' => $visit->id,
                    'created_by' => $admin->id,
                    'public_id' => (string) Str::uuid(),
                    'type' => 'visit',
                    'xp_delta' => $xp,
                    'balance_after' => $xp,
                    'idempotency_key' => 'seed-ledger-'.$customer->id,
                ]);
            }

            foreach ($rewards->where('required_level', '<=', $level) as $reward) {
                $customerReward = CustomerReward::withoutGlobalScope('business')->create([
                    'business_id' => $business->id,
                    'customer_id' => $customer->id,
                    'reward_id' => $reward->id,
                    'public_id' => (string) Str::uuid(),
                    'status' => ($index === 1 && $reward->required_level === 3) ? 'redeemed' : 'available',
                    'unlocked_at' => now()->subDays(10),
                    'redemptions_count' => ($index === 1 && $reward->required_level === 3) ? 1 : 0,
                ]);
                if ($customerReward->status === 'redeemed') {
                    RewardRedemption::withoutGlobalScope('business')->create([
                        'business_id' => $business->id,
                        'customer_reward_id' => $customerReward->id,
                        'customer_id' => $customer->id,
                        'redeemed_by' => $admin->id,
                        'public_id' => (string) Str::uuid(),
                        'idempotency_key' => 'seed-redemption-'.$customerReward->id,
                        'note' => 'Canje de práctica local',
                        'redeemed_at' => now()->subDays(3),
                    ]);
                }
            }
        }

        foreach ($customers->take(5) as $index => $customer) {
            WhatsAppMessage::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'whatsapp_template_id' => $templates['loyalty_xp_update']->id,
                'public_id' => (string) Str::uuid(),
                'direction' => 'outbound',
                'message_type' => 'template',
                'phone_e164' => $customer->phone_e164,
                'status' => ['read', 'delivered', 'sent', 'failed', 'queued'][$index],
                'body_preview' => "Hola {$customer->name}. Ganaste XP en Barbería Central.",
                'variables' => [$customer->name, 'Barbería Central', 100, $customer->level, $customer->tier->name, 50],
                'meta_message_id' => 'fake_seed_'.$index,
                'idempotency_key' => 'seed-message-'.$index,
                'attempts' => 1,
                'queued_at' => now()->subDays(5 - $index),
                'sent_at' => $index < 4 ? now()->subDays(5 - $index) : null,
                'failed_at' => $index === 3 ? now()->subDays(2) : null,
                'error_code' => $index === 3 ? 'FAKE_TIMEOUT' : null,
                'error_message' => $index === 3 ? 'Tiempo de espera agotado en el entorno local.' : null,
            ]);
        }

        $campaign = Campaign::withoutGlobalScope('business')->create([
            'business_id' => $business->id,
            'whatsapp_template_id' => $templates['campaign_level_discount']->id,
            'created_by' => $admin->id,
            'confirmed_by' => $admin->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Beneficio por rango',
            'status' => 'completed',
            'filters' => ['min_level' => 3],
            'variables' => ['{customer_name}', '{level}', '{tier}', '15', 'Corte + Barba', now()->addDays(10)->format('d/m/Y')],
            'confirmed_at' => now()->subDays(8),
            'started_at' => now()->subDays(8),
            'completed_at' => now()->subDays(8),
            'estimated_recipients' => 3,
        ]);
        foreach ($customers->take(3) as $index => $customer) {
            CampaignRecipient::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'status' => ['read', 'delivered', 'sent'][$index],
                'processed_at' => now()->subDays(8),
            ]);
        }

        AuditLog::withoutGlobalScope('business')->create([
            'business_id' => $business->id,
            'user_id' => $admin->id,
            'public_id' => (string) Str::uuid(),
            'action' => 'local.seeded',
            'metadata' => ['customers' => $customers->count(), 'provider' => $account->provider],
        ]);
    }

    private function createTemplates(Business $business): array
    {
        $definitions = [
            'loyalty_welcome' => [
                'utility',
                "Hola {{1}}. Tu inscripción en {{2}} está confirmada.\n\nComienzas en Nivel {{3}}. Responde SALDO, NIVEL, PREMIOS o AYUDA.",
                ['Cliente', 'Barbería Central', '1'],
            ],
            'loyalty_xp_update' => [
                'utility',
                "Hola {{1}}. Registramos una nueva atención en {{2}}.\n\nGanaste {{3}} XP.\nTu estado actual es Nivel {{4}} · {{5}}.\nProgreso al siguiente nivel: {{6}}%.",
                ['Marco', 'Barbería Central', '100', '4', 'Bronce', '50'],
            ],
            'loyalty_level_up' => [
                'utility',
                "Subiste de nivel, {{1}}.\n\nAhora eres Nivel {{2}} · {{3}} en {{4}}.\nTu recompensa disponible es: {{5}}.",
                ['Marco', '5', 'Plata', 'Barbería Central', 'Upgrade Plata'],
            ],
            'loyalty_reward_redeemed' => [
                'utility',
                'Hola {{1}}. Confirmamos el canje de {{2}} en {{3}}. Tu XP histórico se mantiene.',
                ['Marco', 'Barba de cortesía', 'Barbería Central'],
            ],
            'campaign_level_discount' => [
                'marketing',
                "Hola {{1}}.\n\nPor ser Nivel {{2}} · {{3}}, tienes {{4}}% de descuento en {{5}} hasta el {{6}}.\n\nReserva tu atención desde el botón.",
                ['Marco', '5', 'Plata', '15', 'Corte + Barba', '31/12/2026'],
            ],
        ];

        $templates = [];
        foreach ($definitions as $name => [$category, $body, $samples]) {
            $templates[$name] = WhatsAppTemplate::withoutGlobalScope('business')->create([
                'business_id' => $business->id,
                'public_id' => (string) Str::uuid(),
                'technical_name' => $name,
                'display_name' => match ($name) {
                    'loyalty_welcome' => 'Bienvenida al programa',
                    'loyalty_xp_update' => 'Resumen después de una atención',
                    'loyalty_level_up' => 'Aviso de subida de nivel',
                    'loyalty_reward_redeemed' => 'Confirmación de canje',
                    'campaign_level_discount' => 'Promoción por nivel',
                },
                'category' => $category,
                'language' => 'es_PE',
                'header_type' => $name === 'loyalty_level_up' ? 'image' : 'none',
                'body' => $body,
                'footer' => $category === 'marketing' ? 'Puedes dejar de recibir promociones respondiendo SALIR.' : null,
                'variables' => range(1, count($samples)),
                'samples' => $samples,
                'status' => 'approved',
                'last_synced_at' => now(),
            ]);
        }

        return $templates;
    }
}
