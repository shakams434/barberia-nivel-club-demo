<?php

namespace App\Services;

use App\Models\Business;
use App\Models\LoyaltyProgram;
use App\Models\MessageAutomation;
use App\Models\Service;
use App\Models\Tier;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Str;

class BusinessSetupService
{
    public function initialize(Business $business): void
    {
        $business->update([
            'settings' => array_merge([
                'currency' => 'PEN',
                'consent_version' => 'v1',
                'loyalty_consent_text' => 'Acepto participar en el programa de fidelidad y recibir mensajes operativos sobre XP, niveles y recompensas.',
                'marketing_consent_text' => 'Autorizo recibir promociones por WhatsApp. Puedo retirar esta autorización respondiendo SALIR.',
                'privacy_url' => null,
            ], $business->settings ?? []),
        ]);

        LoyaltyProgram::withoutGlobalScope('business')->firstOrCreate(
            ['business_id' => $business->id],
            [
                'xp_per_level' => 100,
                'recent_visit_window_minutes' => 10,
                'campaign_batch_size' => 20,
                'marketing_frequency_limit' => 2,
                'marketing_frequency_days' => 30,
                'campaign_window_start' => '09:00',
                'campaign_window_end' => '20:00',
            ],
        );

        foreach ([
            ['Bronce', 1, 4, '#CD7F32'],
            ['Plata', 5, 9, '#C0C0C0'],
            ['Oro', 10, 19, '#D4AF37'],
            ['Diamante', 20, 34, '#67E8F9'],
            ['Leyenda', 35, null, '#C084FC'],
        ] as $index => [$name, $min, $max, $color]) {
            Tier::withoutGlobalScope('business')->firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                [
                    'public_id' => (string) Str::uuid(),
                    'min_level' => $min,
                    'max_level' => $max,
                    'color' => $color,
                    'icon' => 'shield',
                    'sort_order' => $index,
                    'active' => true,
                ],
            );
        }

        foreach ([['Corte', 100, 40], ['Barba', 70, 25], ['Corte + Barba', 160, 60]] as $index => [$name, $xp, $duration]) {
            Service::withoutGlobalScope('business')->firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                [
                    'public_id' => (string) Str::uuid(),
                    'xp' => $xp,
                    'duration_minutes' => $duration,
                    'sort_order' => $index,
                    'active' => true,
                ],
            );
        }

        WhatsAppAccount::withoutGlobalScope('business')->firstOrCreate(
            ['business_id' => $business->id],
            ['provider' => app()->isProduction() ? 'meta' : 'fake', 'send_enabled' => false],
        );

        foreach (array_keys($this->templates()) as $technicalName) {
            $this->ensureTemplate($business, $technicalName);
        }

        foreach (MessageAutomationService::DEFINITIONS as $eventKey => $definition) {
            if (! $definition['default_enabled']) {
                continue;
            }

            $template = $this->ensureTemplate($business, $definition['default_template']);
            MessageAutomation::withoutGlobalScope('business')->firstOrCreate(
                ['business_id' => $business->id, 'event_key' => $eventKey],
                ['whatsapp_template_id' => $template->id, 'active' => true],
            );
        }
    }

    public function ensureTemplate(Business $business, string $technicalName): WhatsAppTemplate
    {
        $definition = $this->templates()[$technicalName] ?? null;

        if ($definition === null) {
            throw new \InvalidArgumentException("No existe la plantilla base [{$technicalName}].");
        }

        return WhatsAppTemplate::withoutGlobalScope('business')->firstOrCreate(
            [
                'business_id' => $business->id,
                'technical_name' => $technicalName,
                'language' => 'es_PE',
            ],
            [
                'public_id' => (string) Str::uuid(),
                'display_name' => $definition['display_name'],
                'category' => $definition['category'],
                'header_type' => 'none',
                'body' => $definition['body'],
                'footer' => $definition['footer'] ?? null,
                'variables' => range(1, count($definition['samples'])),
                'samples' => $definition['samples'],
                'registration_source' => app()->isProduction() ? 'manual' : 'demo',
                'status' => app()->isProduction() ? 'draft' : 'approved',
            ],
        );
    }

    private function templates(): array
    {
        return [
            'loyalty_welcome' => [
                'display_name' => 'Bienvenida al programa',
                'category' => 'utility',
                'body' => "Hola {{1}}. Tu inscripción en {{2}} está confirmada.\nComienzas en Nivel {{3}}. Responde SALDO, NIVEL, PREMIOS o AYUDA.",
                'samples' => ['Cliente', 'Mi barbería', '1'],
            ],
            'loyalty_xp_update' => [
                'display_name' => 'Resumen después de una atención',
                'category' => 'utility',
                'body' => "Hola {{1}}. Ganaste {{3}} XP en {{2}}.\nAhora eres Nivel {{4}} · {{5}}. Progreso: {{6}}%.",
                'samples' => ['Cliente', 'Mi barbería', '100', '4', 'Bronce', '50'],
            ],
            'loyalty_level_up' => [
                'display_name' => 'Aviso de subida de nivel',
                'category' => 'utility',
                'body' => "Subiste de nivel, {{1}}.\nAhora eres Nivel {{2}} · {{3}} en {{4}}.\nRecompensa: {{5}}.",
                'samples' => ['Cliente', '5', 'Plata', 'Mi barbería', 'Beneficio especial'],
            ],
            'loyalty_reward_redeemed' => [
                'display_name' => 'Confirmación de canje',
                'category' => 'utility',
                'body' => 'Hola {{1}}. Confirmamos el canje de {{2}} en {{3}}. Tu XP histórico se mantiene.',
                'samples' => ['Cliente', 'Beneficio especial', 'Mi barbería'],
            ],
            'loyalty_opt_out' => [
                'display_name' => 'Confirmación de baja promocional',
                'category' => 'utility',
                'body' => 'Hola {{1}}. Dejaste de recibir promociones de {{2}}. Tu nivel y recompensas se mantienen.',
                'samples' => ['Cliente', 'Mi barbería'],
            ],
        ];
    }
}
