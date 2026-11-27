<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use App\Services\Api\Webhooks;
use App\Services\Campaigns\MessageBuilder;
use App\Services\WhatsApp\CloudApiException;
use App\Support\CurrentWorkspace;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Public REST API v1. Every query here is tenant-scoped by the API key's workspace. */
class V1Controller extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    private static function error(string $code, string $message, int $status = 422): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    public function me()
    {
        $w = $this->current->get();

        return [
            'workspace' => ['id' => $w->id, 'name' => $w->name, 'plan' => $w->planName(), 'plan_ends_at' => $w->accessEndsAt()?->toIso8601String()],
            'numbers' => WhatsappAccount::get()->map(fn ($a) => [
                'phone_number_id' => $a->phone_number_id, 'display_phone_number' => $a->display_phone_number,
                'verified_name' => $a->verified_name, 'status' => $a->status, 'quality_rating' => $a->quality_rating,
            ]),
        ];
    }

    public function templates()
    {
        return ['data' => MessageTemplate::approved()->orderBy('name')->get()->map(fn ($t) => [
            'name' => $t->name, 'language' => $t->language, 'category' => $t->category,
            'body' => $t->bodyText(), 'body_variables' => $t->bodyVariableCount(),
            'header' => $t->headerFormat(), 'header_variables' => $t->headerVariableCount(),
        ])];
    }

    public function sendText(Request $request)
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:20'],
            'text' => ['required', 'string', 'max:4096'],
            'from' => ['nullable', 'string'],
        ]);
        [$contact, $account, $fail] = $this->recipient($data['to'], $data['from'] ?? null);
        if ($fail) {
            return $fail;
        }
        if ($contact->windowSecondsLeft() <= 0) {
            return self::error('outside_24h_window', 'This customer has not messaged you in the last 24 hours. Send an approved template instead (POST /messages/template).');
        }

        return $this->send($account, $contact, 'text', $data['text'], [],
            fn () => $account->api()->sendText($account->phone_number_id, $contact->phone, $data['text']));
    }

    public function sendTemplate(Request $request)
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:20'],
            'template' => ['required', 'string', 'max:512'],
            'language' => ['nullable', 'string', 'max:15'],
            'variables' => ['nullable', 'array', 'max:20'],
            'variables.*' => ['nullable', 'string', 'max:1024'],
            'header_text' => ['nullable', 'string', 'max:60'],
            'header_media_url' => ['nullable', 'url', 'max:2000'],
        ]);
        $template = MessageTemplate::approved()->where('name', $data['template'])
            ->when($data['language'] ?? null, fn ($q, $l) => $q->where('language', $l))->first();
        if (! $template) {
            return self::error('template_not_found', 'No approved template with this name'.(isset($data['language']) ? ' and language' : '').'. See GET /templates.', 404);
        }
        if (count($data['variables'] ?? []) < $template->bodyVariableCount()) {
            return self::error('missing_variables', "This template needs {$template->bodyVariableCount()} variables.");
        }

        $account = WhatsappAccount::find($template->whatsapp_account_id);
        [$contact, , $fail] = $this->recipient($data['to'], $account?->phone_number_id);
        if ($fail) {
            return $fail;
        }
        if ($contact->opted_out_at) {
            return self::error('opted_out', 'This customer replied STOP and must not get template messages.');
        }

        $built = MessageBuilder::build($template, new Campaign([
            'variables' => [
                'body' => array_map(fn ($v) => ['type' => 'text', 'value' => (string) $v], array_values($data['variables'] ?? [])),
                'header' => isset($data['header_text']) ? ['type' => 'text', 'value' => $data['header_text']] : null,
            ],
            'header_media_url' => $data['header_media_url'] ?? null,
        ]), $contact);

        return $this->send($account, $contact, 'template', $template->render($built['body_values']), ['template' => $template->name, 'components' => $built['components']],
            fn () => $account->api()->sendTemplate($account->phone_number_id, $contact->phone, $template->name, $template->language, $built['components']));
    }

    public function message(int $id)
    {
        $message = Message::find($id);

        return $message ? ['data' => Webhooks::message($message)] : self::error('not_found', 'Message not found.', 404);
    }

    public function contacts(Request $request)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:500'], 'updated_since' => ['nullable', 'date']]);
        $page = Contact::query()
            ->when($request->query('phone'), fn ($q, $p) => $q->where('phone', preg_replace('/\D+/', '', $p)))
            ->when($request->query('tag'), fn ($q, $t) => $q->where('tags', 'like', '%'.json_encode((string) $t).'%'))
            ->when($request->query('updated_since'), fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('id')->paginate((int) $request->query('per_page', 100));

        return [
            'data' => collect($page->items())->map(fn ($c) => Webhooks::contact($c)),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ];
    }

    public function saveContact(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:30'],
            'fields' => ['nullable', 'array', 'max:50'],
            'lead_status' => ['nullable', Rule::in(array_keys(Contact::LEAD_STATUSES))],
        ]);
        $phone = Phone::normalize($data['phone']);
        if (! $phone) {
            return self::error('invalid_phone', 'Give the number with country code, e.g. 919876543210.');
        }
        $contact = Contact::firstOrNew(['phone' => $phone]);
        if (! $contact->exists && ! $this->current->get()->canAdd('contacts')) {
            return self::error('plan_limit', $this->current->get()->limitMessage('contacts'), 403);
        }
        $contact->fill(array_filter([
            'name' => $data['name'] ?? null, 'email' => $data['email'] ?? null, 'lead_status' => $data['lead_status'] ?? null,
        ], fn ($v) => $v !== null));
        if (isset($data['tags'])) {
            $contact->tags = array_values(array_unique(array_merge($contact->tags ?? [], $data['tags'])));
        }
        if (isset($data['fields'])) {
            $contact->fields = array_merge($contact->fields ?? [], array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $data['fields']));
        }
        if (! $contact->exists) {
            $contact->opted_in_at = now();
            $contact->opt_in_source = 'API';
        }
        $created = ! $contact->exists;
        $contact->save();

        return response()->json(['data' => Webhooks::contact($contact)], $created ? 201 : 200);
    }

    /** @return array{0: ?Contact, 1: ?WhatsappAccount, 2: ?JsonResponse} */
    private function recipient(string $to, ?string $from): array
    {
        $phone = Phone::normalize($to);
        if (! $phone) {
            return [null, null, self::error('invalid_phone', 'Give "to" with country code, e.g. 919876543210.')];
        }
        $account = $from
            ? WhatsappAccount::where('phone_number_id', $from)->first()
            : (WhatsappAccount::where('status', 'connected')->first() ?? WhatsappAccount::first());
        if (! $account) {
            return [null, null, self::error('no_number', $from ? 'This "from" number is not connected to your account.' : 'Connect a WhatsApp number first.')];
        }
        $contact = Contact::firstOrNew(['phone' => $phone]);
        if (! $contact->exists) {
            if (! $this->current->get()->canAdd('contacts')) {
                return [null, null, self::error('plan_limit', $this->current->get()->limitMessage('contacts'), 403)];
            }
            $contact->fill(['opted_in_at' => now(), 'opt_in_source' => 'API'])->save();
        }
        if ($contact->blocked_at) {
            return [null, null, self::error('blocked', 'This number is on your blacklist.')];
        }

        return [$contact, $account, null];
    }

    private function send(WhatsappAccount $account, Contact $contact, string $type, string $body, array $payload, \Closure $call): JsonResponse
    {
        $message = new Message([
            'workspace_id' => $account->workspace_id, 'whatsapp_account_id' => $account->id, 'contact_id' => $contact->id,
            'direction' => 'out', 'phone' => $contact->phone, 'type' => $type, 'body' => $body,
            'payload' => $payload + ['source' => 'api', 'api_key_id' => request()->attributes->get('api_key_id')],
        ]);
        try {
            $result = $call();
            $message->fill(['status' => 'sent', 'wamid' => $result['messages'][0]['id'] ?? null, 'sent_at' => now()])->save();
            $contact->forceFill(['last_message_at' => now()])->save();

            return response()->json(['data' => Webhooks::message($message)], 201);
        } catch (CloudApiException $e) {
            $message->fill(['status' => 'failed', 'error_code' => (string) $e->metaCode, 'error_message' => $e->getMessage(), 'failed_at' => now()])->save();

            return response()->json(['error' => ['code' => 'whatsapp_error', 'meta_code' => $e->metaCode, 'message' => $e->getMessage()], 'data' => Webhooks::message($message)], 502);
        }
    }
}
