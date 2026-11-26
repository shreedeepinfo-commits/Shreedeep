<?php

namespace App\Jobs;

use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Workspace;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\WebhookEvent;
use App\Models\WhatsappAccount;
use App\Services\Flows\FlowEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class ProcessWhatsappWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [5, 30, 120];

    private const OPT_OUT_WORDS = ['stop', 'unsubscribe', 'band karo', 'बंद करो', 'बंद'];

    private const OPT_IN_WORDS = ['start', 'subscribe'];

    public function __construct(public int $eventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $event = WebhookEvent::find($this->eventId);
        if (! $event || $event->processed_at) {
            return;
        }

        try {
            foreach ($event->payload['entry'] ?? [] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    $value = $change['value'] ?? [];
                    match ($change['field'] ?? null) {
                        'messages' => $this->handleMessages($value),
                        'message_template_status_update' => $this->handleTemplateStatus($value),
                        'phone_number_quality_update' => $this->handleQuality($value),
                        default => null,
                    };
                }
            }
            $event->update(['processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $event->update(['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function handleMessages(array $value): void
    {
        $account = WhatsappAccount::withoutGlobalScopes()->where('phone_number_id', $value['metadata']['phone_number_id'] ?? null)->first();
        if (! $account) {
            return;
        }

        foreach ($value['statuses'] ?? [] as $status) {
            $this->applyStatus($status);
        }

        $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [$c['wa_id'] ?? '' => $c['profile']['name'] ?? null]);
        foreach ($value['messages'] ?? [] as $incoming) {
            $this->storeIncoming($account, $incoming, $names[$incoming['from'] ?? ''] ?? null);
        }
    }

    private function applyStatus(array $status): void
    {
        $message = Message::withoutGlobalScopes()->where('wamid', $status['id'] ?? null)->first();
        $new = $status['status'] ?? null;
        if (! $message || ! isset(Message::STATUS_RANK[$new])) {
            return;
        }

        $at = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp']) : now();
        $updates = [];
        if ($new === 'failed') {
            if ($message->status === 'read') {
                return;
            }
            $error = $status['errors'][0] ?? [];
            $updates = [
                'status' => 'failed',
                'failed_at' => $at,
                'error_code' => isset($error['code']) ? (string) $error['code'] : null,
                'error_message' => $error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? 'Failed',
            ];
        } else {
            // Statuses can arrive out of order; only move forward, but always keep timestamps.
            $updates[$new.'_at'] = $message->{$new.'_at'} ?? $at;
            if ($new === 'read' && ! $message->delivered_at) {
                $updates['delivered_at'] = $at;
            }
            if (Message::STATUS_RANK[$new] > (Message::STATUS_RANK[$message->status] ?? 0) && $message->status !== 'failed') {
                $updates['status'] = $new;
            }
        }

        $message->update($updates);
        MessageStatusUpdated::dispatch($message);
    }

    private function storeIncoming(WhatsappAccount $account, array $in, ?string $profileName): void
    {
        if (Message::withoutGlobalScopes()->where('wamid', $in['id'] ?? null)->exists()) {
            return; // Meta redelivered it
        }

        $phone = (string) ($in['from'] ?? '');
        $contact = Contact::withoutGlobalScopes()->firstOrCreate(
            ['workspace_id' => $account->workspace_id, 'phone' => $phone],
            ['name' => $profileName, 'opt_in_source' => 'incoming message', 'opted_in_at' => now()],
        );

        $type = $in['type'] ?? 'unknown';
        $body = match ($type) {
            'text' => $in['text']['body'] ?? '',
            'button' => $in['button']['text'] ?? '',
            'interactive' => $in['interactive']['button_reply']['title'] ?? $in['interactive']['list_reply']['title'] ?? '',
            'image', 'video', 'document', 'audio', 'sticker' => trim('['.$type.'] '.($in[$type]['caption'] ?? $in[$type]['filename'] ?? '')),
            'location' => '[location] '.($in['location']['latitude'] ?? '').','.($in['location']['longitude'] ?? ''),
            'reaction' => '[reaction] '.($in['reaction']['emoji'] ?? ''),
            default => '['.$type.']',
        };

        $keyword = mb_strtolower(trim($body));
        if (in_array($keyword, self::OPT_OUT_WORDS, true)) {
            $contact->opted_out_at = now();
        } elseif (in_array($keyword, self::OPT_IN_WORDS, true)) {
            $contact->opted_out_at = null;
        }
        $sentAt = isset($in['timestamp']) ? Carbon::createFromTimestamp((int) $in['timestamp']) : now();
        $contact->last_message_at = now();
        if (! $contact->last_inbound_at || $sentAt->gt($contact->last_inbound_at)) {
            $contact->last_inbound_at = $sentAt;
        }
        $contact->unread_count = ($contact->unread_count ?? 0) + 1;
        $contact->chat_status = 'open'; // a customer message (re)opens the chat
        if (! $contact->name && $profileName) {
            $contact->name = $profileName;
        }
        $contact->save();

        $message = new Message([
            'workspace_id' => $account->workspace_id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'direction' => 'in',
            'wamid' => $in['id'] ?? null,
            'phone' => $phone,
            'type' => $type,
            'body' => $body,
            'payload' => $in,
            'status' => 'received',
            'media_mime' => $in[$type]['mime_type'] ?? null,
            'media_name' => $in[$type]['filename'] ?? null,
        ]);
        // Use WhatsApp's send time; it decides the 24-hour reply window.
        $message->created_at = $sentAt;
        $message->save();

        $this->autoAssign($account, $contact);

        MessageReceived::dispatch($message);

        // Chatbot flows (not for opt-out/opt-in keywords).
        if (! in_array($keyword, self::OPT_OUT_WORDS, true) && ! in_array($keyword, self::OPT_IN_WORDS, true)) {
            app(FlowEngine::class)->handle($account, $contact, $in, $type === 'text' ? $body : ($in['interactive']['button_reply']['title'] ?? $in['interactive']['list_reply']['title'] ?? $in['button']['text'] ?? ''));
        }
    }

    /** Round-robin: give an unassigned chat to the next agent when the workspace turned it on. */
    private function autoAssign(WhatsappAccount $account, Contact $contact): void
    {
        if ($contact->assigned_user_id) {
            return;
        }
        $workspace = Workspace::find($account->workspace_id);
        if (! $workspace?->auto_assign || ! ($agent = $workspace->nextAgent())) {
            return;
        }

        $contact->forceFill(['assigned_user_id' => $agent->id])->save();
        $workspace->forceFill(['last_assigned_user_id' => $agent->id])->save();
        ContactNote::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id, 'contact_id' => $contact->id, 'body' => "Chat auto-assigned to {$agent->name}", 'system' => true,
        ]);
    }

    private function handleTemplateStatus(array $value): void
    {
        $query = MessageTemplate::withoutGlobalScopes()->where('meta_template_id', (string) ($value['message_template_id'] ?? ''));
        if (! empty($value['event'])) {
            $reason = $value['reason'] ?? null;
            $query->update([
                'status' => $value['event'],
                'rejected_reason' => $reason && $reason !== 'NONE' ? $reason : null,
            ]);
        }
    }

    private function handleQuality(array $value): void
    {
        $digits = preg_replace('/\D+/', '', (string) ($value['display_phone_number'] ?? ''));
        if ($digits === '') {
            return;
        }
        WhatsappAccount::withoutGlobalScopes()->get()
            ->filter(fn ($a) => preg_replace('/\D+/', '', (string) $a->display_phone_number) === $digits)
            ->each(fn ($a) => $a->update(array_filter([
                'messaging_limit' => $value['current_limit'] ?? null,
                'last_error' => ($value['event'] ?? '') === 'FLAGGED' ? 'Meta flagged this number for low quality.' : null,
            ])));
    }
}
