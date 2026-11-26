<?php

namespace App\Http\Controllers;

use App\Jobs\ImportContacts;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactImport;
use App\Support\PastedNumbers;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $contacts = Contact::query()
            ->with('groups')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('phone', 'like', '%'.preg_replace('/\D+/', '', $term).'%')
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('group'), fn ($q, $group) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'groups' => ContactGroup::withCount('contacts')->orderBy('name')->get(),
            'imports' => ContactImport::latest()->limit(5)->get(),
            'stats' => [
                'total' => Contact::count(),
                'subscribed' => Contact::subscribed()->count(),
                'optedOut' => Contact::whereNotNull('opted_out_at')->count(),
                'lists' => ContactGroup::count(),
            ],
        ]);
    }

    public function create()
    {
        return view('contacts.form', ['contact' => new Contact, 'groups' => ContactGroup::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
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

        return redirect()->route('contacts.index')->with('status', 'Contact updated.');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return back()->with('status', 'Contact deleted.');
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'contact_group_id' => ['nullable', Rule::exists('contact_groups', 'id')->where('workspace_id', $this->workspaceId())],
            'new_group' => ['nullable', 'string', 'max:255'],
            'default_country_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'consent' => ['accepted'],
            'opt_in_source' => ['required', 'string', 'max:255'],
        ], ['consent.accepted' => 'Please confirm these contacts agreed to receive WhatsApp messages from you.']);

        $groupId = $data['contact_group_id'] ?? null;
        if (! empty($data['new_group'])) {
            $groupId = ContactGroup::firstOrCreate(['name' => $data['new_group']])->id;
        }

        $import = ContactImport::create([
            'contact_group_id' => $groupId,
            'path' => $request->file('file')->store('imports'),
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
        ]);
        $phone = Phone::normalize($data['phone'], '91');
        $request->merge(['phone' => $phone]);
        $request->validate(['phone' => [
            'required',
            Rule::unique('contacts', 'phone')->where('workspace_id', $this->workspaceId())->ignore($contact?->id),
        ]], ['phone.required' => 'Enter a valid phone number with country code.']);

        return [
            'phone' => $phone,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'opted_in_at' => $contact?->opted_in_at ?? now(),
            'opt_in_source' => $contact?->opt_in_source ?? 'manual',
            'opted_out_at' => $request->boolean('opted_out') ? ($contact?->opted_out_at ?? now()) : null,
        ];
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
