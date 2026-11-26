<?php

namespace App\Services\WhatsApp;

use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\Http;

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

    /** Resubmit an edited template (Meta allows edits to approved, rejected and paused templates). */
    public function update(MessageTemplate $template, array $data): MessageTemplate
    {
        $components = self::buildComponents($data + ['category' => $template->category]);
        $template->whatsappAccount->api()->post($template->meta_template_id ?: throw new CloudApiException('Sync templates first: this one has no Meta id.'), [
            'components' => $components,
        ]);
        $template->update(['components' => $components, 'status' => 'PENDING', 'rejected_reason' => null, 'synced_at' => now()]);

        return $template;
    }

    /**
     * Upload a sample image/video/document for a media header (Meta resumable upload API).
     * Returns the handle Meta expects in the template example.
     */
    public function uploadSample(WhatsappAccount $account, string $path, string $mime, string $filename): string
    {
        $appId = config('whatsapp.app_id') ?: throw new CloudApiException('Set META_APP_ID in .env to upload header samples.');
        $session = $account->api()->post("{$appId}/uploads", [
            'file_name' => $filename, 'file_length' => filesize($path), 'file_type' => $mime,
        ]);
        $response = Http::withHeaders(['Authorization' => 'OAuth '.$account->access_token, 'file_offset' => '0'])
            ->withBody(file_get_contents($path), $mime)->timeout(120)
            ->post(CloudApi::baseUrl().'/'.($session['id'] ?? throw new CloudApiException('Meta did not start the upload.')));
        if ($response->failed() || ! $response->json('h')) {
            throw new CloudApiException($response->json('error.message') ?? 'Could not upload the sample file to Meta.');
        }

        return $response->json('h');
    }

    /** Send an approved template to one number to check how it looks. $values fill {{1}}, {{2}}…; $headerValue is header text or a media link. */
    public function sendTest(MessageTemplate $template, string $phone, array $values, ?string $headerValue): array
    {
        $account = $template->whatsappAccount;
        if ($template->category === 'AUTHENTICATION') {
            // OTP: the code goes in the body and in the copy-code button.
            $code = (string) (($values[0] ?? '') !== '' ? $values[0] : '123456');

            return $account->api()->sendTemplate($account->phone_number_id, $phone, $template->name, $template->language, [
                ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
            ]);
        }

        $components = [];
        $format = $template->headerFormat();
        if (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $key = strtolower($format);
            $components[] = ['type' => 'header', 'parameters' => [['type' => $key, $key => ['link' => $headerValue]]]];
        } elseif ($template->headerVariableCount() > 0) {
            $components[] = ['type' => 'header', 'parameters' => [['type' => 'text', 'text' => $headerValue ?: 'Test']]];
        }
        if ($template->bodyVariableCount() > 0) {
            $components[] = ['type' => 'body', 'parameters' => array_map(
                fn ($i) => ['type' => 'text', 'text' => (string) (($values[$i] ?? '') !== '' ? $values[$i] : 'Test '.($i + 1))],
                range(0, $template->bodyVariableCount() - 1),
            )];
        }
        foreach ($template->component('BUTTONS')['buttons'] ?? [] as $i => $button) {
            if ($button['type'] === 'URL' && str_contains($button['url'] ?? '', '{{1}}')) {
                $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => (string) $i, 'parameters' => [['type' => 'text', 'text' => (string) ($button['example'][0] ?? 'test')]]];
            }
            if ($button['type'] === 'COPY_CODE') {
                $components[] = ['type' => 'button', 'sub_type' => 'copy_code', 'index' => (string) $i, 'parameters' => [['type' => 'coupon_code', 'coupon_code' => (string) ($button['example'] ?? 'CODE')]]];
            }
        }

        return $account->api()->sendTemplate($account->phone_number_id, $phone, $template->name, $template->language, $components);
    }

    /** Turn a template's components back into form fields (for edit and duplicate). */
    public static function formData(MessageTemplate $template): array
    {
        $header = $template->component('HEADER');
        $body = $template->component('BODY');
        $footer = $template->component('FOOTER');

        return [
            'whatsapp_account_id' => $template->whatsapp_account_id,
            'name' => $template->name,
            'language' => $template->language,
            'category' => $template->category,
            'header_type' => $header ? strtolower($header['format'] ?? 'text') : 'none',
            'header' => $header['text'] ?? null,
            'header_example' => $header['example']['header_text'][0] ?? null,
            'header_handle' => $header['example']['header_handle'][0] ?? null,
            'body' => $body['text'] ?? '',
            'body_examples' => $body['example']['body_text'][0] ?? [],
            'footer' => $footer['text'] ?? null,
            'security_recommendation' => (bool) ($body['add_security_recommendation'] ?? false),
            'code_expiration_minutes' => $footer['code_expiration_minutes'] ?? null,
            'buttons' => array_map(fn ($b) => [
                'type' => $b['type'],
                'text' => $b['text'] ?? '',
                'value' => $b['url'] ?? $b['phone_number'] ?? (is_string($b['example'] ?? null) ? $b['example'] : ''),
                'example' => is_array($b['example'] ?? null) ? ($b['example'][0] ?? '') : '',
            ], $template->component('BUTTONS')['buttons'] ?? []),
        ];
    }

    public static function buildComponents(array $data): array
    {
        // Authentication (OTP) templates have a fixed body written by Meta.
        if (($data['category'] ?? null) === 'AUTHENTICATION') {
            $components = [['type' => 'BODY', 'add_security_recommendation' => (bool) ($data['security_recommendation'] ?? false)]];
            if (! empty($data['code_expiration_minutes'])) {
                $components[] = ['type' => 'FOOTER', 'code_expiration_minutes' => (int) $data['code_expiration_minutes']];
            }
            $components[] = ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => $data['otp_button_text'] ?? 'Copy code']]];

            return $components;
        }

        $components = [];
        $headerType = $data['header_type'] ?? (! empty($data['header']) ? 'text' : 'none');

        if ($headerType === 'text' && ! empty($data['header'])) {
            $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $data['header']];
            if (str_contains($data['header'], '{{1}}')) {
                $header['example'] = ['header_text' => [$data['header_example'] ?? 'Example']];
            }
            $components[] = $header;
        } elseif (in_array($headerType, ['image', 'video', 'document'], true) && ! empty($data['header_handle'])) {
            $components[] = ['type' => 'HEADER', 'format' => strtoupper($headerType), 'example' => ['header_handle' => [$data['header_handle']]]];
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
            if (empty($b['text']) && ($b['type'] ?? null) !== 'COPY_CODE') {
                continue;
            }
            $buttons[] = match ($b['type'] ?? 'QUICK_REPLY') {
                'URL' => array_filter([
                    'type' => 'URL', 'text' => $b['text'], 'url' => $b['value'],
                    'example' => str_contains((string) $b['value'], '{{1}}') ? [$b['example'] ?: 'example'] : null,
                ]),
                'PHONE_NUMBER' => ['type' => 'PHONE_NUMBER', 'text' => $b['text'], 'phone_number' => '+'.ltrim(preg_replace('/[^\d+]/', '', (string) $b['value']), '+')],
                'COPY_CODE' => ['type' => 'COPY_CODE', 'example' => $b['value']],
                default => ['type' => 'QUICK_REPLY', 'text' => $b['text']],
            };
        }
        if ($buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $components;
    }
}
