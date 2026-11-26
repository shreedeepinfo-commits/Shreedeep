<?php

namespace App\Http\Controllers;

use App\Jobs\ImportContacts;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactImport;
use App\Models\ContactNote;
use App\Models\Message;
use App\Support\CurrentWorkspace;
use App\Support\PastedNumbers;
use App\Support\SpreadsheetToCsv;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public const SEGMENTS = [
        'subscribed' => 'Subscribed',
        'opted_out' => 'Opted out',
        'blacklisted' => 'Blacklisted',
        'follow_up' => 'Follow-up due',
    ];

    public function index(Request $request)
    {
        $contacts = $this->filtered($request)->with(['groups', 'assignedUser'])->latest()->paginate(50)->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'groups' => ContactGroup::withCount('contacts')->orderBy('name')->get(),
            'imports' => ContactImport::latest()->limit(5)->get(),
            'tags' => $this->allTags(),
            'stats' => [
                'total' => Contact::count(),
                'subscribed' => Contact::subscribed()->count(),
                'optedOut' => Contact::whereNotNull('opted_out_at')->count(),
                'blacklisted' => Contact::whereNotNull('blocked_at')->count(),
                'followUps' => Contact::whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay())->count(),
                'lists' => ContactGroup::count(),
            ],
        ]);
    }

    public function show(Contact $contact)
    {
        $contact->load(['groups', 'assignedUser', 'notes.user']);
        $messages = Message::with('user', 'campaign')->where('contact_id', $contact->id)->latest('id')->limit(100)->get();
        $timeline = $messages->map(fn ($m) => ['at' => $m->created_at, 'kind' => 'message', 'item' => $m])
            ->concat($contact->notes->map(fn ($n) => ['at' => $n->created_at, 'kind' => 'note', 'item' => $n]))
            ->sortByDesc('at')->values();

        return view('contacts.show', [
            'contact' => $contact,
            'timeline' => $timeline,
            'members' => app(CurrentWorkspace::class)->get()->users()->orderBy('name')->get(),
            'stats' => [
                'sent' => Message::where('contact_id', $contact->id)->where('direction', 'out')->count(),
                'received' => Message::where('contact_id', $contact->id)->where('direction', 'in')->count(),
                'campaigns' => Message::where('contact_id', $contact->id)->whereNotNull('campaign_id')->distinct()->count('campaign_id'),
            ],
        ]);
    }

    /** Lead fields: stage, owner, deal value, follow-up. Used by the contact page and the pipeline board. */
    public function lead(Request $request, Contact $contact)
    {
        $members = app(CurrentWorkspace::class)->get()->users()->pluck('users.id')->all();
        $data = $request->validate([
            'lead_status' => ['sometimes', 'required', Rule::in(array_keys(Contact::LEAD_STATUSES))],
            'assigned_user_id' => ['sometimes', 'nullable', Rule::in($members)],
            'deal_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'follow_up_at' => ['sometimes', 'nullable', 'date'],
            'follow_up_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'opt_in_source' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $before = $contact->lead_status;
        $contact->update($data);
        if (isset($data['lead_status']) && $data['lead_status'] !== $before) {
            ContactNote::create(['contact_id' => $contact->id, 'user_id' => $request->user()->id, 'system' => true,
                'body' => 'Lead moved to '.$contact->leadLabel()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'lead_status' => $contact->lead_status]);
        }

        return back()->with('status', 'Lead updated.');
    }

    public function note(Request $request, Contact $contact)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        ContactNote::create(['contact_id' => $contact->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);

        return back()->with('status', 'Note added.');
    }

    /** Blacklist: never send campaigns, chats or bot replies to this number. */
    public function block(Request $request, Contact $contact)
    {
        $blocked = ! $contact->blocked_at;
        $contact->update(['blocked_at' => $blocked ? now() : null]);
        ContactNote::create(['contact_id' => $contact->id, 'user_id' => $request->user()->id, 'system' => true,
            'body' => $blocked ? 'Added to blacklist' : 'Removed from blacklist']);

        return back()->with('status', $blocked ? 'Number blacklisted. It will never get messages from you.' : 'Number removed from the blacklist.');
    }

    /** Download the filtered contacts as CSV (opens in Excel). */
    public function export(Request $request)
    {
        $query = $this->filtered($request)->with(['groups', 'assignedUser'])->orderBy('id');
        $fieldKeys = Contact::whereNotNull('fields')->pluck('fields')->flatMap(fn ($f) => array_keys($f ?? []))->unique()->values()->all();
        $name = 'contacts-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $fieldKeys) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // so Excel reads Hindi and ₹ correctly
            fputcsv($out, array_merge(['phone', 'name', 'email', 'lead_status', 'deal_value', 'owner', 'tags', 'lists', 'source',
                'follow_up_at', 'follow_up_note', 'opted_out', 'blacklisted', 'created_at'], $fieldKeys), escape: '\\');
            $query->chunk(1000, function ($contacts) use ($out, $fieldKeys) {
                foreach ($contacts as $c) {
                    fputcsv($out, array_merge([
                        $c->phone, $c->name, $c->email, $c->lead_status, $c->deal_value, $c->assignedUser?->name,
                        implode(', ', $c->tags ?? []), $c->groups->pluck('name')->implode(', '), $c->opt_in_source,
                        $c->follow_up_at?->format('Y-m-d H:i'), $c->follow_up_note,
                        $c->opted_out_at ? 'yes' : 'no', $c->blocked_at ? 'yes' : 'no', $c->created_at?->format('Y-m-d H:i'),
                    ], array_map(fn ($k) => is_scalar($v = ($c->fields ?? [])[$k] ?? '') ? $v : json_encode($v), $fieldKeys)), escape: '\\');
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create()
    {
        return view('contacts.form', ['contact' => new Contact, 'groups' => ContactGroup::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        if ($limit = $this->planLimitReached('contacts', 1, 'phone')) {
            return $limit;
        }
        $contact = Contact::create($this->validated($request));
        $contact->groups()->sync($this->groupIds($request));

        return redirect()->route('contacts.index')->with('status', 'Contact added.');
    }

    public function edit(Contact $contact)
    {
        return view('contacts.form', ['contact' => $contact, 'groups' => ContactGroup::orderBy('name')->get()]);
    }

    public function update(Request $request, Contact $contact)
    {
        $contact->update($this->validated($request, $contact));
        $contact->groups()->sync($this->groupIds($request));

        return redirect()->route('contacts.show', $contact)->with('status', 'Contact updated.');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return back()->with('status', 'Contact deleted.');
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', function ($attr, $file, $fail) {
                if (! in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt', 'xlsx'], true)) {
                    $fail('Upload a CSV or Excel (.xlsx) file.');
                }
            }],
            'contact_group_id' => ['nullable', Rule::exists('contact_groups', 'id')->where('workspace_id', $this->workspaceId())],
            'new_group' => ['nullable', 'string', 'max:255'],
            'default_country_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'consent' => ['accepted'],
            'opt_in_source' => ['required', 'string', 'max:255'],
        ], ['consent.accepted' => 'Please confirm these contacts agreed to receive WhatsApp messages from you.']);
        if ($limit = $this->planLimitReached('contacts', 1, 'file')) {
            return $limit;
        }

        $groupId = $data['contact_group_id'] ?? null;
        if (! empty($data['new_group'])) {
            $groupId = ContactGroup::firstOrCreate(['name' => $data['new_group']])->id;
        }

        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            try {
                $path = SpreadsheetToCsv::convert($file->getRealPath());
            } catch (\Throwable $e) {
                return back()->withErrors(['file' => $e instanceof \RuntimeException && str_contains($e->getMessage(), 'zip') ? $e->getMessage() : 'Could not read this Excel file. Save it as CSV and try again.']);
            }
        } else {
            $path = $file->store('imports');
        }

        $import = ContactImport::create([
            'contact_group_id' => $groupId,
            'path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'default_country_code' => isset($data['default_country_code']) ? ltrim($data['default_country_code'], '+') : null,
            'opt_in_source' => $data['opt_in_source'],
        ]);
        ImportContacts::dispatch($import->id);

        return redirect()->route('contacts.index')->with('status', 'Import started. Refresh in a moment to see the result.');
    }

    /** Create (or add to) a named list from pasted numbers. Handles 20k+ numbers via the import job. */
    public function paste(Request $request)
    {
        $data = $request->validate([
            'list_name' => ['required', 'string', 'max:255'],
            'numbers' => ['required', 'string', 'max:2000000'],
            'default_country_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'opt_in_source' => ['required', 'string', 'max:255'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please confirm these contacts agreed to receive WhatsApp messages from you.']);

        if ($limit = $this->planLimitReached('contacts', 1, 'numbers')) {
            return $limit;
        }
        $count = count(PastedNumbers::parse($data['numbers']));
        if ($count === 0) {
            return back()->withErrors(['numbers' => 'No phone numbers found in the pasted text.'])->withInput();
        }

        $group = ContactGroup::firstOrCreate(['name' => $data['list_name']]);
        $import = ContactImport::create([
            'contact_group_id' => $group->id,
            'path' => PastedNumbers::toCsv($data['numbers']),
            'original_name' => 'Pasted list: '.$group->name,
            'default_country_code' => isset($data['default_country_code']) ? ltrim($data['default_country_code'], '+') : null,
            'opt_in_source' => $data['opt_in_source'],
        ]);
        ImportContacts::dispatch($import->id);

        return redirect()->route('contacts.index', ['group' => $group->id])
            ->with('status', "Adding {$count} numbers to the list \"{$group->name}\". Refresh in a moment to see them.");
    }

    private function validated(Request $request, ?Contact $contact = null): array
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'opted_out' => ['nullable', 'boolean'],
            'opt_in_source' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
            'field_keys' => ['nullable', 'array', 'max:30'],
            'field_values' => ['nullable', 'array', 'max:30'],
        ]);
        $phone = Phone::normalize($data['phone'], '91');
        $request->merge(['phone' => $phone]);
        $request->validate(['phone' => [
            'required',
            Rule::unique('contacts', 'phone')->where('workspace_id', $this->workspaceId())->ignore($contact?->id),
        ]], ['phone.required' => 'Enter a valid phone number with country code.']);

        $fields = [];
        foreach ((array) $request->input('field_keys', []) as $i => $key) {
            $key = Str::of((string) $key)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(40, '')->toString();
            $value = trim((string) ($request->input('field_values')[$i] ?? ''));
            if ($key !== '' && $value !== '' && ! in_array($key, ['name', 'phone', 'email'], true)) {
                $fields[$key] = Str::limit($value, 500, '');
            }
        }
        $tags = collect(explode(',', (string) $request->input('tags')))
            ->map(fn ($t) => Str::limit(trim($t), 30, ''))->filter()->unique(fn ($t) => mb_strtolower($t))->take(20)->values()->all();

        return [
            'phone' => $phone,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'fields' => $fields ?: null,
            'tags' => $tags ?: null,
            'opted_in_at' => $contact?->opted_in_at ?? now(),
            'opt_in_source' => $request->filled('opt_in_source') ? Str::limit($request->input('opt_in_source'), 255, '') : ($contact?->opt_in_source ?? 'manual'),
            'opted_out_at' => $request->boolean('opted_out') ? ($contact?->opted_out_at ?? now()) : null,
        ];
    }

    /** Contacts matching the list filters (search, list, lead stage, tag, segment). */
    private function filtered(Request $request)
    {
        return Contact::query()
            ->when($request->query('q'), function ($q, $term) {
                $digits = preg_replace('/\D+/', '', $term);
                $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
                    ->when($digits !== '', fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%")));
            })
            ->when($request->query('group'), fn ($q, $group) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))
            ->when(array_key_exists((string) $request->query('stage'), Contact::LEAD_STATUSES), fn ($q) => $q->where('lead_status', $request->query('stage')))
            ->when($request->query('tag'), fn ($q, $tag) => $q->where('tags', 'like', '%'.json_encode((string) $tag).'%'))
            ->when($request->query('segment'), fn ($q, $segment) => match ($segment) {
                'subscribed' => $q->subscribed(),
                'opted_out' => $q->whereNotNull('opted_out_at'),
                'blacklisted' => $q->whereNotNull('blocked_at'),
                'follow_up' => $q->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay()),
                default => $q,
            });
    }

    private function allTags(): array
    {
        return Contact::whereNotNull('tags')->pluck('tags')->flatten()->filter()
            ->unique(fn ($t) => mb_strtolower($t))->sort()->values()->all();
    }

    private function groupIds(Request $request): array
    {
        return ContactGroup::whereIn('id', (array) $request->input('groups', []))->pluck('id')->all();
    }

    private function workspaceId(): ?int
    {
        return app(\App\Support\CurrentWorkspace::class)->id();
    }
}
