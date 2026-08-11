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
        ],
        'visit_registered' => [
            'name' => 'Resumen después de una atención',
            'trigger' => 'Se ejecuta al registrar una atención que no produce una subida de nivel.',
            'default_template' => 'loyalty_xp_update',
            'variables' => ['Nombre del cliente', 'Nombre de la barbería', 'XP ganados', 'Nivel actual', 'Rango actual', 'Progreso al siguiente nivel'],
        ],
        'level_increased' => [
            'name' => 'Aviso de subida de nivel',
            'trigger' => 'Reemplaza al resumen normal cuando la atención hace subir de nivel.',
            'default_template' => 'loyalty_level_up',
            'variables' => ['Nombre del cliente', 'Nuevo nivel', 'Nuevo rango', 'Nombre de la barbería', 'Recompensa desbloqueada'],
        ],
        'reward_redeemed' => [
            'name' => 'Confirmación de canje',
            'trigger' => 'Se ejecuta después de confirmar el canje de una recompensa.',
            'default_template' => 'loyalty_reward_redeemed',
            'variables' => ['Nombre del cliente', 'Recompensa canjeada', 'Nombre de la barbería'],
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
            return $automation->active ? $automation->template : null;
        }

        return WhatsAppTemplate::withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('technical_name', $definition['default_template'])
            ->first() ?? $this->setup->ensureTemplate($business, $definition['default_template']);
    }

    public function validateTemplate(string $eventKey, WhatsAppTemplate $template): void
    {
        $definition = self::DEFINITIONS[$eventKey] ?? null;
        if (! $definition || $template->category !== 'utility') {
            throw new \DomainException('Selecciona una plantilla de servicio compatible con esta acción.');
        }

        preg_match_all('/\{\{(\d+)\}\}/', $template->body, $matches);
        $variableCount = collect($matches[1])->unique()->count();
        if ($variableCount !== count($definition['variables'])) {
            throw new \DomainException('La plantilla no usa la cantidad de datos que necesita esta acción.');
        }
    }
}
