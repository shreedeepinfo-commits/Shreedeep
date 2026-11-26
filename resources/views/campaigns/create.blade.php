@extends('layouts.app', ['title' => 'New campaign'])

@php
    $fromAud = $from?->audience ?? [];
    $fromType = $from ? (! empty($fromAud['all']) ? 'all' : (! empty($fromAud['tags']) ? 'tags' : 'groups')) : null;
    $preGroups = old('groups', $from ? ($fromAud['groups'] ?? []) : (request('group') ? [(int) request('group')] : []));
    $preTags = old('tags', $fromAud['tags'] ?? []);
@endphp

@section('content')
<x-page-header title="New campaign" subtitle="Pick a template, choose who gets it, personalise and send.">
    <x-btn :href="route('campaigns.index')" variant="outline">Cancel</x-btn>
</x-page-header>

@if ($templates->isEmpty())
    <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
        <div class="font-semibold">You need at least one approved template</div>
        <p class="text-sm text-slate-500 mt-1">Create a template and wait for Meta approval, or sync your existing templates.</p>
        <x-btn :href="route('templates.index')" class="mt-4">Go to templates</x-btn>
    </div>
@else
<form method="POST" action="{{ route('campaigns.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    @csrf
    <div class="xl:col-span-2 space-y-5">
        {{-- 1. Details --}}
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4"><span class="w-7 h-7 rounded-full bg-brand-600 text-white grid place-items-center text-sm font-semibold">1</span><span class="font-semibold">Campaign &amp; template</span></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="block"><span class="text-sm font-medium text-slate-700">Campaign name</span>
                    <input name="name" value="{{ old('name', $from ? $from->name.' (copy)' : 'Campaign '.now()->format('d M Y, h:i A')) }}" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5"></label>
                <label class="block"><span class="text-sm font-medium text-slate-700">Approved template</span>
                    <select name="message_template_id" id="template" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5" required>
                        @foreach ($templates as $t)
                            <option value="{{ $t->id }}" @selected(old('message_template_id', $from?->message_template_id ?? request('template')) == $t->id)>{{ $t->name }} · {{ $t->language }} · {{ strtolower($t->category) }}</option>
                        @endforeach
                    </select></label>
            </div>
            <div id="header-media" class="hidden mt-4">
                <label class="block"><span class="text-sm font-medium text-slate-700">Header media link (public https URL)</span>
                    <input name="header_media_url" value="{{ old('header_media_url', $from?->header_media_url) }}" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5" placeholder="https://…/offer.jpg"></label>
            </div>
        </section>

        {{-- 2. Audience --}}
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4"><span class="w-7 h-7 rounded-full bg-brand-600 text-white grid place-items-center text-sm font-semibold">2</span><span class="font-semibold">Audience</span></div>
            @php $aud = old('audience', $fromType ?? ($groups->isEmpty() ? 'paste' : 'groups')); @endphp
            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
                @foreach (['groups' => ['Contact lists', 'list'], 'tags' => ['By tags', 'bolt'], 'paste' => ['Paste numbers', 'clipboard'], 'csv' => ['Upload CSV', 'upload'], 'all' => ['All contacts', 'users']] as $key => [$label, $icon])
                    <label class="aud-opt cursor-pointer flex items-center gap-2 rounded-xl border px-3 py-3 text-sm font-medium {{ $aud === $key ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200' }}">
                        <input type="radio" name="audience" value="{{ $key }}" class="sr-only" @checked($aud === $key)> <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
                    </label>
                @endforeach
            </div>
            <div data-aud="groups" class="aud-panel {{ $aud === 'groups' ? '' : 'hidden' }}">
                <div class="flex flex-wrap gap-2">
                    @forelse ($groups as $g)
                        <label class="cursor-pointer inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                            <input type="checkbox" name="groups[]" value="{{ $g->id }}" data-count="{{ $g->contacts_count }}" class="accent-brand-600 aud-count" @checked(in_array($g->id, $preGroups))> {{ $g->name }} <span class="opacity-60">({{ number_format($g->contacts_count) }})</span>
                        </label>
                    @empty
                        <span class="text-sm text-slate-500">No lists yet. Use Paste numbers or Upload CSV.</span>
                    @endforelse
                </div>
            </div>
            <div data-aud="tags" class="aud-panel {{ $aud === 'tags' ? '' : 'hidden' }}">
                <div class="flex flex-wrap gap-2">
                    @forelse ($tagCounts as $tag => $count)
                        <label class="cursor-pointer inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                            <input type="checkbox" name="tags[]" value="{{ $tag }}" data-count="{{ $count }}" class="accent-brand-600 aud-count" @checked(in_array($tag, $preTags))> {{ $tag }} <span class="opacity-60">({{ number_format($count) }})</span>
                        </label>
                    @empty
                        <span class="text-sm text-slate-500">No tags yet. Add tags to contacts in Contacts &amp; CRM or Live Chat.</span>
                    @endforelse
                </div>
                <div class="text-xs text-slate-500 mt-2">Contacts with any of the selected tags get the message.</div>
            </div>
            <div data-aud="paste" class="aud-panel space-y-2 {{ $aud === 'paste' ? '' : 'hidden' }}">
                <textarea name="numbers" id="numbers" rows="6" placeholder="One number per line (number, name). 20,000+ numbers are fine." class="w-full border border-slate-200 rounded-xl px-3 py-2 font-mono text-xs">{{ old('numbers') }}</textarea>
                <div class="text-xs text-slate-500"><span id="numbers-count">0</span> numbers found. They are saved as a new list named after this campaign.</div>
            </div>
            <div data-aud="csv" class="aud-panel space-y-2 {{ $aud === 'csv' ? '' : 'hidden' }}">
                <input type="file" name="file" accept=".csv,text/csv" class="text-sm">
                <div class="text-xs text-slate-500">Columns: phone (required), name, and any others you want as variables.</div>
            </div>
            <div data-aud="all" class="aud-panel text-sm text-slate-600 {{ $aud === 'all' ? '' : 'hidden' }}">Every subscribed contact in this business. Opted-out contacts are always skipped.</div>
            <div id="upload-extra" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-center {{ in_array($aud, ['paste', 'csv']) ? '' : 'hidden' }}">
                <input name="default_country_code" value="{{ old('default_country_code', '91') }}" placeholder="Country code" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                <label class="md:col-span-2 flex gap-2 items-start text-xs text-slate-600"><input type="checkbox" name="consent" value="1" class="mt-0.5" @checked(old('consent'))> These people agreed to receive WhatsApp messages from my business.</label>
            </div>
        </section>

        {{-- 3. Personalise --}}
        <section id="personalise" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4"><span class="w-7 h-7 rounded-full bg-brand-600 text-white grid place-items-center text-sm font-semibold">3</span><span class="font-semibold">Personalise variables</span></div>
            <div id="header-var" class="hidden mb-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-center">
                    <span class="text-sm font-medium">Header variable</span>
                    <select name="variables[header][type]" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">@include('campaigns.var-options')</select>
                    <input name="variables[header][value]" placeholder="Column name or text" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">
                </div>
            </div>
            <div id="body-vars" class="space-y-2"></div>
            <div id="no-vars" class="text-sm text-slate-500 hidden">This template has no variables.</div>
        </section>

        {{-- 4. Schedule --}}
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4"><span class="w-7 h-7 rounded-full bg-brand-600 text-white grid place-items-center text-sm font-semibold">4</span><span class="font-semibold">When to send</span></div>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="radio" name="send" value="now" class="accent-brand-600" @checked(old('send', 'now') === 'now')> Send now</label>
                <label class="flex items-center gap-2"><input type="radio" name="send" value="later" class="accent-brand-600" @checked(old('send') === 'later')> Schedule for</label>
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="border border-slate-200 rounded-xl px-3 py-2">
                <span class="text-xs text-slate-400">Timezone: {{ config('app.timezone') }}</span>
            </div>
        </section>

        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <x-btn class="w-full md:w-auto px-8 py-3" icon="send">Launch campaign</x-btn>
            <span class="text-xs text-slate-500">Opted-out and blacklisted numbers are skipped automatically.</span>
        </div>
    </div>

    <div class="xl:sticky xl:top-28 self-start">
        <div class="text-sm font-semibold text-slate-600 mb-3 text-center">Live preview</div>
        <x-phone-preview :name="$accounts->first()?->verified_name ?? 'Your Business'" :number="$accounts->first()?->display_phone_number">
            <div id="preview"></div>
        </x-phone-preview>
        <div class="mt-5 rounded-2xl bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600 text-white p-5 shadow-lg">
            <div class="text-xs font-semibold uppercase tracking-wider text-brand-200">Estimate</div>
            <div class="flex items-end justify-between mt-2">
                <div><div class="text-2xl font-bold" id="est-count">0</div><div class="text-xs text-brand-200">recipients</div></div>
                <div class="text-right"><div class="text-2xl font-bold" id="est-cost">₹0</div><div class="text-xs text-brand-200">approx. Meta charge</div></div>
            </div>
            <div class="text-[11px] text-brand-200 mt-3" id="est-note">Meta bills your WhatsApp account directly, about <span id="est-rate"></span> per message for this template's category. Final count excludes opted-out numbers.</div>
        </div>
    </div>
</form>

<template id="var-options">@include('campaigns.var-options')</template>
@endif
@endsection

@push('scripts')
<script>
    const templates = @json($templateMeta);
    const oldBody = @json(old('variables.body', $from?->variables['body'] ?? []));
    const subscribedTotal = @json($subscribedTotal);
    const select = document.getElementById('template');
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const sample = { name: 'Ravi', phone: '919876543210', email: 'ravi@example.com' };

    function bubble(t) {
        let body = t.body;
        document.querySelectorAll('#body-vars .var-row').forEach((row, i) => {
            const type = row.querySelector('select').value, val = row.querySelector('.v').value, fb = row.querySelector('.f').value;
            const shown = type === 'text' ? val : (type === 'field' ? (val ? '[' + val + ']' : '') : sample[type]);
            body = body.split('\x7b\x7b' + (i + 1) + '\x7d\x7d').join(shown || fb || '\x7b\x7b' + (i + 1) + '\x7d\x7d');
        });
        const media = ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(t.header) ? `<div class="h-28 bg-slate-200 grid place-items-center text-xs text-slate-500">${t.header}</div>` : '';
        return `<div class="max-w-[92%] rounded-lg rounded-tl-none bg-white shadow text-[13px] overflow-hidden">${media}
            <div class="px-2.5 pt-2 pb-1">${t.headerText ? `<div class="font-semibold mb-1">${esc(t.headerText)}</div>` : ''}
            <div class="whitespace-pre-line break-words">${esc(body)}</div>
            ${t.footer ? `<div class="text-[11px] text-slate-500 mt-1">${esc(t.footer)}</div>` : ''}
            <div class="text-right text-[10px] text-slate-400">now</div></div>
            ${(t.buttons || []).map(b => `<div class="border-t text-center py-1.5 text-sky-600 font-medium">${esc(b)}</div>`).join('')}</div>`;
    }

    function render() {
        const t = templates[select?.value];
        if (!t) return;
        document.getElementById('header-media').classList.toggle('hidden', !['IMAGE', 'VIDEO', 'DOCUMENT'].includes(t.header));
        document.getElementById('header-var').classList.toggle('hidden', !t.headerVars);
        document.getElementById('no-vars').classList.toggle('hidden', t.vars > 0 || t.headerVars > 0);
        const box = document.getElementById('body-vars');
        box.innerHTML = '';
        for (let i = 0; i < t.vars; i++) {
            const row = document.createElement('div');
            row.className = 'var-row grid grid-cols-1 md:grid-cols-4 gap-2 items-center';
            row.innerHTML = `<span class="text-sm font-medium">Variable <code class="text-brand-700">{{ '{' }}{${i + 1}}}</code></span>
                <select name="variables[body][${i}][type]" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">${document.getElementById('var-options').innerHTML}</select>
                <input name="variables[body][${i}][value]" placeholder="Column name or text" class="v border border-slate-200 rounded-xl px-2 py-2 text-sm">
                <input name="variables[body][${i}][fallback]" placeholder="If empty, use…" class="f border border-slate-200 rounded-xl px-2 py-2 text-sm">`;
            if (oldBody[i]) {
                row.querySelector('select').value = oldBody[i].type;
                row.querySelector('.v').value = oldBody[i].value || '';
                row.querySelector('.f').value = oldBody[i].fallback || '';
            }
            row.addEventListener('input', () => preview(t));
            box.appendChild(row);
        }
        preview(t);
    }
    const preview = t => document.getElementById('preview').innerHTML = bubble(t);
    select?.addEventListener('change', render);
    render();

    document.querySelectorAll('.aud-opt input').forEach(r => r.addEventListener('change', () => {
        document.querySelectorAll('.aud-opt').forEach(l => l.className = 'aud-opt cursor-pointer flex items-center gap-2 rounded-xl border px-3 py-3 text-sm font-medium ' + (l.querySelector('input').checked ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200'));
        document.querySelectorAll('.aud-panel').forEach(p => p.classList.toggle('hidden', p.dataset.aud !== r.value));
        document.getElementById('upload-extra').classList.toggle('hidden', !['paste', 'csv'].includes(r.value));
    }));
    const numbers = document.getElementById('numbers'), counter = document.getElementById('numbers-count');
    const pasted = () => numbers ? (numbers.value.match(/\+?\d[\d\s\-().]{6,}\d/g) || []).length : 0;
    if (numbers) { const c = () => { counter.textContent = pasted().toLocaleString('en-IN'); estimate(); }; numbers.addEventListener('input', c); c(); }

    function estimate() {
        const t = templates[select?.value];
        const aud = document.querySelector('input[name=audience]:checked')?.value;
        let count = 0, approx = false;
        if (aud === 'all') count = subscribedTotal;
        else if (aud === 'paste') count = pasted();
        else if (aud === 'groups' || aud === 'tags') {
            const boxes = [...document.querySelectorAll('.aud-panel[data-aud="' + aud + '"] .aud-count:checked')];
            count = boxes.reduce((n, b) => n + Number(b.dataset.count || 0), 0);
            approx = boxes.length > 1; // a contact can be in more than one list or tag
        }
        const rate = t ? t.rate : 0;
        document.getElementById('est-count').textContent = (approx ? 'up to ' : '') + (aud === 'csv' ? '—' : count.toLocaleString('en-IN'));
        document.getElementById('est-cost').textContent = aud === 'csv' ? '—' : '₹' + (count * rate).toLocaleString('en-IN', { maximumFractionDigits: 0 });
        document.getElementById('est-rate').textContent = '₹' + rate.toFixed(2);
    }
    document.querySelectorAll('.aud-count').forEach(b => b.addEventListener('change', estimate));
    document.querySelectorAll('.aud-opt input').forEach(r => r.addEventListener('change', estimate));
    select?.addEventListener('change', estimate);
    estimate();
</script>
@endpush
