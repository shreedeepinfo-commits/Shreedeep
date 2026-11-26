@extends('layouts.app', ['title' => 'New template'])

@section('content')
<h1 class="text-2xl font-semibold mb-6">New message template</h1>
<form method="POST" action="{{ route('templates.store') }}" class="bg-white rounded-xl shadow p-6 max-w-3xl space-y-4">
    @csrf
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">WhatsApp number</span>
            <select name="whatsapp_account_id" class="mt-1 w-full border rounded px-3 py-2" required>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('whatsapp_account_id') == $a->id)>{{ $a->label() }}</option>@endforeach
            </select></label>
        <label class="block"><span class="text-sm font-medium">Name (lowercase_with_underscores)</span>
            <input name="name" value="{{ old('name') }}" placeholder="diwali_offer" class="mt-1 w-full border rounded px-3 py-2 font-mono" required></label>
        <label class="block"><span class="text-sm font-medium">Category</span>
            <select name="category" class="mt-1 w-full border rounded px-3 py-2">
                <option value="MARKETING">Marketing (offers, promotions)</option>
                <option value="UTILITY" @selected(old('category') === 'UTILITY')>Utility (orders, updates)</option>
            </select></label>
        <label class="block"><span class="text-sm font-medium">Language</span>
            <select name="language" class="mt-1 w-full border rounded px-3 py-2">
                @foreach (['en' => 'English', 'en_US' => 'English (US)', 'hi' => 'Hindi', 'gu' => 'Gujarati', 'mr' => 'Marathi', 'ta' => 'Tamil', 'te' => 'Telugu', 'bn' => 'Bengali', 'kn' => 'Kannada', 'ml' => 'Malayalam', 'pa' => 'Punjabi'] as $code => $label)
                    <option value="{{ $code }}" @selected(old('language', 'en') === $code)>{{ $label }}</option>
                @endforeach
            </select></label>
    </div>
    <label class="block"><span class="text-sm font-medium">Header (optional, text)</span>
        <input name="header" value="{{ old('header') }}" maxlength="60" class="mt-1 w-full border rounded px-3 py-2"></label>
    <label class="block"><span class="text-sm font-medium">Body. Use @{{1}}, @{{2}}… for variables</span>
        <textarea name="body" id="body" rows="5" maxlength="1024" class="mt-1 w-full border rounded px-3 py-2" required>{{ old('body', $exampleBody) }}</textarea></label>
    <div id="examples" class="grid grid-cols-1 md:grid-cols-3 gap-2"></div>
    <label class="block"><span class="text-sm font-medium">Footer (optional)</span>
        <input name="footer" value="{{ old('footer', 'Reply STOP to unsubscribe') }}" maxlength="60" class="mt-1 w-full border rounded px-3 py-2"></label>
    <div>
        <span class="text-sm font-medium">Buttons (optional, up to 3)</span>
        @for ($i = 0; $i < 3; $i++)
            <div class="grid grid-cols-3 gap-2 mt-1">
                <select name="buttons[{{ $i }}][type]" class="border rounded px-2 py-1 text-sm">
                    <option value="QUICK_REPLY">Quick reply</option><option value="URL">Website link</option><option value="PHONE_NUMBER">Call number</option>
                </select>
                <input name="buttons[{{ $i }}][text]" placeholder="Button text" maxlength="25" class="border rounded px-2 py-1 text-sm">
                <input name="buttons[{{ $i }}][value]" placeholder="URL or phone (if needed)" class="border rounded px-2 py-1 text-sm">
            </div>
        @endfor
    </div>
    <button class="rounded bg-emerald-600 text-white px-4 py-2 font-semibold">Submit to Meta for approval</button>
</form>
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
</script>
@endpush
