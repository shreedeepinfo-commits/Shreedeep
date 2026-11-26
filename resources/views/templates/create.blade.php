@extends('layouts.app', ['title' => 'New template'])

@section('content')
<x-page-header title="New message template" subtitle="Meta usually reviews templates within minutes to a few hours.">
    <x-btn :href="route('templates.index')" variant="outline">Cancel</x-btn>
</x-page-header>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
<form method="POST" action="{{ route('templates.store') }}" id="tpl-form" class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
    @csrf
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">WhatsApp number</span>
            <select name="whatsapp_account_id" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5" required>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('whatsapp_account_id') == $a->id)>{{ $a->label() }}</option>@endforeach
            </select></label>
        <label class="block"><span class="text-sm font-medium">Name (lowercase_with_underscores)</span>
            <input name="name" value="{{ old('name') }}" placeholder="diwali_offer" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5 font-mono" required></label>
        <label class="block"><span class="text-sm font-medium">Category</span>
            <select name="category" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5">
                <option value="MARKETING">Marketing (offers, promotions)</option>
                <option value="UTILITY" @selected(old('category') === 'UTILITY')>Utility (orders, updates)</option>
            </select></label>
        <label class="block"><span class="text-sm font-medium">Language</span>
            <select name="language" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5">
                @foreach (['en' => 'English', 'en_US' => 'English (US)', 'hi' => 'Hindi', 'gu' => 'Gujarati', 'mr' => 'Marathi', 'ta' => 'Tamil', 'te' => 'Telugu', 'bn' => 'Bengali', 'kn' => 'Kannada', 'ml' => 'Malayalam', 'pa' => 'Punjabi'] as $code => $label)
                    <option value="{{ $code }}" @selected(old('language', 'en') === $code)>{{ $label }}</option>
                @endforeach
            </select></label>
    </div>
    <label class="block"><span class="text-sm font-medium">Header (optional, text)</span>
        <input name="header" value="{{ old('header') }}" maxlength="60" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5"></label>
    <label class="block"><span class="text-sm font-medium">Body. Use @{{1}}, @{{2}}… for variables</span>
        <textarea name="body" id="body" rows="5" maxlength="1024" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5" required>{{ old('body', $exampleBody) }}</textarea></label>
    <div id="examples" class="grid grid-cols-1 md:grid-cols-3 gap-2"></div>
    <label class="block"><span class="text-sm font-medium">Footer (optional)</span>
        <input name="footer" value="{{ old('footer', 'Reply STOP to unsubscribe') }}" maxlength="60" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2.5"></label>
    <div>
        <span class="text-sm font-medium">Buttons (optional, up to 3)</span>
        @for ($i = 0; $i < 3; $i++)
            <div class="grid grid-cols-3 gap-2 mt-1">
                <select name="buttons[{{ $i }}][type]" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">
                    <option value="QUICK_REPLY">Quick reply</option><option value="URL">Website link</option><option value="PHONE_NUMBER">Call number</option>
                </select>
                <input name="buttons[{{ $i }}][text]" placeholder="Button text" maxlength="25" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">
                <input name="buttons[{{ $i }}][value]" placeholder="URL or phone (if needed)" class="border border-slate-200 rounded-xl px-2 py-2 text-sm">
            </div>
        @endfor
    </div>
    <x-btn icon="send">Submit to Meta for approval</x-btn>
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
    // One example field per body variable; Meta requires sample values.
    const body = document.getElementById('body'), box = document.getElementById('examples');
    function renderExamples() {
        const nums = [...body.value.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map(m => +m[1]);
        const max = nums.length ? Math.max(...nums) : 0;
        const old = [...box.querySelectorAll('input')].map(i => i.value);
        box.innerHTML = '';
        for (let i = 0; i < max; i++) {
            const input = document.createElement('input');
            input.name = `body_examples[${i}]`; input.placeholder = 'Example for variable ' + (i + 1); input.required = true;
            input.className = 'border rounded px-2 py-1 text-sm'; input.value = old[i] || '';
            box.appendChild(input);
        }
    }
    body.addEventListener('input', renderExamples); renderExamples();

    // Live WhatsApp preview with sample values filled in.
    const form = document.getElementById('tpl-form');
    const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    function preview() {
        const f = new FormData(form);
        let text = f.get('body') || '';
        box.querySelectorAll('input').forEach((inp, i) => { if (inp.value) text = text.split('\x7b\x7b' + (i + 1) + '\x7d\x7d').join(inp.value); });
        const buttons = [0, 1, 2].map(i => f.get(`buttons[${i}][text]`)).filter(Boolean);
        document.getElementById('tpl-preview').innerHTML = `<div class="max-w-[92%] rounded-lg rounded-tl-none bg-white shadow text-[13px] overflow-hidden"><div class="px-2.5 pt-2 pb-1">
            ${f.get('header') ? `<div class="font-semibold mb-1">${esc(f.get('header'))}</div>` : ''}
            <div class="whitespace-pre-line break-words">${esc(text)}</div>
            ${f.get('footer') ? `<div class="text-[11px] text-slate-500 mt-1">${esc(f.get('footer'))}</div>` : ''}
            <div class="text-right text-[10px] text-slate-400">now</div></div>
            ${buttons.map(b => `<div class="border-t text-center py-1.5 text-sky-600 font-medium">${esc(b)}</div>`).join('')}</div>`;
    }
    form.addEventListener('input', preview); preview();
</script>
@endpush
