<?php

namespace App\Services\WhatsApp;

use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;

class TemplateService
{
    private const FIELDS = 'id,name,language,status,category,components,rejected_reason';

    /** Pull all templates of the account's WABA from Meta. Returns how many were synced. */
    public function sync(WhatsappAccount $account): int
    {
        $api = $account->api();
        $seen = [];
        $after = null;

        do {
            $query = ['fields' => self::FIELDS, 'limit' => 100];
            if ($after) {
                $query['after'] = $after;
            }
            $page = $api->get("{$account->waba_id}/message_templates", $query);

            foreach ($page['data'] ?? [] as $t) {
                $template = MessageTemplate::withoutGlobalScopes()->updateOrCreate(
                    ['whatsapp_account_id' => $account->id, 'name' => $t['name'], 'language' => $t['language']],
                    [
                        'workspace_id' => $account->workspace_id,
                        'meta_template_id' => $t['id'] ?? null,
                        'category' => $t['category'] ?? 'MARKETING',
                        'status' => $t['status'] ?? 'PENDING',
                        'components' => $t['components'] ?? [],
                        'rejected_reason' => ($t['rejected_reason'] ?? 'NONE') === 'NONE' ? null : $t['rejected_reason'],
                        'synced_at' => now(),
                    ],
                );
                $seen[] = $template->id;
            }

            $after = isset($page['paging']['next']) ? ($page['paging']['cursors']['after'] ?? null) : null;
        } while ($after);

        // Templates deleted in Meta disappear here too.
        MessageTemplate::withoutGlobalScopes()->where('whatsapp_account_id', $account->id)->whereNotIn('id', $seen)->delete();

        return count($seen);
    }

    /**
     * Submit a new template for Meta approval.
     *
     * @param  array{name:string, language:string, category:string, header?:?string, body:string, footer?:?string, body_examples?:array, header_example?:?string, buttons?:array}  $data
     */
    public function create(WhatsappAccount $account, array $data): MessageTemplate
    {
        $components = self::buildComponents($data);

        $result = $account->api()->post("{$account->waba_id}/message_templates", [
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => $data['category'],
            'components' => $components,
        ]);

        return MessageTemplate::withoutGlobalScopes()->updateOrCreate(
            ['whatsapp_account_id' => $account->id, 'name' => $data['name'], 'language' => $data['language']],
            [
                'workspace_id' => $account->workspace_id,
                'meta_template_id' => $result['id'] ?? null,
                'category' => $result['category'] ?? $data['category'],
                'status' => $result['status'] ?? 'PENDING',
                'components' => $components,
                'synced_at' => now(),
            ],
        );
    }

    public function delete(MessageTemplate $template): void
    {
        $account = $template->whatsappAccount;
        $query = ['name' => $template->name];
        if ($template->meta_template_id) {
            $query['hsm_id'] = $template->meta_template_id;
        }
        $account->api()->delete("{$account->waba_id}/message_templates", $query);
        $template->delete();
    }

    public static function buildComponents(array $data): array
    {
        $components = [];

        if (! empty($data['header'])) {
            $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $data['header']];
            if (str_contains($data['header'], '{{1}}')) {
                $header['example'] = ['header_text' => [$data['header_example'] ?? 'Example']];
            }
            $components[] = $header;
        }

        $body = ['type' => 'BODY', 'text' => $data['body']];
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $data['body'], $m);
        if ($m[1]) {
            $count = max(array_map('intval', $m[1]));
            $examples = array_values($data['body_examples'] ?? []);
            $body['example'] = ['body_text' => [array_map(fn ($i) => $examples[$i] ?? 'Sample '.($i + 1), range(0, $count - 1))]];
        }
        $components[] = $body;

        if (! empty($data['footer'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $data['footer']];
        }

        $buttons = [];
        foreach ($data['buttons'] ?? [] as $b) {
            if (empty($b['text'])) {
                continue;
            }
            $buttons[] = match ($b['type'] ?? 'QUICK_REPLY') {
                'URL' => ['type' => 'URL', 'text' => $b['text'], 'url' => $b['value']],
                'PHONE_NUMBER' => ['type' => 'PHONE_NUMBER', 'text' => $b['text'], 'phone_number' => $b['value']],
                default => ['type' => 'QUICK_REPLY', 'text' => $b['text']],
            };
        }
        if ($buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $components;
    }
}
