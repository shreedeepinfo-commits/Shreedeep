@extends('layouts.app', ['title' => 'Integrations'])
@php
    use App\Http\Controllers\IntegrationController;
    use App\Models\Integration;
    use App\Models\IntegrationEvent;
    $input = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none';
    $card = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $label = 'block text-xs font-semibold text-slate-500 mb-1';
@endphp

@section('content')
<x-page-header title="Integrations" subtitle="Bring WhatsApp to your website, store and tools: chat widget, QR codes, order updates and more." />

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-950 via-brand-800 to-brand-600 text-white p-6 mb-6">
    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/5"></div>
    <div class="relative grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ([['Chat widget', 'A WhatsApp button on your website.', 'chat'], ['Link & QR', 'For Instagram bio, shop counter, visiting cards.', 'qr'], ['Store orders', 'Shopify and WooCommerce order updates.', 'cart'], ['Sheets & Zapier', 'Connect any tool with the API.', 'plug']] as [$h, $d, $i])
            <div class="rounded-2xl bg-white/10 border border-white/10 p-4 flex gap-3">
                <x-icon :name="$i" class="w-5 h-5 text-brand-100 mt-0.5 shrink-0" />
                <div><div class="font-semibold">{{ $h }}</div><div class="text-xs text-brand-100 mt-0.5">{{ $d }}</div></div>
            </div>
        @endforeach
    </div>
</section>

<div class="flex flex-wrap gap-2 mb-5">
    @foreach (IntegrationController::TABS as $k => [$tabLabel, $icon])
        <a href="{{ route('integrations.index', ['tab' => $k]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold {{ $tab === $k ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/30' : 'bg-white border border-slate-200 text-slate-600 hover:text-brand-700' }}"><x-icon :name="$icon" class="w-4 h-4" />{{ $tabLabel }}</a>
    @endforeach
</div>

@if ($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 mb-5">{{ $errors->first() }}</div>
@endif

{{-- ===================== Chat widget ===================== --}}
@if ($tab === 'widget')
    @php $w = fn ($k) => old($k, $widget->setting($k)); $snippet = '<script src="'.route('widget.script', $widget->token).'" async></script>'; @endphp
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
        <form method="POST" action="{{ route('integrations.widget') }}" class="xl:col-span-2 {{ $card }} p-6 space-y-4" id="widget-form">
            @csrf @method('PUT')
            <div class="flex items-center justify-between">
                <div class="font-semibold">Widget settings</div>
                <label class="inline-flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="active" value="1" class="rounded text-brand-600" @checked(old('active', $widget->active))> Show on my website</label>
            </div>
            <div>
                <label class="{{ $label }}">WhatsApp number (with country code)</label>
                <input name="phone" value="{{ $w('phone') ?: $numbers->keys()->first() }}" required maxlength="20" placeholder="919876543210" class="{{ $input }}" list="wa-numbers">
                <datalist id="wa-numbers">@foreach ($numbers as $n => $l)<option value="{{ $n }}">{{ $l }}</option>@endforeach</datalist>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}">Business name</label><input name="brand" value="{{ $w('brand') }}" maxlength="60" placeholder="Sharma Electronics" class="{{ $input }}" data-prev="brand"></div>
                <div><label class="{{ $label }}">Button text</label><input name="button_text" value="{{ $w('button_text') }}" required maxlength="30" class="{{ $input }}" data-prev="button_text"></div>
            </div>
            <div><label class="{{ $label }}">Greeting shown in the popup</label><textarea name="greeting" rows="2" required maxlength="300" class="{{ $input }}" data-prev="greeting">{{ $w('greeting') }}</textarea></div>
            <div><label class="{{ $label }}">First message the customer sends (pre-filled)</label><input name="prefill" value="{{ $w('prefill') }}" maxlength="300" class="{{ $input }}"></div>
            <div class="grid grid-cols-3 gap-3 items-end">
                <div><label class="{{ $label }}">Colour</label><input type="color" name="color" value="{{ $w('color') }}" class="h-10 w-full rounded-xl border border-slate-200 p-1" data-prev="color"></div>
                <div><label class="{{ $label }}">Position</label><select name="position" class="{{ $input }}" data-prev="position"><option value="right" @selected($w('position') === 'right')>Bottom right</option><option value="left" @selected($w('position') === 'left')>Bottom left</option></select></div>
                <label class="inline-flex items-center gap-2 text-sm pb-2.5"><input type="checkbox" name="mobile" value="1" class="rounded text-brand-600" @checked(old('mobile', $widget->setting('mobile')))> On mobile</label>
            </div>
            <x-btn icon="check">Save widget</x-btn>
        </form>

        <div class="xl:col-span-3 space-y-6">
            <div class="{{ $card }} overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2 text-xs text-slate-400">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-300"></span><span class="w-2.5 h-2.5 rounded-full bg-amber-300"></span><span class="w-2.5 h-2.5 rounded-full bg-emerald-300"></span>
                    <span class="ml-3 flex-1 bg-slate-50 rounded-lg px-3 py-1">your-website.com</span> Live preview
                </div>
                <div class="relative h-80 bg-gradient-to-b from-slate-50 to-white p-6">
                    <div class="space-y-3 max-w-sm opacity-60"><div class="h-4 w-2/3 bg-slate-200 rounded"></div><div class="h-3 w-full bg-slate-100 rounded"></div><div class="h-3 w-5/6 bg-slate-100 rounded"></div><div class="h-24 w-full bg-slate-100 rounded-xl"></div></div>
                    <div id="pv" class="absolute bottom-5 right-5 flex flex-col items-end gap-3" style="font-family:system-ui">
                        <div class="w-72 rounded-2xl shadow-xl overflow-hidden bg-white">
                            <div id="pv-head" class="px-4 py-3 text-white font-semibold text-sm"></div>
                            <div class="p-4" style="background:#ECE5DD"><div id="pv-greet" class="bg-white rounded-xl rounded-tl-none px-3 py-2 text-sm whitespace-pre-line"></div></div>
                            <div class="p-3"><div id="pv-go" class="rounded-full text-white text-center text-sm font-semibold py-2">Start chat</div></div>
                        </div>
                        <div id="pv-btn" class="inline-flex items-center gap-2 rounded-full px-4 py-3 text-white font-semibold text-sm shadow-lg"><x-icon name="whatsapp" class="w-5 h-5" /><span id="pv-text"></span></div>
                    </div>
                </div>
            </div>

            <div class="{{ $card }} p-6">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-semibold">Paste this code on your website</div>
                    <span class="text-xs px-2 py-1 rounded-full {{ $widget->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $widget->active ? 'Live' : 'Switched off' }}</span>
                </div>
                <p class="text-sm text-slate-500 mb-3">Add it just before <code>&lt;/body&gt;</code>. On WordPress, use a free plugin like "WPCode" (Header &amp; Footer) and paste it in the footer. On Shopify: Online Store → Themes → Edit code → <code>theme.liquid</code>.</p>
                <div class="flex gap-2">
                    <code id="snippet" class="flex-1 bg-slate-900 text-emerald-300 rounded-xl px-4 py-3 text-xs break-all">{{ $snippet }}</code>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('snippet').textContent); this.textContent='Copied ✓'" class="px-4 rounded-xl bg-brand-600 text-white text-sm font-semibold">Copy</button>
                </div>
                <div class="mt-4 text-sm text-slate-600 flex items-center gap-2"><x-icon name="chart" class="w-4 h-4 text-brand-600" /><b>{{ number_format($widget->events_count) }}</b> chats started from the widget{{ $widget->last_event_at ? ' · last '.$widget->last_event_at->diffForHumans() : '' }}</div>
            </div>
        </div>
    </div>
    <script>
        (() => {
            const f = document.getElementById('widget-form');
            const v = n => f.elements[n].value;
            const draw = () => {
                const c = v('color');
                document.getElementById('pv-head').textContent = v('brand') || 'WhatsApp';
                document.getElementById('pv-greet').textContent = v('greeting');
                document.getElementById('pv-text').textContent = v('button_text');
                ['pv-head', 'pv-go', 'pv-btn'].forEach(id => document.getElementById(id).style.background = c);
                const pv = document.getElementById('pv');
                pv.style.left = v('position') === 'left' ? '1.25rem' : ''; pv.style.right = v('position') === 'left' ? '' : '1.25rem';
                pv.classList.toggle('items-end', v('position') !== 'left'); pv.classList.toggle('items-start', v('position') === 'left');
            };
            f.addEventListener('input', draw); f.addEventListener('change', draw); draw();
        })();
    </script>
@endif

{{-- ===================== Link & QR ===================== --}}
@if ($tab === 'link')
    @php $link = $phone ? 'https://wa.me/'.$phone.($text !== '' ? '?text='.rawurlencode($text) : '') : null; @endphp
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <form method="GET" action="{{ route('integrations.index') }}" class="{{ $card }} p-6 space-y-4 h-fit">
            <input type="hidden" name="tab" value="link">
            <div class="font-semibold">Create a WhatsApp link</div>
            <div>
                <label class="{{ $label }}">WhatsApp number (with country code)</label>
                <input name="phone" value="{{ $phone }}" required maxlength="20" placeholder="919876543210" class="{{ $input }}" list="wa-numbers">
                <datalist id="wa-numbers">@foreach ($numbers as $n => $l)<option value="{{ $n }}">{{ $l }}</option>@endforeach</datalist>
            </div>
            <div><label class="{{ $label }}">Message the customer sends (optional)</label><textarea name="text" rows="3" maxlength="500" placeholder="Hi, I want to know about the Diwali offer" class="{{ $input }}">{{ $text }}</textarea></div>
            <x-btn icon="qr">Make link &amp; QR</x-btn>
            @if (request()->has('phone') && ! $phone)<p class="text-sm text-red-600">Enter a valid number with country code.</p>@endif
        </form>

        <div class="xl:col-span-2 {{ $card }} p-6">
            @if ($link)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                    <div class="rounded-2xl border border-slate-100 p-4 bg-white"><img src="{{ route('integrations.qr', ['phone' => $phone, 'text' => $text]) }}" alt="WhatsApp QR code" class="w-full"></div>
                    <div class="space-y-4">
                        <div>
                            <div class="{{ $label }}">Your WhatsApp link</div>
                            <div class="flex gap-2">
                                <code id="wa-link" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm break-all">{{ $link }}</code>
                                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('wa-link').textContent); this.textContent='Copied ✓'" class="px-4 rounded-xl bg-brand-600 text-white text-sm font-semibold">Copy</button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-btn :href="route('integrations.qr', ['phone' => $phone, 'text' => $text, 'download' => 1])" icon="download">Download QR (SVG)</x-btn>
                            <x-btn :href="$link" variant="outline" icon="whatsapp" target="_blank">Test link</x-btn>
                        </div>
                        <ul class="text-sm text-slate-600 space-y-1.5">
                            <li>• Put the link in your Instagram / Facebook bio.</li>
                            <li>• Print the QR on bills, visiting cards and your shop counter.</li>
                            <li>• Customers scan, WhatsApp opens with your message ready to send.</li>
                        </ul>
                    </div>
                </div>
            @else
                <div class="text-center text-slate-500 py-16"><x-icon name="qr" class="w-10 h-10 mx-auto text-brand-300 mb-3" />Enter your number to get a WhatsApp link and QR code.</div>
            @endif
        </div>
    </div>
@endif

{{-- ===================== Shopify & WooCommerce ===================== --}}
@if ($tab === 'stores')
    @php $current = array_key_exists(request('store'), Integration::STORES) ? request('store') : 'shopify'; $s = $stores[$current]; @endphp
    <div class="flex gap-2 mb-5">
        @foreach ($stores as $type => $st)
            <a href="{{ route('integrations.index', ['tab' => 'stores', 'store' => $type]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border {{ $current === $type ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-600' }}">
                {{ Integration::STORES[$type] }} <span class="w-2 h-2 rounded-full {{ $st->active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="space-y-6">
            <div class="{{ $card }} p-6">
                <div class="font-semibold mb-3">Connect {{ Integration::STORES[$current] }}</div>
                <ol class="text-sm text-slate-600 space-y-2 list-decimal pl-4">
                    @if ($current === 'shopify')
                        <li>In Shopify admin open <b>Settings → Notifications → Webhooks</b>.</li>
                        <li>Click <b>Create webhook</b>, format <b>JSON</b>, and paste the URL below.</li>
                        <li>Create one for each event: <b>Order creation</b>, <b>Order fulfillment</b>, <b>Order cancellation</b>.</li>
                        <li>Copy the signing secret shown at the bottom of that page ("Your webhooks will be signed with…") and paste it here.</li>
                    @else
                        <li>In WordPress open <b>WooCommerce → Settings → Advanced → Webhooks</b>.</li>
                        <li>Add a webhook with topic <b>Order created</b>, status <b>Active</b>, and paste the URL and secret below.</li>
                        <li>Add a second webhook with topic <b>Order updated</b> (for shipped/completed and cancelled orders).</li>
                    @endif
                </ol>
                <div class="mt-4 space-y-3">
                    <div>
                        <div class="{{ $label }}">Webhook URL</div>
                        <div class="flex gap-2"><code id="hook-url" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs break-all">{{ $s->webhookUrl() }}</code>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('hook-url').textContent); this.textContent='✓'" class="px-3 rounded-xl bg-brand-600 text-white text-xs font-semibold">Copy</button></div>
                    </div>
                    @if ($current === 'woocommerce')
                        <div>
                            <div class="{{ $label }}">Secret</div>
                            <div class="flex gap-2"><code id="hook-secret" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs break-all">{{ $s->secret }}</code>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('hook-secret').textContent); this.textContent='✓'" class="px-3 rounded-xl bg-brand-600 text-white text-xs font-semibold">Copy</button></div>
                            <form method="POST" action="{{ route('integrations.secret', 'woocommerce') }}" class="mt-2" onsubmit="return confirm('Create a new secret? You must update it in WooCommerce too.')">@csrf<button class="text-xs text-slate-500 hover:text-brand-700">Create new secret</button></form>
                        </div>
                    @endif
                </div>
            </div>
            <div class="{{ $card }} p-6 text-sm text-slate-600">
                <div class="font-semibold text-slate-800 mb-2">How it works</div>
                <p>Every order saves the buyer as a contact (tag <code>{{ strtolower(Integration::STORES[$current]) }}-customer</code>). If you turn on a message below, we send your approved template automatically. Meta charges each order message at the template's category rate (Utility is cheapest).</p>
                <p class="mt-2">{{ number_format($s->events_count) }} orders received{{ $s->last_event_at ? ' · last '.$s->last_event_at->diffForHumans() : '' }}.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('integrations.store', $current) }}" class="xl:col-span-2 {{ $card }} p-6 space-y-5" id="store-form">
            @csrf @method('PUT')
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="font-semibold">Order messages</div>
                <label class="inline-flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="active" value="1" class="rounded text-brand-600" @checked(old('active', $s->active))> Integration on</label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div><label class="{{ $label }}">Store name (used in messages)</label><input name="store_name" value="{{ old('store_name', $s->setting('store_name')) }}" maxlength="60" placeholder="Your store name" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Default country code</label><input name="country_code" value="{{ old('country_code', $s->setting('country_code', '91')) }}" maxlength="5" class="{{ $input }}"></div>
                @if ($current === 'shopify')
                    <div><label class="{{ $label }}">Webhook signing secret</label><input name="secret" type="password" autocomplete="off" placeholder="{{ $s->secret ? '•••••••• saved' : 'Paste from Shopify' }}" class="{{ $input }}"></div>
                @endif
            </div>

            @if ($templates->isEmpty())
                <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">You have no approved templates yet. <a href="{{ route('templates.index') }}" class="font-semibold underline">Create a Utility template</a> like "Hi @{{1}}, your order @{{2}} is confirmed." first.</div>
            @endif

            @foreach (Integration::STORE_EVENTS as $e => [$title, $desc])
                @php $cfg = $s->setting('events.'.$e, []); @endphp
                <div class="rounded-2xl border border-slate-200 p-4 store-event" data-vars='@json(old("events.$e.vars", $cfg['vars'] ?? []))'>
                    <div class="flex items-start justify-between gap-3">
                        <div><div class="font-semibold text-sm">{{ $title }}</div><div class="text-xs text-slate-500">{{ $desc }}</div></div>
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="events[{{ $e }}][enabled]" value="1" class="rounded text-brand-600" @checked(old("events.$e.enabled", $cfg['enabled'] ?? false))> Send WhatsApp</label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                        <select name="events[{{ $e }}][template_id]" class="{{ $input }} tpl">
                            <option value="">Choose template…</option>
                            @foreach ($templates as $t)<option value="{{ $t->id }}" @selected((int) old("events.$e.template_id", $cfg['template_id'] ?? 0) === $t->id)>{{ $t->name }} ({{ $t->language }})</option>@endforeach
                        </select>
                        <div class="text-xs text-slate-500 bg-slate-50 rounded-xl px-3 py-2 preview whitespace-pre-line">Template text will show here.</div>
                    </div>
                    <div class="vars grid grid-cols-1 md:grid-cols-3 gap-2 mt-3" data-event="{{ $e }}"></div>
                    <input type="hidden" name="events[{{ $e }}][header_var]" value="{{ $cfg['header_var'] ?? 'store' }}">
                </div>
            @endforeach
            <x-btn icon="check">Save {{ Integration::STORES[$current] }} settings</x-btn>
        </form>
    </div>

    <div class="{{ $card }} overflow-hidden mt-6">
        <div class="px-6 py-4 border-b border-slate-100 font-semibold">Recent orders</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-slate-500 bg-slate-50"><tr><th class="text-left px-6 py-2">When</th><th class="text-left px-3 py-2">Store</th><th class="text-left px-3 py-2">Order</th><th class="text-left px-3 py-2">Event</th><th class="text-left px-3 py-2">Customer</th><th class="text-left px-3 py-2">WhatsApp</th></tr></thead>
                <tbody>
                @forelse ($events as $ev)
                    <tr class="border-t border-slate-50">
                        <td class="px-6 py-2.5 text-slate-500 whitespace-nowrap">{{ $ev->created_at->diffForHumans() }}</td>
                        <td class="px-3 py-2.5">{{ Integration::STORES[$ev->integration?->type] ?? '-' }}</td>
                        <td class="px-3 py-2.5 font-medium">{{ $ev->order_number }}</td>
                        <td class="px-3 py-2.5">{{ Integration::STORE_EVENTS[$ev->event][0] ?? $ev->event }}</td>
                        <td class="px-3 py-2.5">@if ($ev->contact)<a href="{{ route('contacts.show', $ev->contact) }}" class="text-brand-700">{{ $ev->contact->name ?: $ev->contact->phone }}</a>@else - @endif</td>
                        <td class="px-3 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full {{ IntegrationEvent::STATUS_STYLES[$ev->status] ?? '' }}">{{ ucfirst($ev->status) }}</span> <span class="text-xs text-slate-500">{{ $ev->note }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-slate-500">No orders yet. Connect your store and place a test order.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (() => {
            const meta = @json($templateMeta);
            const fields = @json(Integration::ORDER_FIELDS);
            const defaults = ['name', 'order_number', 'total', 'items', 'tracking_url'];
            document.querySelectorAll('.store-event').forEach(box => {
                const sel = box.querySelector('.tpl'), wrap = box.querySelector('.vars'), prev = box.querySelector('.preview');
                let saved = JSON.parse(box.dataset.vars || '[]');
                const draw = () => {
                    const t = meta[sel.value];
                    prev.textContent = t ? t.body : 'Template text will show here.';
                    const keep = [...wrap.querySelectorAll('select')].map(s => s.value);
                    if (keep.length) saved = keep;
                    wrap.innerHTML = '';
                    for (let i = 0; i < (t ? t.vars : 0); i++) {
                        const s = document.createElement('select');
                        s.name = `events[${wrap.dataset.event}][vars][]`;
                        s.className = @json($input);
                        const cur = saved[i] || defaults[i] || 'name';
                        s.innerHTML = Object.entries(fields).map(([k, l]) => `<option value="${k}" ${k === cur ? 'selected' : ''}>{{ '{' }}{{ '{' }}${i + 1}}} = ${l}</option>`).join('');
                        wrap.appendChild(s);
                    }
                };
                sel.addEventListener('change', draw); draw();
            });
        })();
    </script>
@endif

{{-- ===================== Sheets, Zapier & more ===================== --}}
@if ($tab === 'more')
    @php
        $apiBase = url('/api/v1');
        $sheetScript = <<<JS
// Extensions → Apps Script → paste, set API_KEY, then add a trigger: onFormSubmit or "On change".
const API_KEY = 'sdk_your_api_key';
function syncContacts() {
  const rows = SpreadsheetApp.getActiveSheet().getDataRange().getValues();
  rows.slice(1).forEach(([name, phone, email, tag]) => {
    if (!phone) return;
    UrlFetchApp.fetch('{$apiBase}/contacts', {
      method: 'post', contentType: 'application/json', muteHttpExceptions: true,
      headers: { Authorization: 'Bearer ' + API_KEY },
      payload: JSON.stringify({ name, phone: String(phone), email: email || null, tags: tag ? [tag] : ['google-sheet'] }),
    });
  });
}
JS;
    @endphp
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="{{ $card }} p-6">
            <div class="flex items-center gap-3 mb-3"><span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center"><x-icon name="list" class="w-5 h-5" /></span><div class="font-semibold">Google Sheets → Contacts</div></div>
            <p class="text-sm text-slate-600 mb-3">Keep a sheet with columns <b>Name, Phone, Email, Tag</b>. This script adds every row as a contact. Works great with Google Forms lead sheets.</p>
            <ol class="text-sm text-slate-600 list-decimal pl-4 space-y-1 mb-3">
                <li>Create an API key in <a href="{{ route('developers.index') }}" class="text-brand-700 font-semibold">API &amp; Webhooks</a>.</li>
                <li>In the sheet open <b>Extensions → Apps Script</b>, paste the code and put your key.</li>
                <li>Run <code>syncContacts</code> once, then add a trigger to run it automatically.</li>
            </ol>
            <div class="relative">
                <pre id="sheet-code" class="bg-slate-900 text-slate-100 rounded-xl p-4 text-xs overflow-x-auto">{{ $sheetScript }}</pre>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('sheet-code').textContent); this.textContent='Copied ✓'" class="absolute top-2 right-2 px-3 py-1 rounded-lg bg-white/10 text-white text-xs">Copy</button>
            </div>
        </div>
        <div class="space-y-6">
            @foreach ([
                ['Zapier & Make', 'bolt', 'Use a "Webhooks" step to call our API (send template, add contact) and point our outgoing webhooks to a Zapier/Make catch hook to react to replies and deliveries. Connects 5,000+ apps.'],
                ['Zoho, HubSpot & other CRMs', 'users', 'Send new leads to /api/v1/contacts and get every customer reply on your CRM with the message.received webhook.'],
                ['WordPress website', 'chat', 'Add the chat widget with any header/footer plugin. For WooCommerce orders, use the Shopify & WooCommerce tab.'],
                ['Your own software', 'code', 'Billing, booking or ERP software can send OTPs, invoices and reminders with one API call.'],
            ] as [$h, $i, $d])
                <div class="{{ $card }} p-5 flex gap-4">
                    <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 grid place-items-center shrink-0"><x-icon :name="$i" class="w-5 h-5" /></span>
                    <div><div class="font-semibold">{{ $h }}</div><p class="text-sm text-slate-600 mt-1">{{ $d }}</p></div>
                </div>
            @endforeach
            <x-btn :href="route('developers.index', ['tab' => 'docs'])" icon="code">Open API docs</x-btn>
        </div>
    </div>
@endif
@endsection
