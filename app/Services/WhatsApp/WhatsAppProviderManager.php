<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use App\Models\WhatsAppAccount;

class WhatsAppProviderManager
{
    public function forCurrentBusiness(): WhatsAppProviderInterface
    {
        $account = WhatsAppAccount::first();
        $provider = $account?->provider ?? config('whatsapp.provider', 'fake');

        if ($provider === 'meta' && $account) {
            return new MetaWhatsAppProvider($account);
        }

        return app(FakeWhatsAppProvider::class);
    }
}
