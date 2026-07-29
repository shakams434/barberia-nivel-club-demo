<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use App\Data\ProviderSendResult;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Str;

class FakeWhatsAppProvider implements WhatsAppProviderInterface
{
    public function send(WhatsAppMessage $message): ProviderSendResult
    {
        if (($message->variables['simulate_failure'] ?? false) === true) {
            throw new \RuntimeException('Fallo simulado del proveedor de WhatsApp.');
        }

        return new ProviderSendResult('fake_'.Str::lower(Str::random(24)), 'sent');
    }

    public function health(): array
    {
        return ['ok' => true, 'provider' => 'fake', 'simulation' => true];
    }
}
