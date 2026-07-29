<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\InboundMessage;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = (string) $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $challenge = (string) $request->query('hub.challenge', $request->query('hub_challenge'));
        abort_unless($mode === 'subscribe', 403);
        $valid = WhatsAppAccount::withoutGlobalScope('business')
            ->get()
            ->contains(fn (WhatsAppAccount $account) => filled($account->webhook_verify_token) && hash_equals($account->webhook_verify_token, $token));

        if (! $valid && filled(config('whatsapp.webhook_verify_token'))) {
            $valid = hash_equals((string) config('whatsapp.webhook_verify_token'), $token);
        }

        abort_unless($valid, 403);

        return response($challenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, TenantContext $tenant): Response
    {
        $raw = $request->getContent();
        $phoneNumberId = data_get($request->json()->all(), 'entry.0.changes.0.value.metadata.phone_number_id');
        $account = WhatsAppAccount::withoutGlobalScope('business')
            ->where('phone_number_id', $phoneNumberId)
            ->first();
        $secret = $account?->app_secret ?: config('whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        abort_unless(
            filled($secret)
            && str_starts_with($signature, 'sha256=')
            && hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), $signature),
            403,
        );

        abort_unless($account, 202);
        $tenant->set($account->business_id);

        try {
            $value = data_get($request->json()->all(), 'entry.0.changes.0.value', []);

            foreach (Arr::wrap($value['messages'] ?? []) as $payload) {
                $metaId = $payload['id'] ?? null;
                $from = $payload['from'] ?? null;
                if (! $metaId || ! $from) {
                    continue;
                }
                $event = $this->recordEvent($account->business_id, 'message:'.$metaId, 'message', $payload);
                if (! $event->wasRecentlyCreated) {
                    continue;
                }

                $text = data_get($payload, 'text.body')
                    ?? data_get($payload, 'button.text')
                    ?? data_get($payload, 'interactive.button_reply.title')
                    ?? '';

                $inbound = InboundMessage::firstOrCreate(
                    ['meta_message_id' => $metaId],
                    [
                        'business_id' => $account->business_id,
                        'public_id' => (string) Str::uuid(),
                        'from_phone_e164' => '+'.ltrim($from, '+'),
                        'message_text' => $text,
                        'payload' => $payload,
                        'status' => 'received',
                    ],
                );

                if ($inbound->wasRecentlyCreated) {
                    ProcessInboundWhatsAppMessage::dispatch($inbound->id)->onQueue('webhooks');
                }
            }

            foreach (Arr::wrap($value['statuses'] ?? []) as $status) {
                $eventKey = implode(':', [
                    'status',
                    $status['id'] ?? 'unknown',
                    $status['status'] ?? 'unknown',
                    $status['timestamp'] ?? '0',
                ]);
                $event = $this->recordEvent($account->business_id, $eventKey, 'status', $status);
                if ($event->wasRecentlyCreated) {
                    $this->applyStatus($account->business_id, $status);
                }
            }
        } finally {
            $tenant->clear();
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function recordEvent(int $businessId, string $eventKey, string $type, array $payload): WebhookEvent
    {
        return WebhookEvent::firstOrCreate(
            ['business_id' => $businessId, 'event_key' => $eventKey],
            [
                'public_id' => (string) Str::uuid(),
                'event_type' => $type,
                'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES)),
                'status' => 'processed',
                'processed_at' => now(),
            ],
        );
    }

    private function applyStatus(int $businessId, array $payload): void
    {
        $message = WhatsAppMessage::withoutGlobalScope('business')
            ->where('business_id', $businessId)
            ->where('meta_message_id', $payload['id'] ?? '')
            ->first();

        if (! $message) {
            return;
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        if (! in_array($status, ['queued', 'sent', 'delivered', 'read', 'failed', 'cancelled'], true)) {
            return;
        }

        $timestamp = isset($payload['timestamp'])
            ? now()->setTimestamp((int) $payload['timestamp'])
            : now();
        $updates = ['status' => $status];

        if (in_array($status, ['sent', 'delivered', 'read'], true)) {
            $updates[$status.'_at'] = $timestamp;
        }

        if (in_array($status, ['failed', 'cancelled'], true)) {
            $updates['failed_at'] = $timestamp;
            $updates['error_code'] = (string) data_get($payload, 'errors.0.code', '');
            $updates['error_message'] = Str::limit((string) data_get($payload, 'errors.0.title', 'Meta informó un error.'), 500);
        }

        $message->update($updates);
        $message->campaignRecipient?->update([
            'status' => $status,
            'processed_at' => now(),
        ]);
    }
}
