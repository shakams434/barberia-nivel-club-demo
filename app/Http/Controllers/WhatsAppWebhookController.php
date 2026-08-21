<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\Customer;
use App\Models\InboundMessage;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppConversationService;
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

    public function receive(Request $request, TenantContext $tenant, WhatsAppConversationService $conversations): Response
    {
        $raw = $request->getContent();
        $payload = $request->json()->all();
        $values = collect(Arr::wrap($payload['entry'] ?? []))
            ->flatMap(fn (array $entry) => Arr::wrap($entry['changes'] ?? []))
            ->pluck('value')
            ->filter(fn ($value) => is_array($value))
            ->values();
        $phoneNumberIds = $values->pluck('metadata.phone_number_id')->filter()->unique()->values();
        $accounts = WhatsAppAccount::withoutGlobalScope('business')
            ->whereIn('phone_number_id', $phoneNumberIds)
            ->get()
            ->keyBy('phone_number_id');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $signedAccounts = $accounts->filter(function (WhatsAppAccount $account) use ($raw, $signature): bool {
            if (! str_starts_with($signature, 'sha256=')) {
                return false;
            }
            $secret = $account->app_secret ?: config('whatsapp.app_secret');

            return filled($secret) && hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), $signature);
        });
        abort_if($signedAccounts->isEmpty(), 403);

        foreach ($values as $value) {
            $account = $signedAccounts->get(data_get($value, 'metadata.phone_number_id'));
            if (! $account) {
                continue;
            }
            $tenant->set($account->business_id);
            try {
                $this->processValue($account, $value, $conversations);
                $account->update(['last_webhook_at' => now()]);
            } finally {
                $tenant->clear();
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    public function receiveBot(Request $request, TenantContext $tenant, WhatsAppConversationService $conversations): Response
    {
        $secret = config('whatsapp.baileys_webhook_secret');
        $account = WhatsAppAccount::withoutGlobalScope('business')->where('provider', 'baileys')->first();
        $header = (string) $request->header('Authorization');
        $token = str_starts_with($header, 'Bearer ')
            ? substr($header, 7)
            : (string) $request->header('X-Webhook-Token');

        $valid = (filled($secret) && hash_equals($secret, $token))
            || ($account && filled($account->access_token) && hash_equals((string) $account->access_token, $token));

        if (! $valid) {
            return response('Unauthorized', 401);
        }

        if (! $account) {
            return response('No hay una cuenta de bot conectada.', 400);
        }

        $payload = $request->json()->all();

        $tenant->set($account->business_id);
        try {
            foreach (Arr::wrap($payload['messages'] ?? []) as $messagePayload) {
                $this->handleInboundMessage($account, $messagePayload, $conversations);
            }
            foreach (Arr::wrap($payload['statuses'] ?? []) as $status) {
                $eventKey = implode(':', ['status', $status['id'] ?? 'unknown', $status['status'] ?? 'unknown', $status['timestamp'] ?? '0']);
                $event = $this->recordEvent($account->business_id, $eventKey, 'status', $status);
                if ($event->wasRecentlyCreated) {
                    $this->applyStatus($account->business_id, $status);
                }
            }
            $account->update(['last_webhook_at' => now()]);
        } finally {
            $tenant->clear();
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function processValue(WhatsAppAccount $account, array $value, WhatsAppConversationService $conversations): void
    {
        foreach (Arr::wrap($value['messages'] ?? []) as $messagePayload) {
            $messagePayload['contact_name'] = data_get($value, 'contacts.0.profile.name');
            $this->handleInboundMessage($account, $messagePayload, $conversations);
        }

        foreach (Arr::wrap($value['statuses'] ?? []) as $status) {
            $eventKey = implode(':', ['status', $status['id'] ?? 'unknown', $status['status'] ?? 'unknown', $status['timestamp'] ?? '0']);
            $event = $this->recordEvent($account->business_id, $eventKey, 'status', $status);
            if ($event->wasRecentlyCreated) {
                $this->applyStatus($account->business_id, $status);
            }
        }
    }

    private function handleInboundMessage(WhatsAppAccount $account, array $messagePayload, WhatsAppConversationService $conversations): void
    {
        $metaId = $messagePayload['id'] ?? null;
        $from = $messagePayload['from'] ?? null;
        if (! $metaId || ! $from) {
            return;
        }
        $event = $this->recordEvent($account->business_id, 'message:'.$metaId, 'message', $messagePayload);
        if (! $event->wasRecentlyCreated) {
            return;
        }

        $phone = '+'.ltrim($from, '+');
        $customer = Customer::where('phone_hash', Customer::phoneHash($phone))->first();
        $contactName = $messagePayload['contact_name'] ?? null;
        $conversation = $conversations->forPhone($account->business_id, $phone, $customer, $contactName);
        $text = is_string($messagePayload['text'] ?? null)
            ? $messagePayload['text']
            : (data_get($messagePayload, 'text.body')
                ?? data_get($messagePayload, 'button.text')
                ?? data_get($messagePayload, 'interactive.button_reply.title')
                ?? '');

        $inbound = InboundMessage::firstOrCreate(
            ['meta_message_id' => $metaId],
            [
                'business_id' => $account->business_id,
                'customer_id' => $customer?->id,
                'whatsapp_conversation_id' => $conversation->id,
                'public_id' => (string) Str::uuid(),
                'from_phone_e164' => $phone,
                'message_text' => $text,
                'payload' => [
                    'type' => $messagePayload['type'] ?? 'unknown',
                    'timestamp' => $messagePayload['timestamp'] ?? null,
                    'context_message_id' => $messagePayload['context_id'] ?? data_get($messagePayload, 'context.id'),
                ],
                'status' => 'received',
            ],
        );

        if ($inbound->wasRecentlyCreated) {
            $conversation->update([
                'last_message_at' => now(),
                'last_inbound_at' => now(),
                'unread_count' => $conversation->unread_count + 1,
                'status' => 'open',
            ]);
            ProcessInboundWhatsAppMessage::dispatch($inbound->id)->onQueue('webhooks');
        }
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

        $progress = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];
        if (in_array($message->status, ['read', 'failed', 'cancelled'], true) && $status !== $message->status) {
            return;
        }
        if (isset($progress[$message->status], $progress[$status]) && $progress[$status] < $progress[$message->status]) {
            return;
        }

        $timestamp = isset($payload['timestamp'])
            ? now()->setTimestamp((int) $payload['timestamp'])
            : now();
        $updates = ['status' => $status];

        if (in_array($status, ['sent', 'delivered', 'read'], true)) {
            $updates[$status.'_at'] = $timestamp;
        }
        if (in_array($status, ['delivered', 'read'], true) && ! $message->sent_at) {
            $updates['sent_at'] = $timestamp;
        }
        if ($status === 'read' && ! $message->delivered_at) {
            $updates['delivered_at'] = $timestamp;
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
