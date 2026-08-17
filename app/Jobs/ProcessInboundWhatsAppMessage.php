<?php

namespace App\Jobs;

use App\Models\InboundMessage;
use App\Services\InboundMessageProcessor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInboundWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(public readonly int $inboundMessageId) {}

    public function handle(InboundMessageProcessor $processor, TenantContext $tenant): void
    {
        $inbound = InboundMessage::withoutGlobalScope('business')->findOrFail($this->inboundMessageId);
        $tenant->set($inbound->business_id);

        try {
            $processor->process($inbound);
        } finally {
            $tenant->clear();
        }
    }
}
