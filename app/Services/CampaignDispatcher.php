<?php

namespace App\Services;

use App\Jobs\ProcessCampaignBatch;
use App\Models\Campaign;
use Illuminate\Support\Facades\Cache;

class CampaignDispatcher
{
    public function dispatchDue(): int
    {
        Campaign::withoutGlobalScope('business')
            ->where('status', 'processing')
            ->where('updated_at', '<=', now()->subMinutes(10))
            ->whereHas('recipients', fn ($query) => $query->where('status', 'queued'))
            ->update(['status' => 'queued']);

        $campaigns = Campaign::withoutGlobalScope('business')
            ->with('business.loyaltyProgram')
            ->whereIn('status', ['queued', 'scheduled'])
            ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->get();

        $dispatched = 0;
        foreach ($campaigns as $campaign) {
            $program = $campaign->business->loyaltyProgram;
            $localTime = now()->timezone($campaign->business->timezone)->format('H:i');
            if (
                $program
                && ($localTime < $program->campaign_window_start || $localTime >= $program->campaign_window_end)
            ) {
                continue;
            }

            $lock = Cache::lock('campaign-dispatch:'.$campaign->id, 290);
            if (! $lock->get()) {
                continue;
            }

            try {
                $updated = Campaign::withoutGlobalScope('business')
                    ->whereKey($campaign->id)
                    ->whereIn('status', ['queued', 'scheduled'])
                    ->update(['status' => 'processing', 'started_at' => $campaign->started_at ?? now()]);
                if ($updated === 1) {
                    ProcessCampaignBatch::dispatch($campaign->id)->onQueue('campaigns');
                    $dispatched++;
                }
            } finally {
                $lock->release();
            }
        }

        return $dispatched;
    }
}
