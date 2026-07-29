<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppMessageService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $messageId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(WhatsAppMessageService $messages, TenantContext $tenant): void
    {
        $message = WhatsAppMessage::withoutGlobalScope('business')->findOrFail($this->messageId);
        $tenant->set($message->business_id);

        try {
            $messages->attemptNow($message->id);
        } finally {
            $tenant->clear();
        }
    }
}
