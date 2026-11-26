@extends('layouts.app', ['title' => $contact->exists ? 'Edit contact' : 'Add contact'])

@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ $contact->exists ? 'Edit contact' : 'Add contact' }}</h1>
<form method="POST" action="{{ $contact->exists ? route('contacts.update', $contact) : route('contacts.store') }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 max-w-lg space-y-4">
    @csrf
    @if ($contact->exists) @method('PUT') @endif
    <label class="block"><span class="text-sm font-medium">Phone (with country code)</span>
        <input name="phone" value="{{ old('phone', $contact->phone) }}" placeholder="919876543210" class="mt-1 w-full border rounded px-3 py-2" required></label>
    <label class="block"><span class="text-sm font-medium">Name</span>
        <input name="name" value="{{ old('name', $contact->name) }}" class="mt-1 w-full border rounded px-3 py-2"></label>
    <label class="block"><span class="text-sm font-medium">Email</span>
        <input name="email" type="email" value="{{ old('email', $contact->email) }}" class="mt-1 w-full border rounded px-3 py-2"></label>
    <div>
        <span class="text-sm font-medium">Groups</span>
        <div class="mt-1 flex flex-wrap gap-3">
            @foreach ($groups as $g)
                <label class="text-sm flex items-center gap-1"><input type="checkbox" name="groups[]" value="{{ $g->id }}" @checked($contact->groups->contains($g))> {{ $g->name }}</label>
            @endforeach
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="opted_out" value="1" @checked($contact->opted_out_at)> Opted out (never send campaigns)</label>
    <button class="rounded bg-brand-600 text-white px-4 py-2 font-semibold">Save</button>
</form>
@endsection
