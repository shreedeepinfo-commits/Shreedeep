<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>Connect WhatsApp · {{ config('brand.name') }}</title>
</head>
<body class="min-h-screen bg-slate-50 grid place-items-center p-6">
    <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center">
        @if (config('brand.logo_url'))<img src="{{ config('brand.logo_url') }}" alt="" class="w-14 h-14 mx-auto rounded-full object-contain">@endif
        <div class="text-sm text-slate-500 mt-3">{{ config('brand.name') }}</div>
        <h1 class="text-xl font-bold mt-1">Connect WhatsApp for {{ $workspace->name }}</h1>
        <p class="text-sm text-slate-500 mt-2">Log in with Facebook, choose your business and verify your WhatsApp number. This window closes by itself when done.</p>
        <div id="connect-status" class="hidden mt-4 rounded px-4 py-3 text-sm"></div>
        @if ($signupReady)
            <label class="text-sm flex items-center justify-center gap-2 mt-5"><input type="checkbox" id="coexistence" @checked($coexistence)> I use the WhatsApp Business app on this number</label>
            <button id="connect-whatsapp" class="mt-4 w-full rounded-xl bg-brand-600 text-white px-4 py-3 font-semibold hover:bg-brand-700">Continue with Facebook</button>
        @else
            <p class="mt-4 text-sm text-amber-700">WhatsApp signup is not set up on the server yet.</p>
        @endif
    </div>
@if ($signupReady)
@include('whatsapp.partials.signup-js', [
    'connectUrl' => route('whatsapp.bridge.connect'),
    'extra' => ['t' => $ticket],
    'onDone' => 'json => { showStatus("WhatsApp number " + json.label + " connected. You can close this window.", false); if (window.opener) { window.opener.postMessage({ type: "SD_WA_CONNECTED", label: json.label }, '.json_encode($origin).'); setTimeout(() => window.close(), 1500); } }',
])
@endif
</body>
</html>
