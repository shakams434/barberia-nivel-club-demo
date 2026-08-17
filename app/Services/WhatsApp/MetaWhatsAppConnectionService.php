<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MetaWhatsAppConnectionService
{
    public function inspect(string $wabaId, string $phoneNumberId, string $accessToken): array
    {
        $phones = $this->request($accessToken)
            ->get($this->graphUrl("{$wabaId}/phone_numbers"), [
                'fields' => 'id,display_phone_number,verified_name,quality_rating,platform_type',
            ]);

        if (! $phones->successful()) {
            throw new \RuntimeException($this->errorMessage($phones->json(), 'Meta no pudo validar la cuenta de WhatsApp.'));
        }

        $phone = collect($phones->json('data', []))->firstWhere('id', $phoneNumberId);
        if (! $phone) {
            throw new \RuntimeException('El Phone Number ID no pertenece al WABA ID indicado. Revisa ambos valores en Meta.');
        }

        return [
            'phone_e164' => $this->formatPhone((string) ($phone['display_phone_number'] ?? '')),
            'verified_name' => Str::limit((string) ($phone['verified_name'] ?? 'WhatsApp Business'), 120, ''),
            'quality_rating' => Str::limit((string) ($phone['quality_rating'] ?? 'UNKNOWN'), 40, ''),
            'platform_type' => (string) ($phone['platform_type'] ?? 'CLOUD_API'),
        ];
    }

    public function inspectAccount(WhatsAppAccount $account): array
    {
        if (blank($account->waba_id) || blank($account->phone_number_id) || blank($account->access_token)) {
            throw new \RuntimeException('Faltan datos de Meta para comprobar la conexión.');
        }

        return $this->inspect($account->waba_id, $account->phone_number_id, $account->access_token);
    }

    public function subscribeWebhook(WhatsAppAccount $account): void
    {
        $response = $this->request($account->access_token)
            ->post($this->graphUrl("{$account->waba_id}/subscribed_apps"));

        if (! $response->successful() || ! $response->json('success')) {
            throw new \RuntimeException($this->errorMessage($response->json(), 'Meta no pudo suscribir la aplicación al WABA.'));
        }
    }

    private function request(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout(config('whatsapp.timeout_seconds', 8))
            ->connectTimeout(4);
    }

    private function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.config('whatsapp.graph_api_version').'/'.ltrim($path, '/');
    }

    private function formatPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return $digits ? '+'.$digits : null;
    }

    private function errorMessage(mixed $payload, string $fallback): string
    {
        $message = is_array($payload) ? data_get($payload, 'error.message') : null;

        return Str::limit($message ?: $fallback, 350);
    }
}
