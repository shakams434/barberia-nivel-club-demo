<?php

namespace App\Services;

use App\Models\Consent;
use App\Models\Customer;
use App\Models\InboundMessage;
use App\Models\Tier;
use Illuminate\Support\Str;

class InboundMessageProcessor
{
    public function __construct(
        private readonly ConsentService $consents,
        private readonly WhatsAppMessageService $messages,
        private readonly MessageAutomationService $automations,
    ) {}

    public function process(InboundMessage $inbound): void
    {
        if ($inbound->processed_at) {
            return;
        }

        $command = Str::of($inbound->message_text ?? '')
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->upper()
            ->value();
        $customer = Customer::where('phone_hash', Customer::phoneHash($inbound->from_phone_e164))->first();
        $responseTemplate = null;
        $responseVariables = [];

        if (in_array($command, ['UNIRME', 'QUIERO UNIRME'], true)) {
            $customer ??= $this->join($inbound);
            if (! $this->consents->hasActive($customer, Consent::LOYALTY)) {
                $this->consents->record(
                    $customer,
                    Consent::LOYALTY,
                    true,
                    'whatsapp',
                    text: 'Solicito unirme al programa de fidelización mediante WhatsApp.',
                    evidence: $inbound->meta_message_id,
                );
            }
            $response = "¡Bienvenido a {$customer->business->name}! Ya eres Nivel {$customer->level}. Responde SALDO, NIVEL, PREMIOS o AYUDA. Para recibir promociones responde PROMOS SI.";
        } elseif ($command === 'SALIR') {
            if ($customer) {
                $this->consents->record(
                    $customer,
                    Consent::MARKETING,
                    false,
                    'whatsapp',
                    text: 'Revocación de comunicaciones promocionales mediante la palabra SALIR.',
                    evidence: $inbound->meta_message_id,
                );
                $customer->loadMissing('business.whatsappAccount');
                $responseTemplate = $this->automations->templateFor($customer->business, 'marketing_opted_out');
                $responseVariables = [$customer->name, $customer->business->name];
                $response = $this->automations->isConfigured($customer->business, 'marketing_opted_out')
                    ? null
                    : 'Listo. Dejaste de recibir promociones. Seguirás conservando tu nivel y recompensas.';
            } else {
                $response = 'Listo. Dejaste de recibir promociones. Seguirás conservando tu nivel y recompensas.';
            }
        } elseif (in_array($command, ['PROMOS SI', 'ACEPTO PROMOS'], true)) {
            if (! $customer) {
                $response = 'Primero responde QUIERO UNIRME para registrarte.';
            } else {
                $this->consents->record(
                    $customer,
                    Consent::MARKETING,
                    true,
                    'whatsapp',
                    text: 'Autorizo recibir promociones por WhatsApp. Puedo responder SALIR en cualquier momento.',
                    evidence: $inbound->meta_message_id,
                );
                $response = 'Autorización registrada. Puedes responder SALIR en cualquier momento.';
            }
        } elseif (in_array($command, ['SALDO', 'NIVEL'], true)) {
            $response = $customer
                ? $this->balanceText($customer)
                : 'Aún no estás registrado. Responde QUIERO UNIRME para comenzar.';
        } elseif ($command === 'PREMIOS') {
            $response = $customer
                ? $this->rewardsText($customer)
                : 'Aún no estás registrado. Responde QUIERO UNIRME para comenzar.';
        } elseif ($command === 'AYUDA') {
            $response = $customer
                ? "Comandos: SALDO, NIVEL, PREMIOS y SALIR. Si necesitas ayuda, visita {$customer->business->name}."
                : 'Comandos: QUIERO UNIRME, SALDO, NIVEL, PREMIOS y SALIR. También puedes visitar el negocio.';
        } else {
            $response = null;
        }

        $customer ??= Customer::where('phone_hash', Customer::phoneHash($inbound->from_phone_e164))->first();
        if ($customer && $inbound->conversation && ! $inbound->conversation->customer_id) {
            $inbound->conversation->update(['customer_id' => $customer->id, 'contact_name' => $customer->name]);
        }
        if ($customer && ($responseTemplate || filled($response))) {
            $message = $this->messages->queue(
                $customer,
                $responseTemplate,
                $responseVariables,
                'inbound-response:'.$inbound->id,
                $response,
            );
            $provider = $customer->business->whatsappAccount?->provider;
            if (! $responseTemplate || in_array($provider, ['fake', 'baileys'], true) || $responseTemplate->status === 'approved') {
                $this->messages->attemptNow($message->id, true);
                $inbound->update(['replied_at' => now()]);
            } else {
                $message->update([
                    'error_code' => 'TEMPLATE_NOT_APPROVED',
                    'error_message' => 'Aprueba la plantilla transaccional en Meta antes de reintentar.',
                ]);
            }
        }

        $inbound->update([
            'customer_id' => $customer?->id,
            'command' => $command,
            'status' => filled($response) || $responseTemplate ? 'processed' : 'needs_reply',
            'processed_at' => now(),
        ]);
    }

    private function join(InboundMessage $inbound): Customer
    {
        $tier = Tier::where('active', true)->orderBy('min_level')->first();

        return Customer::create([
            'business_id' => $inbound->business_id,
            'tier_id' => $tier?->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Cliente WhatsApp · '.substr($inbound->from_phone_e164, -4),
            'phone_raw' => $inbound->from_phone_e164,
            'phone_e164' => $inbound->from_phone_e164,
            'source' => 'whatsapp_qr',
            'status' => 'pending',
            'joined_at' => now(),
        ]);
    }

    private function balanceText(Customer $customer): string
    {
        $customer->loadMissing(['business.loyaltyProgram', 'tier']);
        $progress = $customer->progressPercent($customer->business->loyaltyProgram->xp_per_level);

        return "Tienes {$customer->xp_total} XP. Nivel {$customer->level} · {$customer->tier?->name}. Progreso al siguiente nivel: {$progress}%.";
    }

    private function rewardsText(Customer $customer): string
    {
        $rewards = $customer->rewards()
            ->with('reward')
            ->where('status', 'available')
            ->get()
            ->pluck('reward.name');

        return $rewards->isEmpty()
            ? 'Todavía no tienes recompensas pendientes. Sigue sumando XP.'
            : 'Recompensas disponibles: '.$rewards->join(', ').'.';
    }
}
