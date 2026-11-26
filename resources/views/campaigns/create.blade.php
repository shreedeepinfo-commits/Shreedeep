@extends('layouts.app', ['title' => 'New campaign'])

@section('content')
<h1 class="text-2xl font-semibold mb-6">New campaign</h1>

@if ($templates->isEmpty())
    <div class="bg-white rounded-xl shadow p-8 text-slate-600">You need at least one <b>approved</b> template. <a class="underline" href="{{ route('templates.index') }}">Go to templates</a>.</div>
@else
<form method="POST" action="{{ route('campaigns.store') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    @csrf
    <div class="lg:col-span-2 bg-white rounded-xl shadow p-6 space-y-5">
        <label class="block"><span class="text-sm font-medium">Campaign name</span>
            <input name="name" value="{{ old('name') }}" required class="mt-1 w-full border rounded px-3 py-2" placeholder="Diwali offer 2026"></label>

        <label class="block"><span class="text-sm font-medium">Template</span>
            <select name="message_template_id" id="template" class="mt-1 w-full border rounded px-3 py-2" required>
                @foreach ($templates as $t)
                    <option value="{{ $t->id }}" @selected(old('message_template_id') == $t->id)>{{ $t->name }} ({{ $t->language }})</option>
                @endforeach
            </select></label>

        <div id="header-media" class="hidden">
            <label class="block"><span class="text-sm font-medium">Header media link (public https URL)</span>
                <input name="header_media_url" value="{{ old('header_media_url') }}" class="mt-1 w-full border rounded px-3 py-2" placeholder="https://…/offer.jpg"></label>
        </div>
        <div id="header-var" class="hidden">
            <span class="text-sm font-medium">Header variable</span>
            <div class="grid grid-cols-3 gap-2 mt-1">
                <select name="variables[header][type]" class="border rounded px-2 py-1 text-sm">@include('campaigns.var-options')</select>
                <input name="variables[header][value]" placeholder="Field name or text" class="col-span-2 border rounded px-2 py-1 text-sm">
            </div>
        </div>
        <div id="body-vars" class="space-y-2"></div>

        <div>
            <span class="text-sm font-medium">Send to</span>
            <label class="flex items-center gap-2 mt-1 text-sm"><input type="radio" name="audience" value="all" @checked(old('audience', 'groups') === 'all')> All subscribed contacts</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="audience" value="groups" @checked(old('audience', 'groups') === 'groups')> These groups:</label>
            <div class="ml-6 mt-1 flex flex-wrap gap-3">
                @forelse ($groups as $g)
                    <label class="text-sm flex items-center gap-1"><input type="checkbox" name="groups[]" value="{{ $g->id }}" @checked(in_array($g->id, old('groups', [])))> {{ $g->name }} ({{ $g->contacts_count }})</label>
                @empty
                    <span class="text-sm text-slate-500">No groups yet.</span>
                @endforelse
            </div>
        </div>

        <div>
            <span class="text-sm font-medium">When</span>
            <label class="flex items-center gap-2 mt-1 text-sm"><input type="radio" name="send" value="now" checked> Send now</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="send" value="later" @checked(old('send') === 'later')> Schedule:
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="border rounded px-2 py-1"></label>
            <p class="text-xs text-slate-500 mt-1">Times are in {{ config('app.timezone') }}.</p>
        </div>

        <button class="rounded bg-emerald-600 text-white px-5 py-2 font-semibold">Launch campaign</button>
    </div>

    <div>
        <div class="text-sm font-medium mb-2">Preview</div>
        <div class="rounded-xl bg-[#efeae2] p-4">
            <div id="preview" class="rounded-lg bg-white p-3 text-sm whitespace-pre-line shadow"></div>
        </div>
    </div>
</form>

<template id="var-options">@include('campaigns.var-options')</template>
@endif
@endsection

@push('scripts')
<script>
    const templates = @json($templateMeta);
    const oldBody = @json(old('variables.body', []));
    const select = document.getElementById('template');

    function render() {
        const t = templates[select?.value];
        if (!t) return;
        document.getElementById('header-media').classList.toggle('hidden', !['IMAGE', 'VIDEO', 'DOCUMENT'].includes(t.header));
        document.getElementById('header-var').classList.toggle('hidden', !t.headerVars);
        const box = document.getElementById('body-vars');
        box.innerHTML = '';
        for (let i = 0; i < t.vars; i++) {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-4 gap-2 items-center';
            row.innerHTML = `<span class="text-sm font-medium">Variable ${i + 1}</span>
                <select name="variables[body][${i}][type]" class="border rounded px-2 py-1 text-sm">${document.getElementById('var-options').innerHTML}</select>
                <input name="variables[body][${i}][value]" placeholder="Field name or text" class="border rounded px-2 py-1 text-sm">
                <input name="variables[body][${i}][fallback]" placeholder="If empty, use…" class="border rounded px-2 py-1 text-sm">`;
            if (oldBody[i]) {
                row.querySelector('select').value = oldBody[i].type;
                row.querySelector('input').value = oldBody[i].value || '';
            }
            box.appendChild(row);
        }
        let text = (t.headerText ? t.headerText + '\n\n' : (t.header ? '[' + t.header + ']\n\n' : '')) + t.body + (t.footer ? '\n\n' + t.footer : '');
        document.getElementById('preview').textContent = text;
    }
    select?.addEventListener('change', render);
    render();
</script>
@endpush
