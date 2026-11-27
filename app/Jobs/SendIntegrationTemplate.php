<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\IntegrationEvent;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Services\Campaigns\MessageBuilder;
use App\Services\WhatsApp\CloudApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Str;

/** Sends the WhatsApp order update for one Shopify / WooCommerce order event. */
class SendIntegrationTemplate implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    /** @param array{template_id:int, variables?:array} $step */
    public function __construct(public int $eventId, public int $contactId, public array $step)
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
        $event = IntegrationEvent::withoutGlobalScopes()->find($this->eventId);
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);
        $template = MessageTemplate::withoutGlobalScopes()->find($this->step['template_id'] ?? null);
        if (! $event || $event->status !== 'queued') {
            return;
        }
        if (! $contact || ! $template || $template->workspace_id !== $event->workspace_id || $contact->workspace_id !== $event->workspace_id) {
            $event->update(['status' => 'skipped', 'note' => 'Template or contact was deleted.']);

            return;
        }
        if ($contact->opted_out_at || $contact->blocked_at) {
            $event->update(['status' => 'skipped', 'note' => 'Customer has opted out of messages.']);

            return;
        }
        $account = $template->whatsappAccount()->withoutGlobalScopes()->first();
        if (! $account) {
            $event->update(['status' => 'skipped', 'note' => 'WhatsApp number is not connected.']);

            return;
        }

        $built = MessageBuilder::build($template, new Campaign(['variables' => $this->step['variables'] ?? []]), $contact);
        $message = new Message([
            'workspace_id' => $event->workspace_id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'direction' => 'out',
            'phone' => $contact->phone,
            'type' => 'template',
            'body' => $template->render($built['body_values']),
            'payload' => ['template' => $template->name, 'components' => $built['components'], 'integration_event_id' => $event->id],
        ]);

        try {
            $result = $account->api()->sendTemplate($account->phone_number_id, $contact->phone, $template->name, $template->language, $built['components']);
            $message->fill(['status' => 'sent', 'wamid' => $result['messages'][0]['id'] ?? null, 'sent_at' => now()])->save();
            $contact->forceFill(['last_message_at' => now()])->save();
            $event->update(['status' => 'sent', 'note' => 'Sent template '.$template->name]);
        } catch (CloudApiException $e) {
            if ($e->isRateLimit()) {
                $this->release(30);

                return;
            }
            $message->fill([
                'status' => 'failed', 'error_code' => (string) $e->metaCode, 'error_message' => $e->getMessage(), 'failed_at' => now(),
            ])->save();
            $event->update(['status' => 'failed', 'note' => Str::limit($e->getMessage(), 250)]);
        }
    }
}
