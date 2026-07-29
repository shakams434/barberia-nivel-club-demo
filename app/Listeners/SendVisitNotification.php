<?php

namespace App\Listeners;

use App\Events\VisitRegistered;
use App\Models\Visit;
use App\Services\WhatsAppMessageService;
use App\Support\Tenancy\TenantContext;

class SendVisitNotification
{
    public function __construct(
        private readonly WhatsAppMessageService $messages,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(VisitRegistered $event): void
    {
        $visit = Visit::withoutGlobalScope('business')->findOrFail($event->visitId);
        $previousBusinessId = $this->tenant->id();
        $this->tenant->set($visit->business_id);

        try {
            $this->messages->notifyVisit($visit, $event->previousLevel, $event->unlockedRewardIds);
        } finally {
            $this->tenant->set($previousBusinessId);
        }
    }
}
