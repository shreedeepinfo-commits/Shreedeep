<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Campaigns\CampaignLauncher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartCampaign implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public int $campaignId)
    {
        $this->onQueue(config('whatsapp.send_queue'));
    }

    public function handle(CampaignLauncher $launcher): void
    {
        $campaign = Campaign::withoutGlobalScopes()->find($this->campaignId);
        if ($campaign && in_array($campaign->status, ['scheduled', 'running'], true)) {
            $launcher->start($campaign);
        }
    }
}
