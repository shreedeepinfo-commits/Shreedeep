@extends('layouts.app', ['title' => 'API & Webhooks'])
@php
    use App\Models\WebhookEndpoint;
    $input = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none';
    $card = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $tabs = ['keys' => ['API keys', 'code'], 'webhooks' => ['Webhooks', 'bolt'], 'docs' => ['API docs', 'document']];
    $sampleKey = session('new_key') ?: 'sdk_your_api_key';
@endphp

@section('content')
<x-page-header title="API & Webhooks" subtitle="Connect your website, CRM or software: send WhatsApp messages and get events in real time." />

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-950 via-brand-800 to-brand-600 text-white p-6 mb-6">
    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/5"></div>
    <div class="relative grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach ([['1. Create an API key', 'Keep it secret, like a password.', 'code'], ['2. Call the API', 'Send texts and templates, add contacts.', 'send'], ['3. Add a webhook', 'Get replies and delivery updates on your server.', 'bolt']] as [$h, $d, $i])
            <div class="rounded-2xl bg-white/10 border border-white/10 p-4 flex gap-3">
                <x-icon :name="$i" class="w-5 h-5 text-brand-100 mt-0.5" />
                <div><div class="font-semibold">{{ $h }}</div><div class="text-xs text-brand-100 mt-0.5">{{ $d }}</div></div>
            </div>
        @endforeach
    </div>
    <div class="relative mt-4 text-sm text-brand-100">Base URL: <code class="bg-white/10 rounded px-2 py-1 text-white">{{ $baseUrl }}</code></div>
</section>

<div class="flex gap-2 mb-5">
    @foreach ($tabs as $k => [$label, $icon])
        <a href="{{ route('developers.index', ['tab' => $k]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold {{ $tab === $k ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/30' : 'bg-white border border-slate-200 text-slate-600 hover:text-brand-700' }}"><x-icon :name="$icon" class="w-4 h-4" />{{ $label }}</a>
    @endforeach
</div>

@if ($tab === 'keys')
    @if (session('new_key'))
        <div class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-5 mb-5">
            <div class="font-semibold text-emerald-800">Your new API key</div>
            <p class="text-sm text-emerald-700">Copy it now and keep it safe. For security, it will not be shown again.</p>
            <div class="flex gap-2 mt-3">
                <code id="new-key" class="flex-1 bg-white border border-emerald-200 rounded-xl px-3 py-2.5 text-sm break-all">{{ session('new_key') }}</code>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('new-key').textContent); this.textContent='Copied ✓'" class="px-4 rounded-xl bg-emerald-600 text-white text-sm font-semibold">Copy</button>
            </div>
        </div>
    @endif
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <form method="POST" action="{{ route('developers.keys.store') }}" class="{{ $card }} p-6 space-y-3 h-fit">
            @csrf
            <div class="font-semibold">Create API key</div>
            <input name="name" required maxlength="60" placeholder="e.g. Website, Zoho CRM, Billing app" class="{{ $input }}">
            <x-btn icon="plus">Create key</x-btn>
            <p class="text-xs text-slate-500">Each app should get its own key, so you can turn one off without breaking the others.</p>
        </form>
        <div class="xl:col-span-2 {{ $card }} overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 font-semibold">Active keys</div>
            @forelse ($keys as $k)
                <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-slate-50">
                    <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon name="code" class="w-5 h-5" /></span>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium">{{ $k->name }}</div>
                        <div class="text-xs text-slate-500"><code>{{ $k->prefix }}••••••••</code> · created {{ $k->created_at->format('d M Y') }} · {{ $k->last_used_at ? 'last used '.$k->last_used_at->diffForHumans() : 'never used' }} · {{ number_format($k->requests) }} requests</div>
                    </div>
                    <form method="POST" action="{{ route('developers.keys.destroy', $k) }}" onsubmit="return confirm('Revoke this key? Apps using it will stop working.')">@csrf @method('DELETE')<x-btn variant="danger" icon="trash">Revoke</x-btn></form>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">No API keys yet.</div>
            @endforelse
        </div>
    </div>
@endif

@if ($tab === 'webhooks')
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <form method="POST" action="{{ route('developers.webhooks.store') }}" class="{{ $card }} p-6 space-y-3 h-fit">
            @csrf
            <div class="font-semibold">Add webhook URL</div>
            <input name="url" type="url" required placeholder="https://yourapp.com/whatsapp-webhook" value="{{ old('url') }}" class="{{ $input }}">
            <div class="space-y-2">
                @foreach (WebhookEndpoint::EVENTS as $ev => $desc)
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="events[]" value="{{ $ev }}" checked class="rounded text-brand-600 mt-0.5"><span><code class="text-xs">{{ $ev }}</code><span class="block text-xs text-slate-500">{{ $desc }}</span></span></label>
                @endforeach
            </div>
            <x-btn icon="plus">Add webhook</x-btn>
        </form>
        <div class="xl:col-span-2 space-y-4">
            @forelse ($endpoints as $e)
                <div class="{{ $card }} p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <code class="flex-1 min-w-0 truncate text-sm font-medium">{{ $e->url }}</code>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $e->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $e->active ? '● On' : 'Off' }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-2">@foreach ($e->events as $ev)<code class="text-xs px-2 py-0.5 rounded bg-slate-100">{{ $ev }}</code>@endforeach</div>
                    <div class="text-xs text-slate-500 mt-2">
                        {{ $e->last_delivery_at ? 'Last delivery '.$e->last_delivery_at->diffForHumans().' · HTTP '.($e->last_status ?? 'error') : 'No deliveries yet' }}
                        @if ($e->failures) · <span class="text-red-600">{{ $e->failures }} failures in a row</span>@endif
                        @if (! $e->active && $e->failures >= WebhookEndpoint::MAX_FAILURES) · turned off after too many failures @endif
                    </div>
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-brand-700 font-semibold">Signing secret</summary>
                        <code class="block mt-2 bg-slate-50 rounded-lg px-3 py-2 break-all">{{ $e->secret }}</code>
                        <p class="text-xs text-slate-500 mt-1">Check the <code>X-Webhook-Signature</code> header: <code>sha256=</code> HMAC-SHA256 of the raw body with this secret.</p>
                    </details>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <form method="POST" action="{{ route('developers.webhooks.test', $e) }}">@csrf<x-btn variant="outline" icon="send">Send test</x-btn></form>
                        <form method="POST" action="{{ route('developers.webhooks.toggle', $e) }}">@csrf<x-btn variant="outline" :icon="$e->active ? 'pause' : 'play'">{{ $e->active ? 'Turn off' : 'Turn on' }}</x-btn></form>
                        <form method="POST" action="{{ route('developers.webhooks.destroy', $e) }}" onsubmit="return confirm('Delete this webhook?')" class="ml-auto">@csrf @method('DELETE')<x-btn variant="danger" icon="trash">Delete</x-btn></form>
                    </div>
                </div>
            @empty
                <div class="{{ $card }} p-10 text-center text-sm text-slate-500">No webhooks yet. Add your server's URL to get customer replies and delivery updates instantly.</div>
            @endforelse
        </div>
    </div>

    <div class="{{ $card }} overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-semibold">Recent deliveries</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase"><tr><th class="text-left px-6 py-3">Time</th><th class="text-left px-3 py-3">Event</th><th class="text-left px-3 py-3">URL</th><th class="text-left px-3 py-3">Result</th><th class="text-right px-6 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($deliveries as $d)
                        <tr>
                            <td class="px-6 py-3 whitespace-nowrap">{{ $d->created_at->format('d M, H:i:s') }}</td>
                            <td class="px-3 py-3"><code class="text-xs">{{ $d->event }}</code></td>
                            <td class="px-3 py-3 max-w-xs truncate text-slate-500">{{ $d->endpoint?->url }}</td>
                            <td class="px-3 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ ['success' => 'bg-emerald-50 text-emerald-700', 'failed' => 'bg-red-50 text-red-700'][$d->status] ?? 'bg-amber-50 text-amber-700' }}">{{ ucfirst($d->status) }}{{ $d->response_code ? ' · '.$d->response_code : '' }}</span>
                                @if ($d->attempts > 1)<span class="text-xs text-slate-400">{{ $d->attempts }} tries</span>@endif
                            </td>
                            <td class="px-6 py-3 text-right">@if ($d->status === 'failed')<form method="POST" action="{{ route('developers.deliveries.retry', $d) }}">@csrf<button class="text-brand-700 font-semibold text-xs">Retry</button></form>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Nothing sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($tab === 'docs')
    @php
        $endpoints = [
            ['GET', '/me', 'Your account, plan and WhatsApp numbers.', null, '{"workspace":{"id":1,"name":"Demo Traders","plan":"Growth"},"numbers":[{"phone_number_id":"1234567890","display_phone_number":"+91 98765 00000"}]}'],
            ['GET', '/templates', 'Your approved templates and how many variables each needs.', null, '{"data":[{"name":"order_update","language":"en","category":"UTILITY","body_variables":2}]}'],
            ['POST', '/messages/template', 'Send an approved template to any opted-in number (works any time).', '{"to":"919876543210","template":"order_update","language":"en","variables":["Ravi","#1042"],"header_media_url":null}', '{"data":{"id":981,"wamid":"wamid.HBg…","status":"sent","to":"919876543210"}}'],
            ['POST', '/messages/text', 'Send a normal text. Only inside 24 hours of the customer\'s last message.', '{"to":"919876543210","text":"Your order is out for delivery 🚚"}', '{"data":{"id":982,"status":"sent"}}'],
            ['GET', '/messages/{id}', 'Check a message: sent, delivered, read or failed.', null, '{"data":{"id":982,"status":"read","delivered_at":"…","read_at":"…"}}'],
            ['GET', '/contacts?tag=vip&per_page=100', 'List contacts. Filters: phone, tag, updated_since.', null, '{"data":[{"id":7,"phone":"919876543210","name":"Ravi","tags":["vip"]}],"meta":{"page":1,"total":1}}'],
            ['POST', '/contacts', 'Add or update a contact (matched by phone). Tags and fields are merged.', '{"phone":"919876543210","name":"Ravi","tags":["website"],"fields":{"city":"Jaipur"}}', '{"data":{"id":7,"phone":"919876543210"}}'],
        ];
    @endphp
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-4">
            @foreach ($endpoints as [$method, $path, $desc, $body, $resp])
                <div class="{{ $card }} p-5">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold px-2 py-1 rounded {{ $method === 'GET' ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $method }}</span>
                        <code class="font-semibold text-sm">/api/v1{{ $path }}</code>
                    </div>
                    <p class="text-sm text-slate-600 mt-2">{{ $desc }}</p>
                    <pre class="mt-3 bg-slate-900 text-slate-100 rounded-xl p-4 text-xs overflow-x-auto">curl -X {{ $method }} {{ $baseUrl }}{{ $path }} \
  -H "Authorization: Bearer {{ $sampleKey }}"{!! $body ? " \\\n  -H \"Content-Type: application/json\" \\\n  -d '".e($body)."'" : '' !!}</pre>
                    <details class="mt-2 text-sm"><summary class="cursor-pointer text-brand-700 font-semibold">Example response</summary><pre class="mt-2 bg-slate-50 rounded-xl p-3 text-xs overflow-x-auto">{{ $resp }}</pre></details>
                </div>
            @endforeach
        </div>
        <aside class="space-y-4">
            <div class="{{ $card }} p-5 text-sm space-y-2">
                <div class="font-semibold">Good to know</div>
                <p>Send the key in the <code>Authorization: Bearer …</code> header. Up to {{ config('whatsapp.api_per_minute', 120) }} requests per minute per key.</p>
                <p>Phone numbers need the country code, e.g. <code>919876543210</code>.</p>
                <p>Errors come back as <code>{"error":{"code","message"}}</code> with a 4xx/5xx status. Common codes: <code>outside_24h_window</code>, <code>template_not_found</code>, <code>opted_out</code>, <code>blocked</code>, <code>plan_limit</code>.</p>
                <p>Messages sent by API show in Live Chat and reports like any other message.</p>
            </div>
            <div class="{{ $card }} p-5 text-sm space-y-2">
                <div class="font-semibold">Webhook example</div>
                <pre class="bg-slate-900 text-slate-100 rounded-xl p-3 text-xs overflow-x-auto">POST https://yourapp.com/hook
X-Webhook-Event: message.received
X-Webhook-Signature: sha256=…

{"event":"message.received",
 "data":{"id":991,"from":"919876543210",
  "type":"text","text":"Hi, price?"}}</pre>
                <p class="text-xs text-slate-500">Reply with any 2xx status within 10 seconds. Failed deliveries are retried for about 1.5 hours.</p>
            </div>
        </aside>
    </div>
@endif
@endsection
