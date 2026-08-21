<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use App\Data\ProviderSendResult;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class BaileysWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(private readonly ?WhatsAppAccount $account = null) {}

    private function baseUrl(): string
    {
        return (string) ($this->account?->baileys_base_url ?: config('whatsapp.baileys_base_url'));
    }

    private function apiToken(): ?string
    {
        return $this->account?->access_token ?: config('whatsapp.baileys_api_token');
    }

    public function send(WhatsAppMessage $message): ProviderSendResult
    {
        $this->assertConfigured();

        try {
            $response = Http::withToken((string) $this->apiToken())
                ->acceptJson()
                ->timeout(config('whatsapp.timeout_seconds'))
                ->connectTimeout(3)
                ->post($this->baseUrl().'/send-message', [
                    'to' => ltrim($message->phone_e164, '+'),
                    'text' => $message->body_preview,
                ])
                ->throw();
        } catch (RequestException $exception) {
            $body = $exception->response?->json();

            throw new \RuntimeException(
                is_array($body) ? ($body['error'] ?? 'El bot rechazó el mensaje.') : 'El bot no respondió correctamente.',
                $exception->response?->status() ?? 0,
                $exception,
            );
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('No se pudo alcanzar el bot de WhatsApp (Baileys). Verifica BAILEYS_BASE_URL.', 0, $exception);
        }

        if (! $response->json('ok')) {
            throw new \RuntimeException((string) ($response->json('error') ?? 'El bot no confirmó el envío.'));
        }

        $messageId = (string) ($response->json('id') ?? '');
        if ($messageId === '') {
            throw new \RuntimeException('El bot no devolvió un identificador de mensaje.');
        }

        return new ProviderSendResult($messageId, 'sent');
    }

    public function health(): array
    {
        $baseUrl = $this->baseUrl();
        $token = $this->apiToken();

        if (blank($baseUrl) || blank($token)) {
            return [
                'ok' => false,
                'provider' => 'baileys',
                'send_enabled' => false,
                'missing' => collect(['base_url', 'api_token'])
                    ->filter(fn (string $key) => blank($key === 'base_url' ? $baseUrl : $token))
                    ->map(fn (string $key) => $key === 'base_url' ? 'URL del bot' : 'Token del bot')
                    ->values()
                    ->all(),
            ];
        }

        try {
            $response = Http::withToken((string) $token)
                ->acceptJson()
                ->timeout(config('whatsapp.timeout_seconds'))
                ->connectTimeout(3)
                ->get($baseUrl.'/')
                ->throw();
            $status = $response->json('status');
        } catch (\Throwable $exception) {
            return ['ok' => false, 'provider' => 'baileys', 'send_enabled' => false, 'error' => $exception->getMessage()];
        }

        return [
            'ok' => $status === 'connected',
            'provider' => 'baileys',
            'send_enabled' => $status === 'connected',
            'bot_status' => $status,
            'base_url' => $baseUrl,
        ];
    }

    private function assertConfigured(): void
    {
        if (blank($this->baseUrl()) || blank($this->apiToken())) {
            throw new \RuntimeException('El bot de WhatsApp (Baileys) no está configurado.');
        }
    }
}
