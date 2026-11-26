<?php

namespace App\Services\Flows;

use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowSession;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\CloudApiException;
use Illuminate\Support\Facades\Log;

/**
 * Runs chatbot flows on incoming WhatsApp messages.
 * A flow starts when a customer sends a trigger keyword; then each reply moves them along the branches.
 */
class FlowEngine
{
    /** Steps sent in a row without waiting, to stop accidental loops. */
    private const MAX_STEPS = 10;

    private const SESSION_HOURS = 24;

    /** @return bool true when a flow handled this message */
    public function handle(WhatsappAccount $account, Contact $contact, array $incoming, string $text): bool
    {
        $session = FlowSession::withoutGlobalScopes()
            ->where('contact_id', $contact->id)->where('status', 'active')
            ->latest('id')->first();

        if ($session && $session->last_interaction_at?->lt(now()->subHours(self::SESSION_HOURS))) {
            $session->update(['status' => 'expired']);
            $session = null;
        }

        // A trigger keyword always (re)starts its flow, even in the middle of another one.
        $flow = $this->findTriggeredFlow($account, $text);
        if ($flow) {
            $session?->update(['status' => 'expired']);
            $session = FlowSession::withoutGlobalScopes()->create([
                'workspace_id' => $account->workspace_id,
                'flow_id' => $flow->id,
                'contact_id' => $contact->id,
                'whatsapp_account_id' => $account->id,
                'answers' => [],
                'last_interaction_at' => now(),
            ]);
            $flow->increment('triggered_count');
            $this->run($session, $flow, $flow->definition['start'] ?? null, $account, $contact);

            return true;
        }

        if (! $session) {
            return false;
        }

        $flow = Flow::withoutGlobalScopes()->find($session->flow_id);
        $node = $flow?->node($session->current_node);
        if (! $flow || $flow->status !== 'active' || ! $node) {
            $session->update(['status' => 'expired']);

            return false;
        }

        $session->last_interaction_at = now();
        $answers = $session->answers ?? [];

        if (in_array($node['type'], ['buttons', 'list'], true)) {
            $choice = $this->matchOption($node, $incoming, $text);
            if ($choice === null) {
                // Not one of the options: ask again.
                $this->send($account, $contact, $node, 'Please choose one of the options below.');
                $session->save();

                return true;
            }
            $answers[$session->current_node] = $node['options'][$choice]['title'];
            $session->answers = $answers;
            $this->run($session, $flow, $node['options'][$choice]['next'] ?? null, $account, $contact);

            return true;
        }

        if ($node['type'] === 'question') {
            $answers[$session->current_node] = $text;
            $session->answers = $answers;
            $key = $node['save_as'] ?? null;
            if ($key) {
                $contact->fields = array_merge($contact->fields ?? [], [$key => $text]);
                $contact->save();
            }
            $this->run($session, $flow, $node['next'] ?? null, $account, $contact);

            return true;
        }

        return false;
    }

    private function findTriggeredFlow(WhatsappAccount $account, string $text): ?Flow
    {
        if (trim($text) === '') {
            return null;
        }

        return Flow::withoutGlobalScopes()
            ->where('workspace_id', $account->workspace_id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('whatsapp_account_id')->orWhere('whatsapp_account_id', $account->id))
            ->orderBy('id')->get()
            ->first(fn (Flow $f) => $f->matches($text));
    }

    /** Index of the chosen option from a button/list reply id, or from typed text (title or number). */
    private function matchOption(array $node, array $incoming, string $text): ?int
    {
        $replyId = $incoming['interactive']['button_reply']['id'] ?? $incoming['interactive']['list_reply']['id'] ?? null;
        if ($replyId && preg_match('/:opt(\d+)$/', $replyId, $m) && isset($node['options'][(int) $m[1]])) {
            return (int) $m[1];
        }

        $typed = mb_strtolower(trim($text));
        foreach ($node['options'] as $i => $o) {
            if (mb_strtolower(trim($o['title'] ?? '')) === $typed || (string) ($i + 1) === $typed) {
                return $i;
            }
        }

        return null;
    }

    /** Send steps starting at $nodeId until one needs an answer or the flow ends. */
    private function run(FlowSession $session, Flow $flow, ?string $nodeId, WhatsappAccount $account, Contact $contact): void
    {
        for ($i = 0; $i < self::MAX_STEPS; $i++) {
            $node = $flow->node($nodeId);
            if (! $node) {
                $this->finish($session, $flow);

                return;
            }

            $this->send($account, $contact, $node + ['id' => $nodeId]);
            $session->current_node = $nodeId;

            if (in_array($node['type'], ['buttons', 'list', 'question'], true)) {
                $session->save(); // wait for the customer's answer

                return;
            }
            if (in_array($node['type'], ['end', 'handoff'], true)) {
                $this->finish($session, $flow);

                return;
            }
            $nodeId = $node['next'] ?? null; // plain message: continue
        }

        $this->finish($session, $flow);
    }

    private function finish(FlowSession $session, Flow $flow): void
    {
        $session->status = 'completed';
        $session->save();
        $flow->increment('completed_count');
    }

    private function send(WhatsappAccount $account, Contact $contact, array $node, ?string $prefix = null): void
    {
        $text = trim(($prefix ? $prefix."\n\n" : '').$node['text']);
        $id = $node['id'] ?? 'node';
        $api = $account->api();

        try {
            $result = match ($node['type']) {
                'buttons' => $api->sendButtons($account->phone_number_id, $contact->phone, $text,
                    collect($node['options'])->map(fn ($o, $i) => ["{$id}:opt{$i}", $o['title']])->all()),
                'list' => $api->sendList($account->phone_number_id, $contact->phone, $text, $node['button'] ?: 'Choose',
                    collect($node['options'])->map(fn ($o, $i) => ["{$id}:opt{$i}", $o['title'], $o['description'] ?? null])->all()),
                default => $api->sendText($account->phone_number_id, $contact->phone, $text),
            };
        } catch (CloudApiException $e) {
            Log::warning('Flow message failed', ['contact' => $contact->id, 'error' => $e->getMessage()]);
            $result = null;
        }

        $options = in_array($node['type'], ['buttons', 'list'], true)
            ? "\n".collect($node['options'])->map(fn ($o) => '['.$o['title'].']')->join(' ')
            : '';

        Message::withoutGlobalScopes()->create([
            'workspace_id' => $account->workspace_id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'direction' => 'out',
            'wamid' => $result['messages'][0]['id'] ?? null,
            'phone' => $contact->phone,
            'type' => in_array($node['type'], ['buttons', 'list'], true) ? 'interactive' : 'text',
            'body' => $text.$options,
            'status' => $result ? 'sent' : 'failed',
            'error_message' => $result ? null : 'Flow message could not be sent',
            'sent_at' => $result ? now() : null,
            'failed_at' => $result ? null : now(),
        ]);
    }
}
