<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use App\Data\ProviderSendResult;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(private readonly WhatsAppAccount $account) {}

    public function send(WhatsAppMessage $message): ProviderSendResult
    {
        $this->assertConfigured();
        $version = config('whatsapp.graph_api_version');
        $url = "https://graph.facebook.com/{$version}/{$this->account->phone_number_id}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($message->phone_e164, '+'),
        ];

        if ($message->template) {
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $message->template->technical_name,
                'language' => ['code' => $message->template->language],
                'components' => [[
                    'type' => 'body',
                    'parameters' => collect($message->variables ?? [])
                        ->reject(fn ($value, $key) => str_starts_with((string) $key, '_'))
                        ->values()
                        ->map(fn ($value) => ['type' => 'text', 'text' => (string) $value])
                        ->all(),
                ]],
            ];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = ['preview_url' => false, 'body' => $message->body_preview];
        }

        try {
            $response = Http::withToken($this->account->access_token)
                ->acceptJson()
                ->timeout(config('whatsapp.timeout_seconds'))
                ->connectTimeout(3)
                ->post($url, $payload)
                ->throw();
        } catch (RequestException $exception) {
            $error = $exception->response?->json('error');
            throw new \RuntimeException(
                is_array($error) ? ($error['message'] ?? 'Meta rechazó el mensaje.') : 'Meta no respondió correctamente.',
                (int) ($error['code'] ?? 0),
                $exception,
            );
        }

        $messageId = $response->json('messages.0.id');
        if (! $messageId) {
            throw new \RuntimeException('Meta no devolvió un identificador de mensaje.');
        }

        return new ProviderSendResult($messageId, 'sent');
    }

    public function health(): array
    {
        $missing = collect(['phone_number_id', 'access_token', 'app_secret', 'webhook_verify_token'])
            ->filter(fn (string $field) => blank($this->account->{$field}))
            ->values()
            ->all();

        return [
            'ok' => $missing === [] && $this->account->send_enabled,
            'provider' => 'meta',
            'send_enabled' => $this->account->send_enabled,
            'missing' => $missing,
            'graph_api_version' => config('whatsapp.graph_api_version'),
        ];
    }

    private function assertConfigured(): void
    {
        if (! $this->account->send_enabled || ! config('whatsapp.send_enabled')) {
            throw new \RuntimeException('El envío real de WhatsApp está deshabilitado.');
        }

        if (blank($this->account->phone_number_id) || blank($this->account->access_token)) {
            throw new \RuntimeException('La cuenta de Meta no está configurada.');
        }
    }
}
