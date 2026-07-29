<?php

namespace App\Console\Commands;

use App\Services\CampaignDispatcher;
use Illuminate\Console\Command;

class DispatchCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch';

    protected $description = 'Encola las campañas confirmadas y programadas que ya deben procesarse';

    public function handle(CampaignDispatcher $dispatcher): int
    {
        $count = $dispatcher->dispatchDue();
        $this->info("Campañas encoladas: {$count}");

        return self::SUCCESS;
    }
}
