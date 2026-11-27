@extends('layouts.app', ['title' => 'Subscription'])

@php
    use App\Services\Billing\Billing;
    use App\Support\Num;
    $input = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none';
    $label = 'block text-sm font-medium text-slate-700 mb-1.5';
    $active = $workspace->hasActivePlan();
    $end = $workspace->accessEndsAt();
    $gatewayNames = ['razorpay' => 'Razorpay', 'cashfree' => 'Cashfree', 'test' => 'Test payment (local only)'];
@endphp

@section('content')
<x-page-header title="Subscription" subtitle="Your plan, usage, payments and GST invoices." />

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-950 via-brand-800 to-brand-600 text-white p-6 mb-6">
    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/5"></div>
    <div class="relative grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
        <div class="lg:col-span-1">
            <div class="text-brand-100 text-sm">Current plan</div>
            <div class="text-3xl font-bold mt-1">{{ $workspace->planName() }}</div>
            <div class="text-sm text-brand-100 mt-2">
                @if ($workspace->isExpired())
                    Ended {{ $end?->format('d M Y') }}. Choose a plan below to continue.
                @else
                    {{ $active ? 'Renews / ends' : 'Trial ends' }} on <b class="text-white">{{ $end->format('d M Y') }}</b> · {{ $workspace->daysLeft() }} days left
                    @if ($active && $workspace->plan_cycle)<br>Billing: {{ config("billing.cycles.{$workspace->plan_cycle}.label") }}@endif
                @endif
            </div>
        </div>
        <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ($usage as $u)
                @php $pct = $u['limit'] ? min(100, round($u['used'] * 100 / max(1, $u['limit']))) : 8; @endphp
                <div class="rounded-2xl bg-white/10 border border-white/10 p-4">
                    <div class="text-xs text-brand-100">{{ $u['label'] }}</div>
                    <div class="text-lg font-bold mt-1">{{ Num::indian($u['used']) }} <span class="text-sm font-normal text-brand-100">/ {{ $u['limit'] === null ? 'Unlimited' : Num::indian($u['limit']) }}</span></div>
                    <div class="h-1.5 rounded-full bg-white/15 mt-2 overflow-hidden"><div class="h-full rounded-full {{ $pct >= 90 ? 'bg-amber-300' : 'bg-white' }}" style="width: {{ $pct }}%"></div></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h2 class="text-lg font-bold text-slate-900">{{ $active ? 'Change or renew your plan' : 'Choose a plan' }}</h2>
    <div id="cycles" class="inline-flex rounded-xl bg-white border border-slate-200 p-1">
        @foreach ($cycles as $key => $c)
            <button type="button" data-cycle="{{ $key }}" class="cycle-btn px-4 py-2 text-sm font-semibold rounded-lg">
                {{ $c['label'] }} @if ($c['discount'])<span class="text-xs text-emerald-600">−{{ $c['discount'] }}%</span>@endif
            </button>
        @endforeach
    </div>
</div>

@if ($setupFee > 0)
    <p class="text-sm text-slate-600 mb-3">A one-time setup fee of <b>₹{{ Num::indian($setupFee) }}</b> + GST is added to your first payment.</p>
@endif
<p class="text-xs text-slate-500 mb-4">Plans are the panel rental only. WhatsApp message charges are paid by you directly to Meta (card added in your Meta Business account).</p>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
    @foreach ($plans as $key => $p)
        @php $current = $active && $workspace->plan === $key; @endphp
        <div class="relative bg-white rounded-2xl border {{ ! empty($p['popular']) ? 'border-brand-500 ring-2 ring-brand-100' : 'border-slate-200' }} shadow-sm p-6 flex flex-col">
            @if (! empty($p['popular']))<span class="absolute -top-3 left-6 text-xs font-semibold px-3 py-1 rounded-full bg-brand-600 text-white">Most popular</span>@endif
            <div class="flex items-center justify-between">
                <div class="font-bold text-lg">{{ $p['name'] }}</div>
                @if ($current)<span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">● Your plan</span>@endif
            </div>
            <div class="text-sm text-slate-500">{{ $p['tagline'] }}</div>
            @foreach ($cycles as $ck => $c)
                @php $price = $reseller ? round($reseller->price($key) * $c['months'] * (100 - $c['discount']) / 100, 2) : Billing::price($key, $ck); @endphp
                <div class="price mt-4" data-cycle="{{ $ck }}">
                    <div class="text-3xl font-bold text-slate-900">₹{{ Num::indian($price / $c['months']) }}<span class="text-sm font-normal text-slate-500">/month</span></div>
                    <div class="text-xs text-slate-500 mt-1">
                        ₹{{ Num::indian($price) }} {{ $c['months'] > 1 ? 'billed every '.$c['months'].' months' : 'billed monthly' }} + {{ config('billing.gst_rate') }}% GST
                        = <b>₹{{ number_format($price + Billing::gst($price), 2) }}</b>
                    </div>
                </div>
            @endforeach
            <ul class="mt-5 space-y-2 text-sm flex-1">
                @foreach ($p['limits'] as $lk => $lv)
                    <li class="flex gap-2"><x-icon name="check" class="w-4 h-4 text-brand-600 mt-0.5" />{{ $lv === null ? 'Unlimited' : Num::indian($lv) }} {{ strtolower(config("billing.limit_labels.$lk")) }}</li>
                @endforeach
                @foreach ($p['features'] as $f)
                    <li class="flex gap-2"><x-icon name="check" class="w-4 h-4 text-emerald-600 mt-0.5" />{{ $f }}</li>
                @endforeach
            </ul>
            @if ($reseller)
                <div class="mt-6 rounded-xl bg-brand-50 border border-brand-100 p-3 text-sm text-brand-800 text-center">
                    {{ $current ? 'To renew' : 'To activate' }}, contact {{ $reseller->name }}
                    @if ($reseller->support_phone)<br><a href="https://wa.me/{{ preg_replace('/\D+/', '', $reseller->support_phone) }}" class="font-semibold">{{ $reseller->support_phone }}</a>@endif
                    @if ($reseller->support_email)<br><a href="mailto:{{ $reseller->support_email }}" class="font-semibold">{{ $reseller->support_email }}</a>@endif
                </div>
            @else
            <form method="POST" action="{{ route('subscription.checkout') }}" class="mt-6 space-y-2">
                @csrf
                <input type="hidden" name="plan" value="{{ $key }}">
                <input type="hidden" name="cycle" class="cycle-input" value="monthly">
                @if (count($gateways) > 1)
                    <select name="gateway" class="{{ $input }}">
                        @foreach ($gateways as $g)<option value="{{ $g }}">Pay with {{ $gatewayNames[$g] }}</option>@endforeach
                    </select>
                @elseif ($gateways)
                    <input type="hidden" name="gateway" value="{{ $gateways[0] }}">
                @endif
                <button @disabled(! $gateways) class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ ! empty($p['popular']) ? 'bg-brand-600 hover:bg-brand-700 text-white shadow-sm shadow-brand-600/30' : 'bg-white border border-slate-300 hover:border-brand-400 hover:text-brand-700 text-slate-700' }} disabled:opacity-50">
                    {{ $current ? 'Renew '.$p['name'] : ($active ? 'Switch to '.$p['name'] : 'Choose '.$p['name']) }}
                </button>
                @if (! $gateways)<p class="text-xs text-slate-500 text-center">Online payment is being set up. Contact support to activate.</p>@endif
            </form>
            @endif
        </div>
    @endforeach
</div>
<p class="text-xs text-slate-500 -mt-5 mb-8">WhatsApp conversation charges are billed by Meta directly to your own Meta account. Switching plans starts the new plan today and adds the unused value of your old plan as extra days. Renewing the same plan adds time after your current end date.</p>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <form method="POST" action="{{ route('subscription.billing') }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <div class="font-semibold">Billing details</div>
            <p class="text-xs text-slate-500">Shown on your GST invoice. Add your GSTIN to claim input tax credit.</p>
        </div>
        <div><label class="{{ $label }}">Business name</label><input name="billing_name" value="{{ old('billing_name', $workspace->billing_name ?: $workspace->name) }}" required class="{{ $input }}"></div>
        <div><label class="{{ $label }}">GSTIN <span class="text-slate-400 font-normal">(optional)</span></label><input name="billing_gstin" value="{{ old('billing_gstin', $workspace->billing_gstin) }}" maxlength="15" placeholder="08ABCDE1234F1Z5" class="{{ $input }} uppercase"></div>
        <div><label class="{{ $label }}">Address</label><textarea name="billing_address" rows="2" class="{{ $input }}">{{ old('billing_address', $workspace->billing_address) }}</textarea></div>
        <div>
            <label class="{{ $label }}">State</label>
            <select name="billing_state" required class="{{ $input }}">
                <option value="">Choose…</option>
                @foreach (config('billing.states') as $st)<option @selected(old('billing_state', $workspace->billing_state) === $st)>{{ $st }}</option>@endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="{{ $label }}">Email</label><input name="billing_email" type="email" value="{{ old('billing_email', $workspace->billing_email) }}" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">Phone</label><input name="billing_phone" value="{{ old('billing_phone', $workspace->billing_phone) }}" placeholder="9876543210" class="{{ $input }}"></div>
        </div>
        <x-btn icon="check">Save details</x-btn>
    </form>

    <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-semibold">Payments & invoices</div>
        @if ($payments->isEmpty())
            <div class="p-10 text-center text-sm text-slate-500">No payments yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr><th class="text-left px-6 py-3">Date</th><th class="text-left px-3 py-3">Plan</th><th class="text-right px-3 py-3">Amount</th><th class="text-left px-3 py-3">Status</th><th class="text-right px-6 py-3">Invoice</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payments as $pay)
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap">{{ ($pay->paid_at ?? $pay->created_at)->format('d M Y') }}</td>
                                <td class="px-3 py-3">{{ $pay->planName() }}<div class="text-xs text-slate-400">{{ $gatewayNames[$pay->gateway] ?? $pay->gateway }}</div></td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">₹{{ number_format($pay->total, 2) }}</td>
                                <td class="px-3 py-3">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ ['paid' => 'bg-emerald-50 text-emerald-700', 'failed' => 'bg-red-50 text-red-700'][$pay->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ['paid' => 'Paid', 'failed' => 'Failed', 'created' => 'Not completed'][$pay->status] ?? $pay->status }}</span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if ($pay->invoice)<a href="{{ route('subscription.invoice', $pay->invoice) }}" target="_blank" class="font-semibold text-brand-700 hover:underline">{{ $pay->invoice->number }}</a>@else<span class="text-slate-400">—</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    function setCycle(cycle) {
        document.querySelectorAll('.cycle-btn').forEach(b => b.className = 'cycle-btn px-4 py-2 text-sm font-semibold rounded-lg ' + (b.dataset.cycle === cycle ? 'bg-brand-600 text-white' : 'text-slate-600 hover:text-brand-700'));
        document.querySelectorAll('.price').forEach(p => p.classList.toggle('hidden', p.dataset.cycle !== cycle));
        document.querySelectorAll('.cycle-input').forEach(i => i.value = cycle);
    }
    document.querySelectorAll('.cycle-btn').forEach(b => b.addEventListener('click', () => setCycle(b.dataset.cycle)));
    setCycle(@json($workspace->plan_cycle ?: 'monthly'));
</script>
@endpush
