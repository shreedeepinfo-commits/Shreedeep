@extends('layouts.app', ['title' => $contact->exists ? 'Edit contact' : 'Add contact'])

@php
    $fieldRows = collect(old('field_keys') ? array_combine(old('field_keys'), old('field_values', [])) : ($contact->fields ?? []))
        ->map(fn ($v, $k) => [$k, is_scalar($v) ? $v : json_encode($v)])->values()->all();
    $fieldRows[] = ['', ''];
@endphp

@section('content')
<x-page-header :title="$contact->exists ? 'Edit contact' : 'Add contact'" subtitle="Details, tags and custom fields. Custom fields work as template variables in campaigns.">
    <x-btn variant="outline" :href="$contact->exists ? route('contacts.show', $contact) : route('contacts.index')">‹ Back</x-btn>
</x-page-header>

<form method="POST" action="{{ $contact->exists ? route('contacts.update', $contact) : route('contacts.store') }}" class="grid grid-cols-1 xl:grid-cols-2 gap-6 max-w-5xl">
    @csrf
    @if ($contact->exists) @method('PUT') @endif

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
        <div class="font-semibold">Contact details</div>
        <label class="block"><span class="text-sm font-medium text-slate-600">Phone (with country code)</span>
            <input name="phone" value="{{ old('phone', $contact->phone) }}" placeholder="919876543210" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required></label>
        <label class="block"><span class="text-sm font-medium text-slate-600">Name</span>
            <input name="name" value="{{ old('name', $contact->name) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></label>
        <label class="block"><span class="text-sm font-medium text-slate-600">Email</span>
            <input name="email" type="email" value="{{ old('email', $contact->email) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></label>
        <label class="block"><span class="text-sm font-medium text-slate-600">Lead source</span>
            <input name="opt_in_source" value="{{ old('opt_in_source', $contact->opt_in_source) }}" placeholder="Website, Facebook ad, walk-in…" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></label>
        <label class="block"><span class="text-sm font-medium text-slate-600">Tags (comma separated)</span>
            <input name="tags" value="{{ old('tags', implode(', ', $contact->tags ?? [])) }}" placeholder="hot lead, pune" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></label>
        <div>
            <span class="text-sm font-medium text-slate-600">Lists</span>
            <div class="mt-2 flex flex-wrap gap-2">
                @forelse ($groups as $g)
                    <label class="text-sm flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-slate-200 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 cursor-pointer"><input type="checkbox" name="groups[]" value="{{ $g->id }}" class="accent-brand-600" @checked($contact->groups->contains($g))> {{ $g->name }}</label>
                @empty
                    <span class="text-xs text-slate-400">No lists yet.</span>
                @endforelse
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="opted_out" value="1" class="accent-brand-600" @checked($contact->opted_out_at)> Opted out (never send campaigns)</label>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 h-fit">
        <div class="font-semibold">Custom fields</div>
        <p class="text-xs text-slate-500 mt-1">e.g. city = Pune, budget = 50 lakh. Use them in templates as variables.</p>
        <div id="fieldRows" class="space-y-2 mt-4">
            @foreach ($fieldRows as [$k, $v])
                <div class="flex gap-2">
                    <input name="field_keys[]" value="{{ $k }}" placeholder="Field (e.g. city)" class="w-2/5 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <input name="field_values[]" value="{{ $v }}" placeholder="Value" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
            @endforeach
        </div>
        <button type="button" id="addField" class="mt-3 text-sm font-semibold text-brand-700 hover:underline">+ Add field</button>
        <x-btn class="w-full mt-6">Save contact</x-btn>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('addField').addEventListener('click', () => {
    const rows = document.getElementById('fieldRows');
    const row = rows.lastElementChild.cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = '');
    rows.appendChild(row);
});
</script>
@endpush
