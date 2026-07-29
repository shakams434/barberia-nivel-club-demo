<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\Visit;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppProviderManager;
use Illuminate\Support\Str;

class WhatsAppMessageService
{
    public function __construct(
        private readonly WhatsAppProviderManager $providers,
        private readonly WhatsAppTemplateService $templates,
        private readonly ConsentService $consents,
        private readonly LevelCardGenerator $cards,
        private readonly BusinessSetupService $setup,
    ) {}

    public function queue(
        Customer $customer,
        ?WhatsAppTemplate $template,
        array $variables,
        string $idempotencyKey,
        ?string $text = null,
        ?int $campaignRecipientId = null,
    ): WhatsAppMessage {
        return WhatsAppMessage::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'business_id' => $customer->business_id,
                'customer_id' => $customer->id,
                'whatsapp_template_id' => $template?->id,
                'campaign_recipient_id' => $campaignRecipientId,
                'public_id' => (string) Str::uuid(),
                'direction' => 'outbound',
                'message_type' => $template ? 'template' : 'text',
                'phone_e164' => $customer->phone_e164,
                'status' => 'queued',
                'body_preview' => $template ? $this->templates->render($template, $variables) : $text,
                'variables' => $variables,
                'queued_at' => now(),
            ],
        );
    }

    public function attemptNow(int $messageId, bool $requeueOnFailure = false): WhatsAppMessage
    {
        $message = WhatsAppMessage::with(['template', 'customer'])->findOrFail($messageId);
        if ($message->attempts >= config('whatsapp.max_attempts')) {
            $message->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_code' => 'MAX_ATTEMPTS',
                'error_message' => 'Se alcanzó el máximo de intentos configurado.',
            ]);

            return $message->fresh();
        }

        $message->increment('attempts');

        try {
            $result = $this->providers->forCurrentBusiness()->send($message);
            $message->update([
                'status' => $result->status,
                'meta_message_id' => $result->providerMessageId,
                'sent_at' => now(),
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);

            $message->campaignRecipient?->update(['status' => 'sent', 'processed_at' => now()]);
        } catch (\Throwable $exception) {
            $message->update([
                'status' => $requeueOnFailure ? 'queued' : 'failed',
                'failed_at' => now(),
                'error_code' => (string) $exception->getCode(),
                'error_message' => $this->sanitizeError($exception->getMessage()),
            ]);

            if ($requeueOnFailure) {
                SendWhatsAppMessage::dispatch($message->id)->onQueue('messages');
            } else {
                throw $exception;
            }
        }

        return $message->fresh();
    }

    private function sanitizeError(string $message): string
    {
        $message = preg_replace_callback(
            '/\+?\d{9,15}/',
            fn (array $match) => '••••'.substr($match[0], -4),
            $message,
        );
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._-]+/i', 'Bearer [protegido]', $message);

        return Str::limit((string) $message, 500);
    }

    public function notifyVisit(Visit $visit, int $previousLevel, array $unlockedRewardIds): ?WhatsAppMessage
    {
        $visit->loadMissing(['customer.business', 'customer.tier', 'service']);
        $customer = $visit->customer;

        if (! $this->consents->hasActive($customer, Consent::LOYALTY)) {
            return null;
        }

        $levelUp = $customer->level > $previousLevel;
        $templateName = $levelUp ? 'loyalty_level_up' : 'loyalty_xp_update';
        $template = $this->setup->ensureTemplate($customer->business, $templateName);
        $reward = $unlockedRewardIds ? $customer->rewards()->with('reward')->whereIn('id', $unlockedRewardIds)->first()?->reward : null;
        $progress = $customer->progressPercent($customer->business->loyaltyProgram->xp_per_level);
        $cardPath = $levelUp ? $this->cards->generate($customer, $reward) : null;

        if ($levelUp) {
            $variables = [
                $customer->name,
                $customer->level,
                $customer->tier?->name ?? 'Bronce',
                $customer->business->name,
                $reward?->name ?? 'Sigue avanzando para desbloquear tu próxima recompensa',
                '_card_path' => $cardPath,
            ];
        } else {
            $variables = [
                $customer->name,
                $customer->business->name,
                $visit->xp_awarded,
                $customer->level,
                $customer->tier?->name ?? 'Bronce',
                $progress,
            ];
        }

        $fallback = $levelUp
            ? "¡Subiste al Nivel {$customer->level} · {$customer->tier?->name}! {$reward?->name}"
            : "Ganaste {$visit->xp_awarded} XP. Ahora estás en Nivel {$customer->level}.";
        $message = $this->queue($customer, $template, $variables, 'visit-notification:'.$visit->id, $fallback);
        if ($customer->business->whatsappAccount?->provider === 'fake' || $template->status === 'approved') {
            $this->attemptNow($message->id, true);
        } else {
            $message->update([
                'error_code' => 'TEMPLATE_NOT_APPROVED',
                'error_message' => 'Aprueba la plantilla transaccional en Meta antes de reintentar.',
            ]);
        }

        return $message->fresh();
    }
}
