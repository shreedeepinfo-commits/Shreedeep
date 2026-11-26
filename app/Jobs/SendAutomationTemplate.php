<?php

namespace App\Jobs;

use App\Models\Automation;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Services\Campaigns\MessageBuilder;
use App\Services\WhatsApp\CloudApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

/** Sends one template message for a drip step or a date reminder. */
class SendAutomationTemplate implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    /** @param array{template_id:int, variables?:array, header_media_url?:?string} $step */
    public function __construct(public int $automationId, public int $contactId, public array $step)
    {
        $this->onQueue(config('whatsapp.send_queue'));
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function middleware(): array
    {
        return [(new RateLimited('whatsapp-send'))->releaseAfter(1)];
    }

    public function handle(): void
    {
        $automation = Automation::withoutGlobalScopes()->find($this->automationId);
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);
        $template = MessageTemplate::withoutGlobalScopes()->find($this->step['template_id'] ?? null);
        if (! $automation?->active || ! $contact || ! $template || $template->workspace_id !== $automation->workspace_id) {
            return;
        }
        if ($contact->opted_out_at || $contact->blocked_at) {
            return; // never message people who said stop or are blacklisted
        }
        $account = $template->whatsappAccount()->withoutGlobalScopes()->first();
        if (! $account) {
            return;
        }

        $built = MessageBuilder::build($template, new Campaign([
            'variables' => $this->step['variables'] ?? [],
            'header_media_url' => $this->step['header_media_url'] ?? null,
        ]), $contact);

        $message = new Message([
            'workspace_id' => $automation->workspace_id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'direction' => 'out',
            'phone' => $contact->phone,
            'type' => 'template',
            'body' => $template->render($built['body_values']),
            'payload' => ['template' => $template->name, 'components' => $built['components'], 'automation_id' => $automation->id],
        ]);

        try {
            $result = $account->api()->sendTemplate($account->phone_number_id, $contact->phone, $template->name, $template->language, $built['components']);
            $message->fill(['status' => 'sent', 'wamid' => $result['messages'][0]['id'] ?? null, 'sent_at' => now()])->save();
            $automation->increment('sent_count');
            $contact->forceFill(['last_message_at' => now()])->save();
        } catch (CloudApiException $e) {
            if ($e->isRateLimit()) {
                $this->release(30);

                return;
            }
            $message->fill([
                'status' => 'failed', 'error_code' => (string) $e->metaCode, 'error_message' => $e->getMessage(), 'failed_at' => now(),
            ])->save();
            Log::info('Automation message failed', ['automation' => $automation->id, 'contact' => $contact->id, 'error' => $e->getMessage()]);
        }
    }
}
