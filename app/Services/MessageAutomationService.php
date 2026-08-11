<?php

namespace App\Services;

use App\Models\Business;
use App\Models\MessageAutomation;
use App\Models\WhatsAppTemplate;

class MessageAutomationService
{
    public function __construct(private readonly BusinessSetupService $setup) {}

    public const DEFINITIONS = [
        'customer_registered' => [
            'name' => 'Bienvenida al registrar un cliente',
            'trigger' => 'Se ejecuta una vez al terminar el registro del cliente.',
            'default_template' => 'loyalty_welcome',
            'variables' => ['Nombre del cliente', 'Nombre de la barbería', 'Nivel inicial'],
            'default_enabled' => true,
        ],
        'visit_registered' => [
            'name' => 'Resumen después de una atención',
            'trigger' => 'Se ejecuta al registrar una atención que no produce una subida de nivel.',
            'default_template' => 'loyalty_xp_update',
            'variables' => ['Nombre del cliente', 'Nombre de la barbería', 'XP ganados', 'Nivel actual', 'Rango actual', 'Progreso al siguiente nivel'],
            'default_enabled' => true,
        ],
        'level_increased' => [
            'name' => 'Aviso de subida de nivel',
            'trigger' => 'Reemplaza al resumen normal cuando la atención hace subir de nivel.',
            'default_template' => 'loyalty_level_up',
            'variables' => ['Nombre del cliente', 'Nuevo nivel', 'Nuevo rango', 'Nombre de la barbería', 'Recompensa desbloqueada'],
            'default_enabled' => true,
        ],
        'reward_redeemed' => [
            'name' => 'Confirmación de canje',
            'trigger' => 'Se ejecuta después de confirmar el canje de una recompensa.',
            'default_template' => 'loyalty_reward_redeemed',
            'variables' => ['Nombre del cliente', 'Recompensa canjeada', 'Nombre de la barbería'],
            'default_enabled' => true,
        ],
        'marketing_opted_out' => [
            'name' => 'Confirmación al dejar promociones',
            'trigger' => 'Se ejecuta cuando el cliente responde SALIR y retira el permiso de promociones.',
            'default_template' => 'loyalty_opt_out',
            'variables' => ['Nombre del cliente', 'Nombre de la barbería'],
            'default_enabled' => false,
        ],
    ];

    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public function templateFor(Business $business, string $eventKey): ?WhatsAppTemplate
    {
        $definition = self::DEFINITIONS[$eventKey] ?? null;
        if (! $definition) {
            throw new \InvalidArgumentException('La acción automática no existe.');
        }

        $automation = MessageAutomation::withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('event_key', $eventKey)
            ->with('template')
            ->first();

        if ($automation) {
            return $automation->active && $automation->template?->status === 'approved'
                ? $automation->template
                : null;
        }

        if (! $definition['default_enabled']) {
            return null;
        }

        $template = WhatsAppTemplate::withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('technical_name', $definition['default_template'])
            ->where('status', 'approved')
            ->first();
        if ($template) {
            return $template;
        }

        $template = $this->setup->ensureTemplate($business, $definition['default_template']);

        return $template->status === 'approved' ? $template : null;
    }

    public function isConfigured(Business $business, string $eventKey): bool
    {
        return MessageAutomation::withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('event_key', $eventKey)
            ->exists();
    }

    public function validateTemplate(string $eventKey, WhatsAppTemplate $template): void
    {
        $definition = self::DEFINITIONS[$eventKey] ?? null;
        if (! $definition || $template->category !== 'utility' || $template->status !== 'approved') {
            throw new \DomainException('Selecciona una plantilla de servicio compatible con esta acción.');
        }

        preg_match_all('/\{\{(\d+)\}\}/', $template->body, $matches);
        $variableCount = collect($matches[1])->unique()->count();
        if ($variableCount !== count($definition['variables'])) {
            throw new \DomainException('La plantilla no usa la cantidad de datos que necesita esta acción.');
        }
    }
}
