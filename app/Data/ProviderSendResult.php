<?php

namespace App\Data;

final readonly class ProviderSendResult
{
    public function __construct(
        public string $providerMessageId,
        public string $status = 'sent',
    ) {}
}
