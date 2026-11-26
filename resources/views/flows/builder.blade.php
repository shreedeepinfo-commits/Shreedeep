@extends('layouts.app', ['title' => $flow->exists ? 'Edit flow' : 'New flow'])

@push('head')
<style>
    .tree ul { display: flex; justify-content: center; gap: 1.25rem; padding-top: 1.5rem; position: relative; }
    .tree li { list-style: none; position: relative; display: flex; flex-direction: column; align-items: center; }
    /* connector lines */
    .tree ul::before { content: ''; position: absolute; top: 0; left: 50%; height: 1.5rem; border-left: 2px solid #aecdef; }
    .tree li::before, .tree li::after { content: ''; position: absolute; top: 0; height: 2px; background: #aecdef; width: 50%; }
    .tree li::before { right: 50%; margin-top: -1.5rem; } .tree li::after { left: 50%; margin-top: -1.5rem; }
    .tree li:first-child::before, .tree li:last-child::after { display: none; }
    .tree li:only-child::before, .tree li:only-child::after { display: none; }
    .tree li > .edge { position: relative; margin-top: -1.5rem; height: 1.5rem; border-left: 2px solid #aecdef; }
    .tree > ul::before { display: none; }
    .tree > ul { padding-top: 0; }
</style>
@endpush

@section('content')
<form method="POST" action="{{ $flow->exists ? route('flows.update', $flow) : route('flows.store') }}" id="flow-form">
    @csrf
    @if ($flow->exists) @method('PUT') @endif
    <input type="hidden" name="definition" id="definition">
    <input type="hidden" name="status" id="status" value="{{ old('status', $flow->status) }}">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-4 flex flex-col xl:flex-row xl:items-end gap-3">
        <a href="{{ route('flows.index') }}" class="text-sm text-slate-500 hover:text-brand-700 xl:self-center">‹ Flows</a>
        <label class="flex-1"><span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Flow name</span>
            <input name="name" value="{{ old('name', $flow->name) }}" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2"></label>
        <label class="flex-1"><span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Trigger keywords (comma separated)</span>
            <input name="triggers" value="{{ old('triggers', implode(', ', $flow->triggers ?? [])) }}" required placeholder="hi, hello, property" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2"></label>
        <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Number</span>
            <select name="whatsapp_account_id" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2">
                <option value="">All numbers</option>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('whatsapp_account_id', $flow->whatsapp_account_id) == $a->id)>{{ $a->display_phone_number ?? $a->phone_number_id }}</option>@endforeach
            </select></label>
        <div class="flex gap-2">
            <button type="submit" data-status="draft" class="save-btn inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold border border-slate-300 bg-white hover:border-brand-400">Save draft</button>
            <button type="submit" data-status="active" class="save-btn inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold bg-brand-600 hover:bg-brand-700 text-white"><x-icon name="play" class="w-4 h-4" /> Save &amp; go live</button>
        </div>
    </div>
    <div id="client-errors" class="hidden mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm"></div>
</form>

<div class="grid grid-cols-1 2xl:grid-cols-12 gap-4">
    {{-- Canvas --}}
    <div class="2xl:col-span-6 bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
            <div class="font-semibold">Flow map</div>
            <div class="flex gap-2">
                <button type="button" id="add-step" class="text-sm rounded-lg border border-slate-200 px-3 py-1.5 hover:border-brand-400"><span class="font-bold">+</span> Add step</button>
            </div>
        </div>
        <div class="overflow-auto p-6 bg-[radial-gradient(#e2e8f0_1px,transparent_1px)] [background-size:16px_16px] min-h-[560px]">
            <div id="canvas" class="tree w-max min-w-full"></div>
            <div id="orphans" class="mt-8"></div>
        </div>
    </div>

    {{-- Step editor --}}
    <div class="2xl:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm self-start 2xl:sticky 2xl:top-28">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold">Edit step</div>
        <div id="editor" class="p-5 space-y-4 text-sm"></div>
    </div>

    {{-- Simulator --}}
    <div class="2xl:col-span-3 self-start 2xl:sticky 2xl:top-28">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold text-slate-600">Test your flow</div>
            <button type="button" id="sim-restart" class="text-xs rounded-lg border border-slate-200 bg-white px-2.5 py-1 hover:border-brand-400">↺ Restart</button>
        </div>
        <x-phone-preview :name="$accounts->first()?->verified_name ?? 'Your Business'" :number="$accounts->first()?->display_phone_number">
            <div id="sim" class="space-y-2"></div>
        </x-phone-preview>
        <form id="sim-form" class="mt-3 flex gap-2">
            <input id="sim-input" placeholder="Type a reply, e.g. hi" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm">
            <button class="rounded-xl bg-brand-600 text-white px-3 text-sm font-semibold">Send</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const TYPES = {
    message: { label: 'Message', color: 'bg-sky-50 text-sky-700', hint: 'Sends text and continues to the next step.' },
    buttons: { label: 'Buttons', color: 'bg-brand-50 text-brand-700', hint: 'Up to 3 reply buttons (20 characters each).' },
    list: { label: 'List menu', color: 'bg-violet-50 text-violet-700', hint: 'A menu with up to 10 options (24 characters each).' },
    question: { label: 'Question', color: 'bg-amber-50 text-amber-700', hint: 'Waits for a typed answer and saves it on the contact.' },
    handoff: { label: 'Hand over', color: 'bg-rose-50 text-rose-700', hint: 'Ends the bot; your team replies in Live Chat.' },
    end: { label: 'End', color: 'bg-emerald-50 text-emerald-700', hint: 'Last message of the flow.' },
};
let def = @json(old('definition') ? json_decode(old('definition'), true) : $flow->definition);
let selected = def.start;
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
const newId = () => 'n' + Math.random().toString(36).slice(2, 8);
const isChoice = n => n && (n.type === 'buttons' || n.type === 'list');
const short = (s, n = 60) => { s = String(s || ''); return s.length > n ? s.slice(0, n) + '…' : s; };

function children(node) {
    if (!node) return [];
    if (isChoice(node)) return (node.options || []).map(o => ({ label: o.title, next: o.next }));
    if (node.type === 'message' || node.type === 'question') return node.next ? [{ label: node.type === 'question' ? 'answer' : 'then', next: node.next }] : [];
    return [];
}

function card(id) {
    const n = def.nodes[id], t = TYPES[n.type] || TYPES.message;
    const opts = isChoice(n) ? `<div class="flex flex-wrap gap-1 mt-2">${n.options.map(o => `<span class="text-[11px] px-2 py-0.5 rounded-full border border-brand-200 text-brand-700 bg-white">${esc(o.title || '—')}</span>`).join('')}</div>` : '';
    const extra = n.type === 'question' ? `<div class="text-[11px] text-slate-500 mt-1">saves → <code>${esc(n.save_as || '?')}</code></div>` : '';
    return `<button type="button" data-node="${id}" class="node w-56 text-left rounded-2xl border-2 ${id === selected ? 'border-brand-600 ring-4 ring-brand-100' : 'border-slate-200 hover:border-brand-300'} bg-white shadow-sm p-3">
        <div class="flex items-center justify-between gap-2">
            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded ${t.color}">${t.label}</span>
            ${id === def.start ? '<span class="text-[10px] font-bold uppercase text-emerald-600">● Start</span>' : ''}
        </div>
        <div class="text-[13px] text-slate-700 mt-2 whitespace-pre-line">${esc(short(n.text, 90)) || '<span class="text-slate-400">No message yet</span>'}</div>${opts}${extra}</button>`;
}

function renderTree(id, seen) {
    if (!id || !def.nodes[id]) return '<div class="w-40 text-center text-xs text-slate-400 border-2 border-dashed border-slate-200 rounded-xl py-2 bg-white">End of flow</div>';
    if (seen.has(id)) return `<button type="button" data-node="${id}" class="node w-44 text-xs rounded-xl border-2 border-dashed border-brand-300 bg-brand-50 text-brand-700 py-2 px-3">↪ Goes to: ${esc(short(def.nodes[id].text, 28))}</button>`;
    seen.add(id);
    const kids = children(def.nodes[id]);
    let html = card(id);
    if (kids.length) {
        html += '<ul>' + kids.map(k => `<li><div class="edge"></div><span class="mb-2 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-brand-600 text-white relative z-10">${esc(short(k.label, 20))}</span>${renderTree(k.next, seen)}</li>`).join('') + '</ul>';
    }
    return html;
}

function render() {
    const seen = new Set();
    document.getElementById('canvas').innerHTML = `<ul><li>${renderTree(def.start, seen)}</li></ul>`;
    const orphans = Object.keys(def.nodes).filter(id => !seen.has(id));
    document.getElementById('orphans').innerHTML = orphans.length
        ? `<div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Not connected yet (link them from an option's "Go to")</div><div class="flex flex-wrap gap-3">${orphans.map(card).join('')}</div>` : '';
    document.querySelectorAll('[data-node]').forEach(el => el.addEventListener('click', () => { selected = el.dataset.node; render(); }));
    renderEditor();
    validate();
}

function stepOptions(current) {
    return `<option value="">— End flow —</option>` + Object.keys(def.nodes).map(id =>
        `<option value="${id}" ${id === current ? 'selected' : ''}>${esc(TYPES[def.nodes[id].type]?.label)}: ${esc(short(def.nodes[id].text, 32))}</option>`).join('') + `<option value="__new">➕ New step…</option>`;
}

function renderEditor() {
    const box = document.getElementById('editor'), n = def.nodes[selected];
    if (!n) { box.innerHTML = '<div class="text-slate-500">Click a step on the map to edit it.</div>'; return; }
    const max = n.type === 'buttons' ? 3 : 10, tmax = n.type === 'buttons' ? 20 : 24;
    box.innerHTML = `
        <label class="block"><span class="font-medium">Step type</span>
            <select data-f="type" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2">${Object.entries(TYPES).map(([k, v]) => `<option value="${k}" ${k === n.type ? 'selected' : ''}>${v.label}</option>`).join('')}</select>
            <span class="text-xs text-slate-500">${TYPES[n.type].hint}</span></label>
        <label class="block"><span class="font-medium">Message</span>
            <textarea data-f="text" rows="4" maxlength="1024" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2">${esc(n.text)}</textarea></label>
        ${n.type === 'list' ? `<label class="block"><span class="font-medium">Menu button text</span><input data-f="button" maxlength="20" value="${esc(n.button || 'Choose')}" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2"></label>` : ''}
        ${n.type === 'question' ? `<label class="block"><span class="font-medium">Save answer as</span><input data-f="save_as" value="${esc(n.save_as || '')}" placeholder="budget" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 font-mono"><span class="text-xs text-slate-500">Saved on the contact; usable as a campaign variable.</span></label>` : ''}
        ${isChoice(n) ? `<div><div class="flex items-center justify-between"><span class="font-medium">Options (${n.options.length}/${max})</span>
            ${n.options.length < max ? '<button type="button" id="add-opt" class="text-xs text-brand-700 font-semibold">+ Add option</button>' : ''}</div>
            <div class="space-y-2 mt-2">${n.options.map((o, i) => `<div class="rounded-xl border border-slate-200 p-2 space-y-1.5">
                <div class="flex gap-1"><input data-o="${i}" data-k="title" maxlength="${tmax}" value="${esc(o.title)}" placeholder="Option title" class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5">
                <button type="button" data-del-opt="${i}" class="px-2 text-slate-400 hover:text-red-600" title="Remove">×</button></div>
                ${n.type === 'list' ? `<input data-o="${i}" data-k="description" maxlength="72" value="${esc(o.description || '')}" placeholder="Description (optional)" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs">` : ''}
                <select data-o="${i}" data-k="next" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs">${stepOptions(o.next)}</select></div>`).join('')}</div></div>` : ''}
        ${(n.type === 'message' || n.type === 'question') ? `<label class="block"><span class="font-medium">Then go to</span><select data-f="next" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2">${stepOptions(n.next)}</select></label>` : ''}
        <div class="flex gap-2 pt-2 border-t border-slate-100">
            ${selected !== def.start ? '<button type="button" id="make-start" class="text-xs rounded-lg border border-slate-200 px-3 py-1.5">Set as start</button>' : ''}
            <button type="button" id="del-step" class="text-xs rounded-lg border border-red-200 text-red-600 px-3 py-1.5 ml-auto">Delete step</button>
        </div>`;

    box.querySelectorAll('[data-f]').forEach(el => el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', () => {
        const f = el.dataset.f;
        if (f === 'next' && el.value === '__new') { n.next = addNode(); selected = n.next; return render(); }
        n[f] = el.value || (f === 'next' ? null : '');
        if (f === 'type') {
            if (isChoice(n) && !n.options?.length) n.options = [{ title: 'Yes', next: null }, { title: 'No', next: null }];
            if (n.type === 'buttons' && n.options.length > 3) n.options = n.options.slice(0, 3);
            if (n.type === 'question' && !n.save_as) n.save_as = 'answer';
            return render();
        }
        lightRender();
    }));
    box.querySelectorAll('[data-o]').forEach(el => el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', () => {
        const o = n.options[+el.dataset.o];
        if (el.dataset.k === 'next' && el.value === '__new') { o.next = addNode(); selected = o.next; return render(); }
        o[el.dataset.k] = el.value || (el.dataset.k === 'next' ? null : '');
        el.tagName === 'SELECT' ? render() : lightRender();
    }));
    box.querySelectorAll('[data-del-opt]').forEach(el => el.addEventListener('click', () => { n.options.splice(+el.dataset.delOpt, 1); render(); }));
    box.querySelector('#add-opt')?.addEventListener('click', () => { n.options.push({ title: 'Option ' + (n.options.length + 1), next: null }); render(); });
    box.querySelector('#make-start')?.addEventListener('click', () => { def.start = selected; render(); });
    box.querySelector('#del-step').addEventListener('click', () => {
        if (Object.keys(def.nodes).length === 1) return alert('A flow needs at least one step.');
        if (!confirm('Delete this step? Links to it will end the flow instead.')) return;
        delete def.nodes[selected];
        Object.values(def.nodes).forEach(x => { if (x.next === selected) x.next = null; (x.options || []).forEach(o => { if (o.next === selected) o.next = null; }); });
        if (def.start === selected) def.start = Object.keys(def.nodes)[0];
        selected = def.start; render();
    });
}

// Re-draw the map without rebuilding the editor (keeps typing focus).
function lightRender() {
    const seen = new Set();
    document.getElementById('canvas').innerHTML = `<ul><li>${renderTree(def.start, seen)}</li></ul>`;
    document.querySelectorAll('#canvas [data-node]').forEach(el => el.addEventListener('click', () => { selected = el.dataset.node; render(); }));
    validate();
}

function addNode(type = 'message') {
    const id = newId();
    def.nodes[id] = { type, text: '', next: null };
    return id;
}
document.getElementById('add-step').addEventListener('click', () => { selected = addNode(); render(); });

function validate() {
    const errs = [];
    Object.entries(def.nodes).forEach(([id, n]) => {
        const name = `"${short(n.text, 25) || 'untitled step'}"`;
        if (!String(n.text || '').trim()) errs.push(`Step ${name} needs a message.`);
        if (isChoice(n)) {
            const max = n.type === 'buttons' ? 3 : 10, tmax = n.type === 'buttons' ? 20 : 24;
            if (!n.options.length || n.options.length > max) errs.push(`Step ${name} needs 1 to ${max} options.`);
            n.options.forEach(o => { if (!String(o.title || '').trim()) errs.push(`Step ${name} has an empty option.`); else if (o.title.length > tmax) errs.push(`Option "${o.title}" is longer than ${tmax} characters.`); });
        }
        if (n.type === 'question' && !/^[a-z][a-z0-9_]{0,40}$/.test(n.save_as || '')) errs.push(`Step ${name}: "save answer as" must be lowercase like budget.`);
    });
    const box = document.getElementById('client-errors');
    box.classList.toggle('hidden', !errs.length);
    box.innerHTML = [...new Set(errs)].map(esc).join('<br>');
    return errs.length === 0;
}

document.querySelectorAll('.save-btn').forEach(b => b.addEventListener('click', () => document.getElementById('status').value = b.dataset.status));
document.getElementById('flow-form').addEventListener('submit', e => {
    if (!validate()) { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    document.getElementById('definition').value = JSON.stringify(def);
});

// ---------- Simulator ----------
let simNode = null;
const sim = document.getElementById('sim');
const bubble = (html, me) => { sim.insertAdjacentHTML('beforeend', `<div class="flex ${me ? 'justify-end' : ''}"><div class="max-w-[88%] rounded-lg ${me ? 'rounded-tr-none bg-[#d9fdd3]' : 'rounded-tl-none bg-white'} shadow px-2.5 py-1.5 text-[13px] whitespace-pre-line">${html}</div></div>`); sim.parentElement.scrollTop = 1e9; };
function simRun(id) {
    for (let i = 0; i < 10; i++) {
        const n = def.nodes[id];
        if (!n) { simNode = null; bubble('<span class="text-slate-400 text-xs">— flow ended —</span>'); return; }
        let html = esc(n.text);
        if (isChoice(n)) html += `<div class="mt-2 border-t pt-1 space-y-1">${n.options.map((o, j) => `<button type="button" data-sim="${j}" class="block w-full text-center text-sky-600 font-medium py-0.5 hover:bg-sky-50 rounded">${n.type === 'list' ? '☰ ' : ''}${esc(o.title)}</button>`).join('')}</div>`;
        bubble(html);
        simNode = id;
        if (isChoice(n) || n.type === 'question') return wireSim();
        if (n.type === 'end' || n.type === 'handoff') { simNode = null; bubble(`<span class="text-slate-400 text-xs">— ${n.type === 'handoff' ? 'handed over to your team' : 'flow ended'} —</span>`); return; }
        id = n.next;
    }
}
function wireSim() {
    sim.querySelectorAll('[data-sim]').forEach(b => b.onclick = () => simAnswer(def.nodes[simNode]?.options[+b.dataset.sim]?.title));
}
function simAnswer(text) {
    if (!text) return;
    bubble(esc(text), true);
    const n = def.nodes[simNode];
    if (!n) {
        if (!document.querySelector('[name=triggers]').value.split(',').map(t => t.trim().toLowerCase()).includes(text.trim().toLowerCase())) return bubble('<span class="text-slate-400 text-xs">(not a trigger keyword, so the bot stays quiet)</span>');
        return simRun(def.start);
    }
    if (isChoice(n)) {
        const i = n.options.findIndex((o, j) => o.title.toLowerCase() === text.trim().toLowerCase() || String(j + 1) === text.trim());
        if (i < 0) { bubble('Please choose one of the options below.'); return simRun(simNode); }
        return simRun(n.options[i].next);
    }
    if (n.type === 'question') return simRun(n.next);
}
document.getElementById('sim-form').addEventListener('submit', e => { e.preventDefault(); const v = document.getElementById('sim-input'); simAnswer(v.value); v.value = ''; });
document.getElementById('sim-restart').addEventListener('click', () => { sim.innerHTML = ''; simNode = null; bubble('<span class="text-slate-400 text-xs">Type a trigger keyword (e.g. ' + esc(document.querySelector('[name=triggers]').value.split(',')[0]) + ') to start.</span>'); });

render();
document.getElementById('sim-restart').click();
</script>
@endpush
