@php
    $editing = $template->exists;
    $f = fn ($key, $default = null) => old($key, $form[$key] ?? $default);
    $headerType = $f('header_type', 'none');
    $category = $f('category', 'MARKETING');
    $buttons = old('buttons', $form['buttons'] ?? []);
    if (count($buttons) === 0) { $buttons = [['type' => 'QUICK_REPLY', 'text' => '', 'value' => '', 'example' => '']]; }
@endphp
@extends('layouts.app', ['title' => $editing ? 'Edit template' : 'New template'])

@section('content')
<x-page-header :title="$editing ? 'Edit template: '.$template->name : 'New message template'" subtitle="Meta usually reviews templates within minutes to a few hours.">
    <x-btn :href="route('templates.index', $editing ? ['t' => $template->id] : [])" variant="outline">Cancel</x-btn>
</x-page-header>

@if ($editing && $template->status === 'REJECTED' && $template->rejected_reason)
    <div class="mb-5 rounded-xl bg-red-50 border border-red-100 text-red-800 px-4 py-3 text-sm"><b>Meta rejected it:</b> {{ $template->rejected_reason }}. Fix the content below and submit again.</div>
@endif

@if ($accounts->isEmpty())
    <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 text-amber-800 px-5 py-4 text-sm">
        <b>Connect your WhatsApp number first.</b> Templates are sent to Meta for approval from your own WhatsApp Business account.
        <a href="{{ route('whatsapp.index') }}" class="font-semibold underline">Go to WhatsApp Settings →</a>
        <div class="text-xs mt-1">There are no credits in this panel: template approval is free, and message charges are paid by you directly to Meta.</div>
    </div>
@endif
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
<form method="POST" action="{{ $editing ? route('templates.update', $template) : route('templates.store') }}" id="tpl-form" enctype="multipart/form-data" class="xl:col-span-2 space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif
    <input type="hidden" name="existing_handle" value="{{ $f('header_handle') }}">

    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <div class="font-semibold mb-4">Basics</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block"><span class="text-sm font-medium text-slate-600">WhatsApp number</span>
                <select name="whatsapp_account_id" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5" @disabled($editing) required>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($f('whatsapp_account_id') == $a->id)>{{ $a->label() }}</option>@endforeach
                </select></label>
            <label class="block"><span class="text-sm font-medium text-slate-600">Name (lowercase_with_underscores)</span>
                <input name="name" value="{{ $f('name') }}" placeholder="diwali_offer" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5 font-mono disabled:bg-slate-50" @disabled($editing) required></label>
            <label class="block"><span class="text-sm font-medium text-slate-600">Language</span>
                <select name="language" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5" @disabled($editing)>
                    @foreach ($languages as $code => $label)<option value="{{ $code }}" @selected($f('language', 'en') === $code)>{{ $label }}</option>@endforeach
                </select></label>
            <div>
                <span class="text-sm font-medium text-slate-600">Category</span>
                <div class="grid grid-cols-3 gap-2 mt-1">
                    @foreach (['MARKETING' => ['Marketing', 'Offers, promotions'], 'UTILITY' => ['Utility', 'Orders, updates'], 'AUTHENTICATION' => ['Authentication', 'OTP codes']] as $key => [$label, $hint])
                        <label class="rounded-xl border border-slate-200 px-3 py-2 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 {{ $editing ? 'opacity-60 pointer-events-none' : '' }}">
                            <input type="radio" name="category" value="{{ $key }}" class="sr-only" @checked($category === $key)>
                            <div class="text-sm font-semibold">{{ $label }}</div><div class="text-[11px] text-slate-500">{{ $hint }}</div>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Authentication (OTP) --}}
    <section data-for="auth" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4 {{ $category === 'AUTHENTICATION' ? '' : 'hidden' }}">
        <div class="font-semibold">One-time password</div>
        <p class="text-sm text-slate-500">Meta writes the text: "<b>123456</b> is your verification code." You choose the extras.</p>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="security_recommendation" value="1" class="accent-brand-600" @checked($f('security_recommendation', true))> Add "For your security, do not share this code."</label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block"><span class="text-sm font-medium text-slate-600">Code expires in (minutes, optional)</span>
                <input type="number" name="code_expiration_minutes" min="1" max="90" value="{{ $f('code_expiration_minutes', 10) }}" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5"></label>
            <label class="block"><span class="text-sm font-medium text-slate-600">Copy button text</span>
                <input name="otp_button_text" value="{{ $f('otp_button_text', 'Copy code') }}" maxlength="25" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5"></label>
        </div>
    </section>

    {{-- Content --}}
    <section data-for="content" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4 {{ $category === 'AUTHENTICATION' ? 'hidden' : '' }}">
        <div class="font-semibold">Content</div>
        <div>
            <span class="text-sm font-medium text-slate-600">Header</span>
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach (['none' => ['None', 'text'], 'text' => ['Text', 'text'], 'image' => ['Image', 'image'], 'video' => ['Video', 'play'], 'document' => ['Document', 'document']] as $key => [$label, $icon])
                    <label class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                        <input type="radio" name="header_type" value="{{ $key }}" class="sr-only" @checked($headerType === $key)> <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
                    </label>
                @endforeach
            </div>
            <div data-header="text" class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-3 {{ $headerType === 'text' ? '' : 'hidden' }}">
                <input name="header" value="{{ $f('header') }}" maxlength="60" placeholder="Header text (can use @{{1}})" class="md:col-span-2 border border-slate-200 rounded-xl px-3 py-2.5">
                <input name="header_example" value="{{ $f('header_example') }}" maxlength="60" placeholder="Example for @{{1}}" class="border border-slate-200 rounded-xl px-3 py-2.5">
            </div>
            <div data-header="media" class="mt-3 {{ in_array($headerType, ['image', 'video', 'document']) ? '' : 'hidden' }}">
                <input type="file" name="header_file" id="headerFile" class="text-sm" accept="image/jpeg,image/png,video/mp4,application/pdf">
                <p class="text-xs text-slate-500 mt-1">A sample file for Meta's review (JPG/PNG up to 5 MB, MP4 up to 16 MB, PDF). In campaigns you give the real link.
                    @if ($f('header_handle'))<b class="text-emerald-700">A sample is already uploaded; leave empty to keep it.</b>@endif</p>
            </div>
        </div>
        <label class="block"><span class="text-sm font-medium text-slate-600">Body. Use @{{1}}, @{{2}}… for variables</span>
            <textarea name="body" id="body" rows="5" maxlength="1024" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5">{{ $f('body', $exampleBody) }}</textarea></label>
        <div class="flex flex-wrap gap-2 -mt-2 text-xs">
            <button type="button" class="fmt px-2 py-1 rounded-lg bg-slate-100 font-bold" data-wrap="*">B</button>
            <button type="button" class="fmt px-2 py-1 rounded-lg bg-slate-100 italic" data-wrap="_">I</button>
            <button type="button" class="fmt px-2 py-1 rounded-lg bg-slate-100 line-through" data-wrap="~">S</button>
            <button type="button" id="addVar" class="px-2 py-1 rounded-lg bg-brand-50 text-brand-700 font-semibold">+ Variable</button>
            <span class="ml-auto text-slate-400"><span id="bodyLen">0</span>/1024</span>
        </div>
        <div id="examples" class="grid grid-cols-1 md:grid-cols-3 gap-2"></div>
        <label class="block"><span class="text-sm font-medium text-slate-600">Footer (optional)</span>
            <input name="footer" value="{{ $f('footer', $editing ? null : 'Reply STOP to unsubscribe') }}" maxlength="60" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5"></label>

        <div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-600">Buttons (optional, up to 10)</span>
                <button type="button" id="addButton" class="text-sm font-semibold text-brand-700 hover:underline">+ Add button</button>
            </div>
            <div id="buttons" class="space-y-2 mt-2">
                @foreach ($buttons as $i => $b)
                    <div class="btn-row grid grid-cols-12 gap-2 items-center">
                        <select name="buttons[{{ $i }}][type]" class="b-type col-span-3 border border-slate-200 rounded-xl px-2 py-2 text-sm">
                            @foreach (['QUICK_REPLY' => 'Quick reply', 'URL' => 'Website link', 'PHONE_NUMBER' => 'Call number', 'COPY_CODE' => 'Coupon code'] as $k => $l)<option value="{{ $k }}" @selected(($b['type'] ?? '') === $k)>{{ $l }}</option>@endforeach
                        </select>
                        <input name="buttons[{{ $i }}][text]" value="{{ $b['text'] ?? '' }}" placeholder="Button text" maxlength="25" class="b-text col-span-3 border border-slate-200 rounded-xl px-2 py-2 text-sm">
                        <input name="buttons[{{ $i }}][value]" value="{{ $b['value'] ?? '' }}" placeholder="Link, phone or code" class="b-value col-span-3 border border-slate-200 rounded-xl px-2 py-2 text-sm">
                        <input name="buttons[{{ $i }}][example]" value="{{ $b['example'] ?? '' }}" placeholder="Example for @{{1}} in link" class="b-example col-span-2 border border-slate-200 rounded-xl px-2 py-2 text-sm">
                        <button type="button" class="b-remove col-span-1 text-slate-400 hover:text-red-600 text-lg" title="Remove">×</button>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-btn icon="send">{{ $editing ? 'Submit changes to Meta' : 'Submit to Meta for approval' }}</x-btn>
</form>
<div class="xl:sticky xl:top-28 self-start">
    <div class="text-sm font-semibold text-slate-600 mb-3 text-center">Live preview</div>
    <x-phone-preview :name="$accounts->first()?->verified_name ?? 'Your Business'" :number="$accounts->first()?->display_phone_number">
        <div id="tpl-preview"></div>
    </x-phone-preview>
</div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('tpl-form');
    const body = document.getElementById('body'), box = document.getElementById('examples');
    const oldExamples = @json(array_values(old('body_examples', $form['body_examples'] ?? [])));
    const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const fmt = t => esc(t).replace(/\*([^*\n]+)\*/g, '<b>$1</b>').replace(/_([^_\n]+)_/g, '<i>$1</i>').replace(/~([^~\n]+)~/g, '<s>$1</s>');
    const L = '\x7b\x7b', R = '\x7d\x7d';

    function renderExamples() {
        const nums = [...body.value.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map(m => +m[1]);
        const max = nums.length ? Math.max(...nums) : 0;
        const old = [...box.querySelectorAll('input')].map(i => i.value);
        box.innerHTML = '';
        for (let i = 0; i < max; i++) {
            const input = document.createElement('input');
            input.name = `body_examples[${i}]`; input.placeholder = 'Example for variable ' + (i + 1);
            input.className = 'border border-slate-200 rounded-xl px-3 py-2 text-sm'; input.value = old[i] ?? oldExamples[i] ?? '';
            box.appendChild(input);
        }
        document.getElementById('bodyLen').textContent = body.value.length;
    }
    body.addEventListener('input', renderExamples); renderExamples();

    // Bold / italic / strike and "+ Variable" helpers
    document.querySelectorAll('.fmt').forEach(b => b.addEventListener('click', () => {
        const [s, e] = [body.selectionStart, body.selectionEnd], w = b.dataset.wrap;
        body.setRangeText(w + body.value.slice(s, e) + w, s, e, 'end'); body.dispatchEvent(new Event('input')); body.focus();
    }));
    document.getElementById('addVar').addEventListener('click', () => {
        const nums = [...body.value.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map(m => +m[1]);
        body.setRangeText(L + ((nums.length ? Math.max(...nums) : 0) + 1) + R, body.selectionStart, body.selectionEnd, 'end');
        body.dispatchEvent(new Event('input')); body.focus();
    });

    // Category and header switches
    const category = () => form.querySelector('input[name=category]:checked')?.value;
    const headerType = () => form.querySelector('input[name=header_type]:checked')?.value;
    function toggle() {
        const auth = category() === 'AUTHENTICATION';
        form.querySelector('[data-for=auth]').classList.toggle('hidden', !auth);
        form.querySelector('[data-for=content]').classList.toggle('hidden', auth);
        body.required = !auth;
        const h = headerType();
        form.querySelector('[data-header=text]').classList.toggle('hidden', h !== 'text');
        form.querySelector('[data-header=media]').classList.toggle('hidden', !['image', 'video', 'document'].includes(h));
        document.querySelectorAll('.btn-row').forEach(row => {
            const t = row.querySelector('.b-type').value;
            row.querySelector('.b-text').classList.toggle('invisible', t === 'COPY_CODE');
            row.querySelector('.b-value').classList.toggle('invisible', t === 'QUICK_REPLY');
            row.querySelector('.b-value').placeholder = { URL: 'https://example.com/@{{1}}', PHONE_NUMBER: '+91 98765 43210', COPY_CODE: 'Coupon code, e.g. DIWALI20' }[t] || '';
            row.querySelector('.b-example').classList.toggle('invisible', !(t === 'URL' && row.querySelector('.b-value').value.includes(L)));
        });
    }

    // Buttons: add / remove (renumbered on submit)
    const list = document.getElementById('buttons');
    document.getElementById('addButton').addEventListener('click', () => {
        if (list.children.length >= 10) return;
        const row = list.lastElementChild.cloneNode(true);
        row.querySelectorAll('input').forEach(i => i.value = ''); row.querySelector('select').value = 'QUICK_REPLY';
        list.appendChild(row); renumber(); toggle(); preview();
    });
    list.addEventListener('click', e => {
        if (!e.target.classList.contains('b-remove')) return;
        const row = e.target.closest('.btn-row');
        if (list.children.length > 1) row.remove(); else row.querySelectorAll('input').forEach(i => i.value = '');
        renumber(); preview();
    });
    function renumber() { [...list.children].forEach((row, i) => row.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(/buttons\[\d+\]/, `buttons[${i}]`))); }

    // Live WhatsApp preview
    function preview() {
        const fd = new FormData(form);
        let html;
        if (category() === 'AUTHENTICATION') {
            const sec = fd.get('security_recommendation') ? ' For your security, do not share this code.' : '';
            const exp = fd.get('code_expiration_minutes') ? `<div class="text-[11px] text-slate-500 mt-1">This code expires in ${esc(fd.get('code_expiration_minutes'))} minutes.</div>` : '';
            html = `<div class="px-2.5 pt-2 pb-1"><div><b>123456</b> is your verification code.${sec}</div>${exp}<div class="text-right text-[10px] text-slate-400">now</div></div>
                <div class="border-t text-center py-1.5 text-sky-600 font-medium">⧉ ${esc(fd.get('otp_button_text') || 'Copy code')}</div>`;
        } else {
            let text = body.value;
            box.querySelectorAll('input').forEach((inp, i) => { if (inp.value) text = text.split(L + (i + 1) + R).join(inp.value); });
            const h = headerType();
            const media = { image: '🖼 Image', video: '▶ Video', document: '📄 Document' }[h];
            const header = h === 'text' && fd.get('header') ? `<div class="font-semibold mb-1">${esc(fd.get('header'))}</div>` : '';
            const buttons = [...list.children].map(row => {
                const t = row.querySelector('.b-type').value, label = t === 'COPY_CODE' ? 'Copy offer code' : row.querySelector('.b-text').value;
                const icon = { URL: '↗ ', PHONE_NUMBER: '📞 ', COPY_CODE: '⧉ ', QUICK_REPLY: '↩ ' }[t];
                return label ? `<div class="border-t text-center py-1.5 text-sky-600 font-medium">${icon}${esc(label)}</div>` : '';
            }).join('');
            html = `${media ? `<div class="h-28 bg-slate-200 grid place-items-center text-xs text-slate-500">${media}</div>` : ''}<div class="px-2.5 pt-2 pb-1">${header}
                <div class="whitespace-pre-line break-words">${fmt(text)}</div>
                ${fd.get('footer') ? `<div class="text-[11px] text-slate-500 mt-1">${esc(fd.get('footer'))}</div>` : ''}
                <div class="text-right text-[10px] text-slate-400">now</div></div>${buttons}`;
        }
        document.getElementById('tpl-preview').innerHTML = `<div class="max-w-[92%] rounded-lg rounded-tl-none bg-white shadow text-[13px] overflow-hidden">${html}</div>`;
    }
    form.addEventListener('input', () => { toggle(); preview(); });
    form.addEventListener('change', () => { toggle(); preview(); });
    form.addEventListener('submit', renumber);
    toggle(); preview();
})();
</script>
@endpush
