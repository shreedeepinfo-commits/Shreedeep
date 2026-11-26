@extends('layouts.app', ['title' => 'Contacts'])

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Contacts <span class="text-base text-slate-500">({{ number_format($contacts->total()) }})</span></h1>
    <a href="{{ route('contacts.create') }}" class="rounded bg-brand-600 text-white px-4 py-2 font-semibold">Add contact</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <div class="lg:col-span-3">
        <form class="flex gap-2 mb-4">
            <input name="q" value="{{ request('q') }}" placeholder="Search name, phone, email" class="flex-1 border rounded px-3 py-2">
            <select name="group" class="border rounded px-3 py-2">
                <option value="">All groups</option>
                @foreach ($groups as $g)<option value="{{ $g->id }}" @selected(request('group') == $g->id)>{{ $g->name }}</option>@endforeach
            </select>
            <button class="rounded border px-4">Filter</button>
        </form>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 text-left"><tr><th class="p-3">Phone</th><th class="p-3">Name</th><th class="p-3">Groups</th><th class="p-3">Opt-in</th><th class="p-3"></th></tr></thead>
                <tbody>
                @forelse ($contacts as $c)
                    <tr class="border-t">
                        <td class="p-3 font-mono">+{{ $c->phone }}</td>
                        <td class="p-3">{{ $c->name }}</td>
                        <td class="p-3">{{ $c->groups->pluck('name')->join(', ') }}</td>
                        <td class="p-3">
                            @if ($c->opted_out_at)<span class="text-red-700">Opted out</span>
                            @else<span class="text-emerald-700">{{ $c->opt_in_source ?? 'yes' }}</span>@endif
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <a href="{{ route('contacts.edit', $c) }}" class="underline">Edit</a>
                            <form method="POST" action="{{ route('contacts.destroy', $c) }}" class="inline" onsubmit="return confirm('Delete contact?')">@csrf @method('DELETE')<button class="underline text-red-700 ml-2">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-slate-500">No contacts yet. Import a CSV to get started.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $contacts->links() }}</div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Import CSV</h2>
            <form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data" class="space-y-3 text-sm">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" required class="w-full">
                <p class="text-xs text-slate-500">Columns: <b>phone</b> (required), name, email. Other columns become template variables.</p>
                <select name="contact_group_id" class="w-full border rounded px-2 py-1">
                    <option value="">Add to group…</option>
                    @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                </select>
                <input name="new_group" placeholder="…or new group name" class="w-full border rounded px-2 py-1">
                <input name="default_country_code" value="91" placeholder="Default country code" class="w-full border rounded px-2 py-1">
                <input name="opt_in_source" value="{{ old('opt_in_source', 'csv import') }}" placeholder="Where did they opt in?" class="w-full border rounded px-2 py-1">
                <label class="flex gap-2 items-start text-xs"><input type="checkbox" name="consent" value="1" class="mt-0.5"> These contacts agreed to receive WhatsApp messages from my business.</label>
                <button class="w-full rounded bg-brand-600 text-white py-2 font-semibold">Upload</button>
            </form>
            @if ($imports->isNotEmpty())
                <div class="mt-4 text-xs space-y-1">
                    @foreach ($imports as $i)
                        <div>{{ $i->original_name }}: <b>{{ $i->status }}</b> @if($i->status === 'done') ({{ $i->imported }} imported, {{ $i->skipped }} skipped)@endif @if($i->error)<span class="text-red-700">{{ $i->error }}</span>@endif</div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Groups</h2>
            <ul class="text-sm space-y-1 mb-3">
                @foreach ($groups as $g)
                    <li class="flex justify-between">
                        <a href="?group={{ $g->id }}" class="underline">{{ $g->name }}</a>
                        <span class="text-slate-500">{{ $g->contacts_count }}
                            <form method="POST" action="{{ route('groups.destroy', $g) }}" class="inline" onsubmit="return confirm('Delete group? Contacts are kept.')">@csrf @method('DELETE')<button class="text-red-700 ml-1">×</button></form>
                        </span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('groups.store') }}" class="flex gap-2">
                @csrf
                <input name="name" placeholder="New group" class="flex-1 border rounded px-2 py-1 text-sm" required>
                <button class="rounded border px-3 text-sm">Add</button>
            </form>
        </div>
    </div>
</div>
@endsection
