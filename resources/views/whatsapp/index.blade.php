@extends('layouts.app', ['title' => 'WhatsApp Settings'])

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">WhatsApp Settings</h1>
    @if ($signupReady)
        <div class="flex items-center gap-3">
            <label class="text-sm flex items-center gap-1" title="Keep using the WhatsApp Business app on this number">
                <input type="checkbox" id="coexistence"> I use the WhatsApp Business app on this number
            </label>
            <button id="connect-whatsapp" class="rounded bg-brand-600 text-white px-4 py-2 font-semibold hover:bg-brand-700">Connect WhatsApp</button>
        </div>
    @else
        <span class="text-sm text-amber-700">Set META_APP_ID and META_EMBEDDED_SIGNUP_CONFIG_ID in .env to enable Connect WhatsApp.</span>
    @endif
</div>
<div id="connect-status" class="hidden mb-4 rounded px-4 py-3 text-sm"></div>

@forelse ($accounts as $account)
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-4">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-lg font-semibold">{{ $account->verified_name ?? 'WhatsApp number' }}</div>
                <div class="text-slate-600">{{ $account->display_phone_number ?? $account->phone_number_id }}</div>
                <div class="text-xs text-slate-500 mt-1">
                    Phone number ID {{ $account->phone_number_id }} · WABA {{ $account->waba_id }} · via {{ str_replace('_', ' ', $account->connected_via) }}
                </div>
                <div class="mt-2 flex gap-2 text-xs">
                    <span class="px-2 py-0.5 rounded {{ $account->status === 'connected' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">{{ $account->status }}</span>
                    @if ($account->quality_rating)<span class="px-2 py-0.5 rounded bg-slate-100">Quality: {{ $account->quality_rating }}</span>@endif
                    @if ($account->messaging_limit)<span class="px-2 py-0.5 rounded bg-slate-100">Limit: {{ $account->messaging_limit }}</span>@endif
                </div>
                @if ($account->last_error)<div class="mt-2 text-xs text-amber-700 whitespace-pre-line">{{ $account->last_error }}</div>@endif
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('whatsapp.refresh', $account) }}">@csrf<button class="text-sm rounded border px-3 py-1">Refresh</button></form>
                <form method="POST" action="{{ route('whatsapp.destroy', $account) }}" onsubmit="return confirm('Remove this number from the panel?')">@csrf @method('DELETE')<button class="text-sm rounded border border-red-300 text-red-700 px-3 py-1">Remove</button></form>
            </div>
        </div>

        <form method="POST" action="{{ route('whatsapp.test', $account) }}" class="mt-5 border-t pt-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            @csrf
            <div class="md:col-span-5 text-sm font-semibold">Send a test message</div>
            <label class="block"><span class="text-xs">To (with country code)</span>
                <input name="to" placeholder="919876543210" value="{{ old('to') }}" class="mt-1 w-full border rounded px-2 py-1" required></label>
            <label class="block"><span class="text-xs">Type</span>
                <select name="type" class="mt-1 w-full border rounded px-2 py-1">
                    <option value="template">Template</option>
                    <option value="text">Text (only inside 24h window)</option>
                </select></label>
            @php($approved = $templates[$account->id] ?? collect())
            <label class="block"><span class="text-xs">Approved template</span>
                @if ($approved->isNotEmpty())
                    <select name="template" class="mt-1 w-full border rounded px-2 py-1"
                            onchange="this.form.language.value = this.selectedOptions[0].dataset.lang">
                        @foreach ($approved as $t)
                            <option value="{{ $t->name }}" data-lang="{{ $t->language }}" @selected(old('template') === $t->name)>{{ $t->name }} ({{ $t->language }})</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="language" value="{{ old('language', $approved->first()->language) }}">
                @else
                    <div class="flex gap-1 mt-1"><input name="template" value="{{ old('template') }}" placeholder="template_name" class="w-full border rounded px-2 py-1"><input name="language" value="{{ old('language', 'en') }}" class="w-16 border rounded px-2 py-1"></div>
                @endif
            </label>
            <label class="block"><span class="text-xs">Text body</span>
                <input name="body" placeholder="Hello from the panel" class="mt-1 w-full border rounded px-2 py-1"></label>
            <button class="rounded bg-brand-600 text-white px-4 py-2 font-semibold">Send</button>
            @if ($approved->isEmpty())
                <p class="md:col-span-5 text-xs text-amber-700">No approved template yet. <a href="{{ route('templates.index') }}" class="underline font-semibold">Create a template</a> and wait for Meta approval (hello_world works only on Meta test numbers). Text messages work only if this person messaged you in the last 24 hours.</p>
            @endif
        </form>
    </div>
@empty
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-10 text-center text-slate-600">
        No number connected yet. Click <b>Connect WhatsApp</b> to link your WhatsApp Business number with Meta.
    </div>
@endforelse

<details class="mt-8 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
    <summary class="cursor-pointer font-semibold">Advanced: add a number manually (IDs + access token)</summary>
    <p class="text-sm text-slate-600 mt-2">For numbers already set up in Meta Business Manager, for example the Cloud API test number. Use a permanent system user token.</p>
    <form method="POST" action="{{ route('whatsapp.manual') }}" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        @csrf
        <input name="waba_id" placeholder="WhatsApp Business Account ID" value="{{ old('waba_id') }}" class="border rounded px-3 py-2" required>
        <input name="phone_number_id" placeholder="Phone number ID" value="{{ old('phone_number_id') }}" class="border rounded px-3 py-2" required>
        <input name="access_token" type="password" placeholder="Access token" class="border rounded px-3 py-2" required autocomplete="off">
        <button class="md:col-span-3 rounded bg-slate-800 text-white py-2 font-semibold">Add number</button>
    </form>
</details>
@endsection

@if ($signupReady)
@push('scripts')
@if ($bridgeUrl)
<script>
    // White-label domain: Meta signup runs on the main panel domain in a popup, then tells this page it is done.
    document.getElementById('connect-whatsapp').addEventListener('click', () => {
        const coexistence = document.getElementById('coexistence').checked ? 1 : 0;
        const w = window.open(@json($bridgeUrl) + '&coexistence=' + coexistence, 'wa-connect', 'width=720,height=820');
        if (!w) alert('Please allow pop-ups for this site to connect WhatsApp.');
    });
    window.addEventListener('message', (event) => {
        if (event.origin !== @json($bridgeOrigin) || event.data?.type !== 'SD_WA_CONNECTED') return;
        window.location.reload();
    });
</script>
@else
@include('whatsapp.partials.signup-js', [
    'connectUrl' => route('whatsapp.connect'),
    'extra' => [],
    'onDone' => 'json => { window.location = json.redirect; }',
])
@endif
@endpush
@endif
