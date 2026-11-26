@extends('layouts.app', ['title' => 'WhatsApp Numbers'])

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">WhatsApp Numbers</h1>
    @if ($signupReady)
        <div class="flex items-center gap-3">
            <label class="text-sm flex items-center gap-1" title="Keep using the WhatsApp Business app on this number">
                <input type="checkbox" id="coexistence"> I use the WhatsApp Business app on this number
            </label>
            <button id="connect-whatsapp" class="rounded bg-emerald-600 text-white px-4 py-2 font-semibold hover:bg-emerald-700">Connect WhatsApp</button>
        </div>
    @else
        <span class="text-sm text-amber-700">Set META_APP_ID and META_EMBEDDED_SIGNUP_CONFIG_ID in .env to enable Connect WhatsApp.</span>
    @endif
</div>
<div id="connect-status" class="hidden mb-4 rounded px-4 py-3 text-sm"></div>

@forelse ($accounts as $account)
    <div class="bg-white rounded-xl shadow p-6 mb-4">
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
            <label class="block"><span class="text-xs">Template name / language</span>
                <div class="flex gap-1 mt-1"><input name="template" value="hello_world" class="w-full border rounded px-2 py-1"><input name="language" value="en_US" class="w-20 border rounded px-2 py-1"></div></label>
            <label class="block"><span class="text-xs">Text body</span>
                <input name="body" placeholder="Hello from the panel" class="mt-1 w-full border rounded px-2 py-1"></label>
            <button class="rounded bg-emerald-600 text-white px-4 py-2 font-semibold">Send</button>
        </form>
    </div>
@empty
    <div class="bg-white rounded-xl shadow p-10 text-center text-slate-600">
        No number connected yet. Click <b>Connect WhatsApp</b> to link your WhatsApp Business number with Meta.
    </div>
@endforelse

<details class="mt-8 bg-white rounded-xl shadow p-6">
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
<script>
    window.fbAsyncInit = function () {
        FB.init({ appId: @json(config('whatsapp.app_id')), autoLogAppEvents: true, xfbml: true, version: @json(config('whatsapp.graph_version')) });
    };

    // Embedded Signup posts the new WABA and phone number ids to this window.
    let signupSession = null;
    window.addEventListener('message', (event) => {
        if (!event.origin.endsWith('facebook.com')) return;
        try {
            const data = JSON.parse(event.data);
            if (data.type !== 'WA_EMBEDDED_SIGNUP') return;
            if (String(data.event).startsWith('FINISH')) {
                signupSession = data.data;
            } else if (data.event === 'CANCEL') {
                showStatus('Signup was cancelled' + (data.data?.current_step ? ' at step ' + data.data.current_step : '') + '.', true);
            } else if (data.event === 'ERROR') {
                showStatus('Meta reported an error: ' + (data.data?.error_message || 'unknown'), true);
            }
        } catch (e) { /* not JSON, ignore */ }
    });

    function showStatus(text, isError) {
        const el = document.getElementById('connect-status');
        el.textContent = text;
        el.className = 'mb-4 rounded px-4 py-3 text-sm ' + (isError ? 'bg-red-100 text-red-900' : 'bg-emerald-100 text-emerald-900');
    }

    async function finishSignup(code) {
        // The session info message can arrive just after the login callback.
        for (let i = 0; i < 20 && !signupSession; i++) await new Promise(r => setTimeout(r, 250));
        if (!signupSession?.phone_number_id || !signupSession?.waba_id) {
            showStatus('Meta did not return the WhatsApp number details. Please try again.', true);
            return;
        }
        showStatus('Connecting your number…', false);
        const res = await fetch(@json(route('whatsapp.connect')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({
                code,
                waba_id: signupSession.waba_id,
                phone_number_id: signupSession.phone_number_id,
                business_id: signupSession.business_id || null,
                coexistence: document.getElementById('coexistence').checked,
            }),
        });
        const json = await res.json();
        if (res.ok) window.location = json.redirect; else showStatus(json.message || 'Could not connect the number.', true);
    }

    document.getElementById('connect-whatsapp').addEventListener('click', () => {
        signupSession = null;
        const coexistence = document.getElementById('coexistence').checked;
        FB.login((response) => {
            if (response.authResponse?.code) finishSignup(response.authResponse.code);
            else showStatus('Signup window closed before finishing.', true);
        }, {
            config_id: @json(config('whatsapp.config_id')),
            response_type: 'code',
            override_default_response_type: true,
            extras: {
                setup: {},
                featureType: coexistence ? 'whatsapp_business_app_onboarding' : '',
                sessionInfoVersion: '3',
            },
        });
    });
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
@endpush
@endif
