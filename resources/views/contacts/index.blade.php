@extends('layouts.app', ['title' => 'Contacts & Groups'])

@section('content')
<x-page-header title="Contacts & Lists" subtitle="Build contact lists by pasting numbers or uploading a CSV. Lists of 20,000+ numbers are fine.">
    <x-btn :href="route('contacts.create')" variant="outline" icon="user-plus">Add contact</x-btn>
    <x-btn href="#new-list" icon="plus" onclick="document.getElementById('new-list').scrollIntoView({behavior:'smooth'})">New list</x-btn>
</x-page-header>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Total contacts" :value="number_format($stats['total'])" icon="users" />
    <x-stat-card label="Subscribed" :value="number_format($stats['subscribed'])" sub="Can receive campaigns" icon="check" tone="green" />
    <x-stat-card label="Opted out" :value="number_format($stats['optedOut'])" sub="Replied STOP" icon="alert" tone="red" />
    <x-stat-card label="Lists" :value="number_format($stats['lists'])" icon="list" tone="sky" />
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-4">
        {{-- Lists --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <div class="font-semibold mb-3">Lists</div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('contacts.index') }}" class="px-3 py-1.5 rounded-full text-sm border {{ request('group') ? 'border-slate-200 hover:border-brand-300' : 'bg-brand-600 border-brand-600 text-white' }}">All contacts</a>
                @foreach ($groups as $g)
                    <span class="inline-flex items-center rounded-full text-sm border {{ request('group') == $g->id ? 'bg-brand-600 border-brand-600 text-white' : 'border-slate-200 hover:border-brand-300' }}">
                        <a href="?group={{ $g->id }}" class="pl-3 pr-1 py-1.5">{{ $g->name }} <span class="opacity-70">({{ number_format($g->contacts_count) }})</span></a>
                        <form method="POST" action="{{ route('groups.destroy', $g) }}" onsubmit="return confirm('Delete list {{ $g->name }}? Contacts are kept.')">@csrf @method('DELETE')<button class="pr-3 pl-1 opacity-60 hover:opacity-100" title="Delete list">×</button></form>
                    </span>
                @endforeach
            </div>
        </div>

        <form class="flex gap-2">
            @if (request('group'))<input type="hidden" name="group" value="{{ request('group') }}">@endif
            <div class="relative flex-1">
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                <input name="q" value="{{ request('q') }}" placeholder="Search name, phone, email" class="w-full border border-slate-200 rounded-xl pl-9 pr-3 py-2.5 text-sm">
            </div>
            <x-btn variant="outline">Search</x-btn>
        </form>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-500 border-b"><tr><th class="p-4">Contact</th><th class="p-4">Lists</th><th class="p-4">Status</th><th class="p-4"></th></tr></thead>
                <tbody>
                @forelse ($contacts as $c)
                    <tr class="border-t border-slate-100 hover:bg-slate-50">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-brand-50 text-brand-700 grid place-items-center font-semibold text-xs uppercase">{{ mb_substr($c->name ?: '#', 0, 1) }}</span>
                                <span><span class="block font-medium">{{ $c->name ?: '—' }}</span><span class="block text-xs text-slate-500 font-mono">+{{ $c->phone }}</span></span>
                            </div>
                        </td>
                        <td class="p-4">@foreach ($c->groups as $g)<span class="inline-block text-xs px-2 py-0.5 rounded-full bg-slate-100 mr-1 mb-1">{{ $g->name }}</span>@endforeach</td>
                        <td class="p-4">
                            @if ($c->opted_out_at)<span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-700">Opted out</span>
                            @else<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Subscribed</span>@endif
                        </td>
                        <td class="p-4 text-right whitespace-nowrap">
                            <a href="{{ route('contacts.edit', $c) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('contacts.destroy', $c) }}" class="inline" onsubmit="return confirm('Delete contact?')">@csrf @method('DELETE')<button class="text-red-600 hover:underline ml-3">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-10 text-center text-slate-500">No contacts yet. Paste numbers or upload a CSV to create your first list.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $contacts->links() }}</div>
    </div>

    {{-- New list --}}
    <div id="new-list" class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <div class="font-semibold mb-3">Create a contact list</div>
            <div class="grid grid-cols-2 gap-1 p-1 bg-slate-100 rounded-xl mb-4 text-sm font-medium">
                <button type="button" data-tab="paste" class="tab-btn rounded-lg py-2 bg-white shadow-sm text-brand-700">Paste numbers</button>
                <button type="button" data-tab="csv" class="tab-btn rounded-lg py-2 text-slate-600">Upload CSV</button>
            </div>

            <form method="POST" action="{{ route('contacts.paste') }}" data-panel="paste" class="tab-panel space-y-3 text-sm">
                @csrf
                <input name="list_name" value="{{ old('list_name') }}" placeholder="List name, e.g. Dilip" required class="w-full border border-slate-200 rounded-xl px-3 py-2">
                <textarea name="numbers" id="numbers" rows="8" required placeholder="One number per line, e.g.&#10;9876543210&#10;9876543211, Ravi&#10;+91 98765 43212" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-mono text-xs">{{ old('numbers') }}</textarea>
                <div class="text-xs text-slate-500"><span id="numbers-count">0</span> numbers found. Copy-paste from Excel works too (number, name).</div>
                <div class="grid grid-cols-2 gap-2">
                    <input name="default_country_code" value="91" placeholder="Country code" class="border border-slate-200 rounded-xl px-3 py-2">
                    <input name="opt_in_source" value="{{ old('opt_in_source', 'customer list') }}" placeholder="Opt-in source" class="border border-slate-200 rounded-xl px-3 py-2">
                </div>
                <label class="flex gap-2 items-start text-xs text-slate-600"><input type="checkbox" name="consent" value="1" class="mt-0.5"> These people agreed to receive WhatsApp messages from my business.</label>
                <x-btn class="w-full" icon="clipboard">Create list</x-btn>
            </form>

            <form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data" data-panel="csv" class="tab-panel hidden space-y-3 text-sm">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" required class="w-full text-sm">
                <p class="text-xs text-slate-500">Columns: <b>phone</b> (required), name, email. Other columns (city, amount…) become template variables.</p>
                <select name="contact_group_id" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                    <option value="">Add to existing list…</option>
                    @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                </select>
                <input name="new_group" placeholder="…or new list name" class="w-full border border-slate-200 rounded-xl px-3 py-2">
                <div class="grid grid-cols-2 gap-2">
                    <input name="default_country_code" value="91" placeholder="Country code" class="border border-slate-200 rounded-xl px-3 py-2">
                    <input name="opt_in_source" value="csv import" placeholder="Opt-in source" class="border border-slate-200 rounded-xl px-3 py-2">
                </div>
                <label class="flex gap-2 items-start text-xs text-slate-600"><input type="checkbox" name="consent" value="1" class="mt-0.5"> These people agreed to receive WhatsApp messages from my business.</label>
                <x-btn class="w-full" icon="upload">Upload</x-btn>
            </form>
        </div>

        @if ($imports->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                <div class="font-semibold mb-3">Recent imports</div>
                <div class="space-y-2 text-sm">
                    @foreach ($imports as $i)
                        <div class="flex justify-between gap-2">
                            <span class="truncate">{{ $i->original_name }}</span>
                            <span class="shrink-0 text-xs {{ $i->status === 'failed' ? 'text-red-600' : ($i->status === 'done' ? 'text-emerald-600' : 'text-amber-600') }}">
                                @if ($i->status === 'done'){{ number_format($i->imported) }} added, {{ number_format($i->skipped) }} skipped @else {{ $i->status }} @endif
                            </span>
                        </div>
                        @if ($i->error)<div class="text-xs text-red-600">{{ $i->error }}</div>@endif
                    @endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('groups.store') }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex gap-2">
            @csrf
            <input name="name" placeholder="Empty list name" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm" required>
            <x-btn variant="outline">Add</x-btn>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.tab-btn').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.className = 'tab-btn rounded-lg py-2 ' + (b === btn ? 'bg-white shadow-sm text-brand-700' : 'text-slate-600'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('hidden', p.dataset.panel !== btn.dataset.tab));
    }));
    const numbers = document.getElementById('numbers'), counter = document.getElementById('numbers-count');
    const count = () => counter.textContent = (numbers.value.match(/\+?\d[\d\s\-().]{6,}\d/g) || []).length.toLocaleString();
    numbers.addEventListener('input', count); count();
</script>
@endpush
