<?php

namespace App\Services\Automations;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\CloudApiException;
use Illuminate\Support\Facades\Log;

/**
 * Instant automatic replies on incoming messages: welcome, keyword and away (business hours).
 * Scheduled automations (drip, date reminders) run from the automations:run command.
 */
class AutomationEngine
{
    /** Away replies go out at most once per contact in this many hours. */
    public const AWAY_EVERY_HOURS = 12;

    /** @return int number of automatic replies sent */
    public function handleIncoming(WhatsappAccount $account, Contact $contact, string $text, bool $firstMessage): int
    {
        if ($contact->blocked_at) {
            return 0;
        }

        $automations = Automation::withoutGlobalScopes()
            ->where('workspace_id', $account->workspace_id)
            ->where('active', true)->whereIn('type', ['welcome', 'keyword', 'away'])
            ->orderBy('id')->get()->groupBy('type');
        $sent = 0;

        $welcome = $automations->get('welcome')?->first();
        if ($firstMessage && $welcome && $welcome->claim($contact->id, 'welcome')) {
            $sent += $this->reply($account, $contact, $welcome, $welcome->config['message'] ?? '');
        }

        $keyword = $automations->get('keyword')?->first(fn (Automation $a) => self::keywordMatches($a, $text));
        if ($keyword) {
            return $sent + $this->reply($account, $contact, $keyword, $keyword->config['reply'] ?? '');
        }

        $away = $automations->get('away')?->first();
        if ($away && ! $away->isOpenAt(now())) {
            $slot = intdiv(now()->timestamp, self::AWAY_EVERY_HOURS * 3600);
            if ($away->claim($contact->id, 'away:'.$slot)) {
                $sent += $this->reply($account, $contact, $away, $away->config['message'] ?? '');
            }
        }

        return $sent;
    }

    public static function keywordMatches(Automation $automation, string $text): bool
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return false;
        }
        foreach ($automation->config['keywords'] ?? [] as $word) {
            $word = mb_strtolower(trim((string) $word));
            if ($word === '') {
                continue;
            }
            $hit = ($automation->config['match'] ?? 'contains') === 'exact'
                ? $text === $word
                : (bool) preg_match('/(^|[^\p{L}\p{N}])'.preg_quote($word, '/').'($|[^\p{L}\p{N}])/u', $text);
            if ($hit) {
                return true;
            }
        }

        return false;
    }

    /** Fill {{name}} / {{phone}} in a reply. */
    public static function personalise(string $text, Contact $contact): string
    {
        return strtr($text, [
            '{{name}}' => $contact->name ?: 'there',
            '{{phone}}' => (string) $contact->phone,
        ]);
    }

    private function reply(WhatsappAccount $account, Contact $contact, Automation $automation, string $text): int
    {
        $text = trim(self::personalise($text, $contact));
        if ($text === '') {
            return 0;
        }

        try {
            $result = $account->api()->sendText($account->phone_number_id, $contact->phone, $text);
        } catch (CloudApiException $e) {
            Log::warning('Automation reply failed', ['automation' => $automation->id, 'contact' => $contact->id, 'error' => $e->getMessage()]);
            $result = null;
        }

        Message::withoutGlobalScopes()->create([
            'workspace_id' => $account->workspace_id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'direction' => 'out',
            'wamid' => $result['messages'][0]['id'] ?? null,
            'phone' => $contact->phone,
            'type' => 'text',
            'body' => $text,
            'payload' => ['automation_id' => $automation->id],
            'status' => $result ? 'sent' : 'failed',
            'error_message' => $result ? null : 'Automatic reply could not be sent',
            'sent_at' => $result ? now() : null,
            'failed_at' => $result ? null : now(),
        ]);

        if (! $result) {
            return 0;
        }
        $automation->increment('sent_count');

        return 1;
    }
}
