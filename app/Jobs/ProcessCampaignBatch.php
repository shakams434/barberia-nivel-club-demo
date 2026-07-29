<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Consent;
use App\Models\WhatsAppMessage;
use App\Services\ConsentService;
use App\Services\WhatsAppMessageService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessCampaignBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $campaignId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('campaign-batch:'.$this->campaignId))->expireAfter(290)];
    }

    public function handle(
        ConsentService $consents,
        WhatsAppMessageService $messages,
        TenantContext $tenant,
    ): void {
        $campaign = Campaign::withoutGlobalScope('business')->with(['template', 'recipients.customer'])->findOrFail($this->campaignId);
        $tenant->set($campaign->business_id);

        try {
            if ($campaign->status !== 'processing') {
                return;
            }

            $program = $campaign->business->loyaltyProgram;
            $recipients = $campaign->recipients()
                ->with('customer.tier')
                ->where('status', 'queued')
                ->limit($program->campaign_batch_size)
                ->get();

            foreach ($recipients as $recipient) {
                $customer = $recipient->customer;

                if (! $customer || $customer->status !== 'active' || ! $consents->hasActive($customer, Consent::MARKETING)) {
                    $recipient->update(['status' => 'opt_out', 'exclusion_reason' => 'marketing_consent', 'processed_at' => now()]);

                    continue;
                }

                $recentPromotions = WhatsAppMessage::where('customer_id', $customer->id)
                    ->whereNotNull('campaign_recipient_id')
                    ->whereIn('status', ['sent', 'delivered', 'read'])
                    ->where('sent_at', '>=', now()->subDays($program->marketing_frequency_days))
                    ->count();

                if ($recentPromotions >= $program->marketing_frequency_limit) {
                    $recipient->update(['status' => 'excluded', 'exclusion_reason' => 'frequency_limit', 'processed_at' => now()]);

                    continue;
                }

                $variables = collect($campaign->variables ?? [])
                    ->map(fn ($value) => str_replace(
                        ['{customer_name}', '{level}', '{tier}'],
                        [$customer->name, (string) $customer->level, $customer->tier?->name ?? 'Bronce'],
                        (string) $value,
                    ))->values()->all();

                $message = $messages->queue(
                    $customer,
                    $campaign->template,
                    $variables,
                    "campaign:{$campaign->id}:customer:{$customer->id}",
                    campaignRecipientId: $recipient->id,
                );
                $recipient->update(['status' => 'submitted', 'processed_at' => now()]);
                SendWhatsAppMessage::dispatch($message->id)->onQueue('messages');
            }

            if ($campaign->recipients()->where('status', 'queued')->exists()) {
                $campaign->update(['status' => 'queued']);
            } else {
                $campaign->update(['status' => 'completed', 'completed_at' => now()]);
            }
        } finally {
            $tenant->clear();
        }
    }
}
