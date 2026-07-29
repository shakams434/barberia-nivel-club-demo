<?php

namespace App\Contracts;

use App\Data\ProviderSendResult;
use App\Models\WhatsAppMessage;

interface WhatsAppProviderInterface
{
    public function send(WhatsAppMessage $message): ProviderSendResult;

    public function health(): array;
}
