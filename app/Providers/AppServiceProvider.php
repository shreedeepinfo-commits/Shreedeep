<?php

namespace App\Providers;

use App\Jobs\SendAutomationTemplate;
use App\Jobs\SendCampaignMessage;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Support\CurrentWorkspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspace::class);
        // Tests run many requests in one process; reset per-request view data.
        $this->app->terminating(fn () => \App\View\Composers\TopBarComposer::flush());
    }

    public function boot(): void
    {
        View::composer('layouts.app', \App\View\Composers\TopBarComposer::class);

        // Campaign sends are throttled per WhatsApp number (whatsapp_account_id).
        RateLimiter::for('whatsapp-send', function (SendCampaignMessage|SendAutomationTemplate $job) {
            $accountId = $job instanceof SendCampaignMessage
                ? Message::withoutGlobalScopes()->whereKey($job->messageId)->value('whatsapp_account_id')
                : MessageTemplate::withoutGlobalScopes()->whereKey($job->step['template_id'] ?? 0)->value('whatsapp_account_id');

            return Limit::perSecond(max(1, config('whatsapp.messages_per_second')))->by('wa-send:'.$accountId);
        });
    }
}
