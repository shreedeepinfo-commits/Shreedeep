<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Message;
use App\Services\Campaigns\CampaignLauncher;
use App\Services\Campaigns\MessageBuilder;
use App\Services\WhatsApp\CloudApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

class SendCampaignMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 0; // limited by retryUntil instead, because rate-limit releases count as attempts

    public int $maxExceptions = 3;

    public function __construct(public int $messageId)
    {
        $this->onQueue(config('whatsapp.send_queue'));
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    /** Throttle per WhatsApp number (see AppServiceProvider). */
    public function middleware(): array
    {
        return [(new RateLimited('whatsapp-send'))->releaseAfter(1)];
    }

    public function handle(CampaignLauncher $launcher): void
    {
        $message = Message::withoutGlobalScopes()->with(['contact', 'whatsappAccount'])->find($this->messageId);
        if (! $message || $message->status !== 'queued') {
            return;
        }
        $campaign = Campaign::withoutGlobalScopes()->with('template')->find($message->campaign_id);
        if (! $campaign || ! $campaign->isActive()) {
            return; // paused or cancelled: message stays queued until resume
        }

        $template = $campaign->template;
        $built = MessageBuilder::build($template, $campaign, $message->contact);
        $account = $message->whatsappAccount;

        try {
            $result = $account->api()->sendTemplate($account->phone_number_id, $message->phone, $template->name, $template->language, $built['components']);
            $message->update([
                'status' => 'sent',
                'wamid' => $result['messages'][0]['id'] ?? null,
                'body' => $template->render($built['body_values']),
                'payload' => ['template' => $template->name, 'components' => $built['components']],
                'sent_at' => now(),
            ]);
        } catch (CloudApiException $e) {
            if ($e->isRateLimit()) {
                $this->release(30);

                return;
            }
            $message->update([
                'status' => 'failed',
                'error_code' => (string) $e->metaCode,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);
            Log::info('Campaign message failed', ['message_id' => $message->id, 'code' => $e->metaCode, 'error' => $e->getMessage()]);
        }

        $launcher->completeIfDone($campaign);
    }
}
