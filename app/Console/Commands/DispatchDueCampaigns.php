<?php

namespace App\Console\Commands;

use App\Jobs\StartCampaign;
use App\Models\Campaign;
use Illuminate\Console\Command;

class DispatchDueCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-due';

    protected $description = 'Start scheduled campaigns whose time has come';

    public function handle(): void
    {
        Campaign::withoutGlobalScopes()->where('status', 'scheduled')->where('scheduled_at', '<=', now())
            ->each(function (Campaign $campaign) {
                $campaign->update(['status' => 'running']);
                StartCampaign::dispatch($campaign->id);
                $this->info("Started campaign {$campaign->id}");
            });
    }
}
