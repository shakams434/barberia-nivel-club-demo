<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Http;

class WhatsAppTemplateService
{
    public function render(WhatsAppTemplate $template, array $variables): string
    {
        $variables = collect($variables)
            ->reject(fn ($value, $key) => is_string($key) && str_starts_with($key, '_'))
            ->values()
            ->all();
        $this->validateVariables($template->body, $variables);
        $body = $template->body;

        foreach (array_values($variables) as $index => $value) {
            $body = str_replace('{{'.($index + 1).'}}', (string) $value, $body);
        }

        return trim(implode("\n\n", array_filter([$template->header, $body, $template->footer])));
    }

    public function validateVariables(string $body, array $variables): void
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
        $expected = collect($matches[1])->map(fn ($value) => (int) $value)->unique()->sort()->values()->all();

        if ($expected !== ($expected ? range(1, max($expected)) : [])) {
            throw new \InvalidArgumentException('Las variables de la plantilla deben ser correlativas desde {{1}}.');
        }

        if (count($variables) !== count($expected)) {
            throw new \InvalidArgumentException('La cantidad de valores no coincide con las variables de la plantilla.');
        }
    }

    public function simulateReview(WhatsAppTemplate $template, bool $approve, ?string $reason = null): WhatsAppTemplate
    {
        $template->update([
            'status' => $approve ? 'approved' : 'rejected',
            'rejection_reason' => $approve ? null : ($reason ?: 'Marcada como rechazada en el entorno local.'),
            'last_synced_at' => now(),
        ]);

        return $template->fresh();
    }

    public function submitToMeta(WhatsAppTemplate $template): WhatsAppTemplate
    {
        $account = WhatsAppAccount::firstOrFail();
        if ($account->provider !== 'meta' || ! $account->send_enabled || blank($account->waba_id) || blank($account->access_token)) {
            throw new \RuntimeException('Configura y habilita Meta antes de enviar plantillas.');
        }

        $components = [];
        if ($template->header) {
            $components[] = ['type' => 'HEADER', 'format' => strtoupper($template->header_type), 'text' => $template->header];
        }
        $components[] = [
            'type' => 'BODY',
            'text' => $template->body,
            'example' => $template->samples ? ['body_text' => [array_values($template->samples)]] : null,
        ];
        if ($template->footer) {
            $components[] = ['type' => 'FOOTER', 'text' => $template->footer];
        }
        if ($template->buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $template->buttons];
        }

        $response = Http::withToken($account->access_token)
            ->timeout(config('whatsapp.timeout_seconds'))
            ->post('https://graph.facebook.com/'.config('whatsapp.graph_api_version')."/{$account->waba_id}/message_templates", [
                'name' => $template->technical_name,
                'language' => $template->language,
                'category' => strtoupper($template->category),
                'components' => array_values(array_map(fn (array $item) => array_filter($item, fn ($value) => $value !== null), $components)),
            ])->throw();

        $template->update([
            'meta_id' => $response->json('id'),
            'status' => strtolower($response->json('status', 'pending')),
            'last_synced_at' => now(),
        ]);

        return $template->fresh();
    }

    public function syncFromMeta(WhatsAppTemplate $template): WhatsAppTemplate
    {
        $account = WhatsAppAccount::firstOrFail();
        if (! $template->meta_id || blank($account->access_token)) {
            throw new \RuntimeException('La plantilla todavía no tiene un identificador de Meta.');
        }

        $data = Http::withToken($account->access_token)
            ->timeout(config('whatsapp.timeout_seconds'))
            ->get('https://graph.facebook.com/'.config('whatsapp.graph_api_version')."/{$template->meta_id}", [
                'fields' => 'id,name,status,category,rejected_reason,language',
            ])->throw()->json();

        $template->update([
            'status' => strtolower($data['status'] ?? $template->status),
            'category' => strtolower($data['category'] ?? $template->category),
            'rejection_reason' => $data['rejected_reason'] ?? null,
            'last_synced_at' => now(),
        ]);

        return $template->fresh();
    }
}
