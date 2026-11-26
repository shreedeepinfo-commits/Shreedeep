<?php

namespace App\Providers;

use App\Jobs\SendCampaignMessage;
use App\Models\Message;
use App\Support\CurrentWorkspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspace::class);
    }

    public function boot(): void
    {
        // Campaign sends are throttled per WhatsApp number (whatsapp_account_id).
        RateLimiter::for('whatsapp-send', function (SendCampaignMessage $job) {
            $accountId = Message::withoutGlobalScopes()->whereKey($job->messageId)->value('whatsapp_account_id');

            return Limit::perSecond(max(1, config('whatsapp.messages_per_second')))->by('wa-send:'.$accountId);
        });
    }
}
