@php
    $type = $automation->type;
    $c = $automation->config ?? [];
    [$typeLabel, $typeDesc, $typeIcon] = \App\Models\Automation::TYPES[$type];
    $input = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none';
    $label = 'block text-sm font-medium text-slate-700 mb-1.5';
    $card = 'bg-white rounded-2xl border border-slate-200 shadow-sm p-6';
    $dateTemplate = [
        'template_id' => old('template_id', $c['template_id'] ?? null),
        'header_media_url' => old('header_media_url', $c['header_media_url'] ?? null),
        'variables' => old('variables', $c['variables'] ?? []),
    ];
    $dripSteps = array_values(old('steps', $c['steps'] ?? []));
    $zones = ['Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Asia/Kathmandu', 'Asia/Dhaka', 'Europe/London', 'America/New_York', 'UTC'];
@endphp
@extends('layouts.app', ['title' => $typeLabel])

@section('content')
<x-page-header :title="($automation->exists ? 'Edit ' : 'New ').strtolower($typeLabel)" :subtitle="$typeDesc">
    <x-btn :href="route('automations.index')" variant="outline">← All automations</x-btn>
</x-page-header>

<form method="POST" action="{{ $automation->exists ? route('automations.update', $automation) : route('automations.store') }}" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    @csrf
    @if ($automation->exists) @method('PUT') @else <input type="hidden" name="type" value="{{ $type }}"> @endif

    <div class="xl:col-span-2 space-y-6">
        <div class="{{ $card }}">
            <div class="flex items-center gap-3 mb-5">
                <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon :name="$typeIcon" class="w-5 h-5" /></span>
                <div class="flex-1">
                    <label class="{{ $label }}">Name</label>
                    <input name="name" value="{{ old('name', $automation->name) }}" required maxlength="80" class="{{ $input }}">
                </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-medium">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', $automation->active)) class="rounded text-brand-600"> Turn on
            </label>
        </div>

        @if (in_array($type, ['welcome', 'away', 'keyword']))
            <div class="{{ $card }} space-y-4">
                @if ($type === 'keyword')
                    <div>
                        <label class="{{ $label }}">Keywords</label>
                        <input name="keywords" value="{{ old('keywords', implode(', ', $c['keywords'] ?? [])) }}" required placeholder="price, rate, cost" class="{{ $input }}">
                        <p class="text-xs text-slate-500 mt-1">Separate with commas. Not case-sensitive.</p>
                    </div>
                    <div>
                        <label class="{{ $label }}">When</label>
                        <div class="flex gap-3">
                            @foreach (['contains' => 'Message contains the word', 'exact' => 'Message is exactly the word'] as $v => $l)
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm cursor-pointer has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="match" value="{{ $v }}" @checked(old('match', $c['match'] ?? 'contains') === $v) class="text-brand-600"> {{ $l }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div>
                    <label class="{{ $label }}">{{ $type === 'keyword' ? 'Reply' : 'Message' }}</label>
                    <textarea id="msg" name="{{ $type === 'keyword' ? 'reply' : 'message' }}" rows="5" required maxlength="4096" class="{{ $input }}">{{ old($type === 'keyword' ? 'reply' : 'message', $c[$type === 'keyword' ? 'reply' : 'message'] ?? '') }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">Use <code>@{{name}}</code> for the customer's name. Sent as a normal chat message (free inside the 24-hour window).</p>
                </div>
            </div>
        @endif

        @if ($type === 'away')
            <div class="{{ $card }}">
                <div class="flex items-center justify-between mb-4">
                    <div class="font-semibold">Business hours</div>
                    <select name="timezone" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                        @foreach (array_unique(array_merge($zones, [$c['timezone'] ?? 'Asia/Kolkata'])) as $z)<option @selected(old('timezone', $c['timezone'] ?? 'Asia/Kolkata') === $z)>{{ $z }}</option>@endforeach
                    </select>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach (\App\Models\Automation::DAYS as $d => $dayName)
                        @php $h = old("hours.$d", $c['hours'][$d] ?? ['open' => false, 'from' => '10:00', 'to' => '19:00']); @endphp
                        <div class="flex flex-wrap items-center gap-3 py-2.5">
                            <label class="w-36 flex items-center gap-2 text-sm font-medium">
                                <input type="hidden" name="hours[{{ $d }}][open]" value="0">
                                <input type="checkbox" name="hours[{{ $d }}][open]" value="1" @checked(! empty($h['open'])) class="rounded text-brand-600"> {{ $dayName }}
                            </label>
                            <input type="time" name="hours[{{ $d }}][from]" value="{{ $h['from'] ?? '10:00' }}" class="border border-slate-200 rounded-xl px-3 py-1.5 text-sm">
                            <span class="text-slate-400 text-sm">to</span>
                            <input type="time" name="hours[{{ $d }}][to]" value="{{ $h['to'] ?? '19:00' }}" class="border border-slate-200 rounded-xl px-3 py-1.5 text-sm">
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-slate-500 mt-3">Outside these hours, customers get the away message (at most once every {{ \App\Services\Automations\AutomationEngine::AWAY_EVERY_HOURS }} hours each).</p>
            </div>
        @endif

        @if ($type === 'drip')
            <div class="{{ $card }}">
                <div class="font-semibold mb-1">Who gets this sequence</div>
                <p class="text-xs text-slate-500 mb-4">Leave lists and tags empty to include every subscribed contact. Opted-out and blacklisted numbers are always skipped.</p>
                @php $selGroups = array_map('intval', old('groups', $c['audience']['groups'] ?? [])); @endphp
                <label class="{{ $label }}">Contact lists</label>
                <div class="flex flex-wrap gap-2 mb-4">
                    @forelse ($groups as $g)
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                            <input type="checkbox" name="groups[]" value="{{ $g->id }}" @checked(in_array($g->id, $selGroups)) class="rounded text-brand-600"> {{ $g->name }} <span class="text-xs text-slate-400">{{ $g->contacts_count }}</span>
                        </label>
                    @empty
                        <span class="text-sm text-slate-500">No lists yet.</span>
                    @endforelse
                </div>
                <label class="{{ $label }}">Tags (any of)</label>
                <input name="tags" value="{{ old('tags', implode(', ', $c['audience']['tags'] ?? [])) }}" placeholder="lead, website" class="{{ $input }}">
                <label class="flex items-center gap-2 text-sm mt-4">
                    <input type="hidden" name="enroll_new" value="0">
                    <input type="checkbox" name="enroll_new" value="1" @checked(old('enroll_new', $c['enroll_new'] ?? true)) class="rounded text-brand-600">
                    Also add new contacts who match later
                </label>
                @if ($audienceCount !== null)<p class="text-xs text-brand-700 mt-3">{{ \App\Support\Num::indian($audienceCount) }} contacts match right now.</p>@endif
            </div>

            <div class="{{ $card }}">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="font-semibold">Steps</div>
                        <p class="text-xs text-slate-500">Each step sends an approved template. The wait counts from the previous step (the first from when the contact joins).</p>
                    </div>
                    <button type="button" id="add-step" class="text-sm font-semibold text-brand-700 hover:text-brand-800">+ Add step</button>
                </div>
                <div id="steps" class="space-y-4"></div>
            </div>
        @endif

        @if ($type === 'date')
            <div class="{{ $card }} space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">Date field on the contact</label>
                        <input name="field" list="field-list" value="{{ old('field', $c['field'] ?? 'birthday') }}" required class="{{ $input }}">
                        <datalist id="field-list">@foreach ($fields->merge(['birthday', 'anniversary', 'appointment', 'due_date'])->unique() as $f)<option value="{{ $f }}">@endforeach</datalist>
                        <p class="text-xs text-slate-500 mt-1">A column from your contact import, like birthday (15-08-1990 or 1990-08-15).</p>
                    </div>
                    <div>
                        <label class="{{ $label }}">Send</label>
                        <select name="days_before" class="{{ $input }}">
                            @foreach ([0 => 'On the day', 1 => '1 day before', 2 => '2 days before', 3 => '3 days before', 7 => '1 week before'] as $v => $l)
                                <option value="{{ $v }}" @selected((int) old('days_before', $c['days_before'] ?? 0) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">At</label>
                        <input type="time" name="time" value="{{ old('time', $c['time'] ?? '09:00') }}" required class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Timezone</label>
                        <select name="timezone" class="{{ $input }}">
                            @foreach (array_unique(array_merge($zones, [$c['timezone'] ?? 'Asia/Kolkata'])) as $z)<option @selected(old('timezone', $c['timezone'] ?? 'Asia/Kolkata') === $z)>{{ $z }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="yearly" value="0">
                    <input type="checkbox" name="yearly" value="1" @checked(old('yearly', $c['yearly'] ?? true)) class="rounded text-brand-600">
                    Every year (birthdays, anniversaries). Untick for one-time dates like appointments or payment due dates.
                </label>
            </div>
            <div class="{{ $card }}">
                <div class="font-semibold mb-4">Message</div>
                <div id="date-template"></div>
            </div>
        @endif

        <div class="flex gap-3">
            <x-btn icon="check">Save</x-btn>
            <x-btn :href="route('automations.index')" variant="outline">Cancel</x-btn>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="{{ $card }}">
            <div class="font-semibold mb-3">Preview</div>
            <div class="rounded-2xl bg-[#e5ddd5] p-4 min-h-[180px]">
                <div id="preview" class="max-w-[92%] rounded-lg rounded-tl-none bg-white shadow px-2.5 py-2 text-[13px] whitespace-pre-line break-words">…</div>
            </div>
        </div>
        <div class="rounded-2xl bg-brand-50 border border-brand-100 p-5 text-sm text-brand-900 space-y-2">
            <div class="font-semibold">Good to know</div>
            @if (in_array($type, ['drip', 'date']))
                <p>WhatsApp only allows approved templates for messages you start. Each one is charged by Meta at the template's category rate.</p>
                <p>Messages go out through the WhatsApp number the template belongs to.</p>
            @else
                <p>Replies go out instantly when a customer writes to you, and show up in Live Chat.</p>
                <p>A running chatbot flow always answers first; this reply is used when no flow matches.</p>
            @endif
        </div>
    </aside>
</form>

@if (in_array($type, ['drip', 'date']))
    <template id="var-options">@include('campaigns.var-options')</template>
@endif
@endsection

@push('scripts')
<script>
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const previewBox = document.getElementById('preview');
    const sampleName = 'Ravi';

    @if (in_array($type, ['welcome', 'away', 'keyword']))
        const msg = document.getElementById('msg');
        const showText = () => previewBox.textContent = (msg.value || '…').split('\x7b\x7bname\x7d\x7d').join(sampleName);
        msg.addEventListener('input', showText);
        showText();
    @else
        const templates = @json($templateMeta);
        const opts = Object.entries(templates).map(([id, t]) => `<option value="${id}">${esc(t.name)}</option>`).join('');

        // Field name, e.g. nm('steps[0]', 'variables', 'body', 1, 'type') → steps[0][variables][body][1][type]
        const nm = (prefix, ...parts) => prefix ? prefix + '[' + parts.join('][') + ']' : parts[0] + (parts.length > 1 ? '[' + parts.slice(1).join('][') + ']' : '');

        function templateBlock(prefix, data) {
            const wrap = document.createElement('div');
            wrap.className = 'space-y-3';
            wrap.innerHTML = `
                <select name="${nm(prefix, 'template_id')}" class="tpl w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <option value="">Choose an approved template…</option>${opts}</select>
                <input name="${nm(prefix, 'header_media_url')}" placeholder="Header image / video / PDF link (https://…)" class="media hidden w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <div class="hvar hidden grid grid-cols-1 md:grid-cols-3 gap-2 items-center">
                    <span class="text-sm font-medium">Header variable</span>
                    <select name="${nm(prefix, 'variables', 'header', 'type')}" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">${document.getElementById('var-options').innerHTML}</select>
                    <input name="${nm(prefix, 'variables', 'header', 'value')}" placeholder="Column name or text" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">
                </div>
                <div class="vars space-y-2"></div>`;
            const sel = wrap.querySelector('.tpl');
            sel.value = data.template_id || '';
            wrap.querySelector('.media').value = data.header_media_url || '';
            const hv = data.variables?.header;
            if (hv) { wrap.querySelector('.hvar select').value = hv.type || 'text'; wrap.querySelector('.hvar input').value = hv.value || ''; }

            const render = (first) => {
                const t = templates[sel.value];
                wrap.querySelector('.media').classList.toggle('hidden', !t || !['IMAGE', 'VIDEO', 'DOCUMENT'].includes(t.header));
                wrap.querySelector('.hvar').classList.toggle('hidden', !t || !t.headerVars);
                const box = wrap.querySelector('.vars');
                box.innerHTML = '';
                for (let i = 0; i < (t?.vars || 0); i++) {
                    const v = first ? (data.variables?.body?.[i] || {}) : {};
                    const row = document.createElement('div');
                    row.className = 'grid grid-cols-1 md:grid-cols-4 gap-2 items-center';
                    row.innerHTML = `<span class="text-sm font-medium">Variable <code class="text-brand-700">\x7b\x7b${i + 1}\x7d\x7d</code></span>
                        <select name="${nm(prefix, 'variables', 'body', i, 'type')}" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">${document.getElementById('var-options').innerHTML}</select>
                        <input name="${nm(prefix, 'variables', 'body', i, 'value')}" placeholder="Column name or text" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">
                        <input name="${nm(prefix, 'variables', 'body', i, 'fallback')}" placeholder="If empty, use…" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">`;
                    row.querySelector('select').value = v.type || 'name';
                    row.querySelector('input').value = v.value || '';
                    row.querySelectorAll('input')[1].value = v.fallback || '';
                    box.appendChild(row);
                }
                if (t) previewBox.textContent = t.body;
            };
            sel.addEventListener('change', () => render(false));
            sel.addEventListener('focus', () => templates[sel.value] && (previewBox.textContent = templates[sel.value].body));
            render(true);
            return wrap;
        }
    @endif

    @if ($type === 'drip')
        const stepsBox = document.getElementById('steps');
        let stepSeq = 0;
        function addStep(data = {}) {
            const n = stepSeq++;
            const card = document.createElement('div');
            card.className = 'step rounded-2xl border border-slate-200 p-4';
            card.innerHTML = `
                <div class="flex items-center gap-3 mb-3">
                    <span class="num w-7 h-7 rounded-full bg-brand-600 text-white text-xs font-bold grid place-items-center"></span>
                    <span class="text-sm text-slate-600">Wait</span>
                    <input type="number" min="0" max="365" name="steps[${n}][delay_days]" value="${data.delay_days ?? 1}" class="w-16 border border-slate-200 rounded-xl px-2 py-1.5 text-sm"> <span class="text-sm text-slate-600">days</span>
                    <input type="number" min="0" max="23" name="steps[${n}][delay_hours]" value="${data.delay_hours ?? 0}" class="w-16 border border-slate-200 rounded-xl px-2 py-1.5 text-sm"> <span class="text-sm text-slate-600">hours, then send</span>
                    <button type="button" class="rm ml-auto text-xs text-red-600 hover:underline">Remove</button>
                </div>`;
            card.appendChild(templateBlock(`steps[${n}]`, data));
            card.querySelector('.rm').addEventListener('click', () => { if (stepsBox.children.length > 1) { card.remove(); number(); } });
            stepsBox.appendChild(card);
            number();
        }
        const number = () => [...stepsBox.children].forEach((c, i) => c.querySelector('.num').textContent = i + 1);
        document.getElementById('add-step').addEventListener('click', () => stepsBox.children.length < 10 && addStep());
        (@json($dripSteps)).forEach(s => addStep(s));
        if (!stepsBox.children.length) addStep({ delay_days: 0 });
    @endif

    @if ($type === 'date')
        document.getElementById('date-template').appendChild(templateBlock('', @json($dateTemplate)));
    @endif
</script>
@endpush
