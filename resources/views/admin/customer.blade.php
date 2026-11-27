@extends('layouts.admin', ['title' => $w->name])
@php
    use App\Support\Num; use App\Http\Controllers\Admin\AdminController;
    [$sLabel, $sCls] = AdminController::STATUSES[$w->status()];
    $input = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm';
    $card = 'bg-white rounded-2xl border border-slate-200 shadow-sm p-6';
@endphp

@section('content')
<x-page-header :title="$w->name" :subtitle="'Customer since '.$w->created_at->format('d M Y').' · '.$w->owner?->email">
    <x-btn :href="route('admin.customers')" variant="outline">← Customers</x-btn>
    <form method="POST" action="{{ route('admin.customers.impersonate', $w) }}">@csrf<x-btn icon="eye">Open their panel</x-btn></form>
</x-page-header>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="{{ $card }}">
        <div class="text-sm text-slate-500">Status</div>
        <div class="mt-2"><span class="text-sm font-semibold px-3 py-1 rounded-full {{ $sCls }}">{{ $sLabel }}</span></div>
        <div class="text-xs text-slate-500 mt-3">{{ $w->planName() }} · {{ $w->accessEndsAt() ? 'ends '.$w->accessEndsAt()->format('d M Y') : '' }}</div>
    </div>
    <x-stat-card label="Paid so far" :value="'₹'.Num::indian($payments->where('status', 'paid')->sum('total'))" :sub="$payments->where('status', 'paid')->count().' payments'" icon="card" tone="green" />
    <x-stat-card label="Messages sent" :value="Num::indian($stats['messages'])" :sub="$stats['campaigns'].' campaigns'" icon="send" />
    <x-stat-card label="Last activity" :value="$stats['lastActive'] ? \Illuminate\Support\Carbon::parse($stats['lastActive'])->diffForHumans() : 'Never'" sub="Last message in or out" icon="clock" tone="sky" />
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="{{ $card }}">
            <div class="font-semibold mb-4">Usage vs plan limits</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($usage as $u)
                    @php $pct = $u['limit'] ? min(100, round($u['used'] * 100 / max(1, $u['limit']))) : 5; @endphp
                    <div>
                        <div class="text-xs text-slate-500">{{ $u['label'] }}</div>
                        <div class="font-bold">{{ Num::indian($u['used']) }} <span class="text-xs font-normal text-slate-500">/ {{ $u['limit'] === null ? '∞' : Num::indian($u['limit']) }}</span></div>
                        <div class="h-1.5 rounded-full bg-slate-100 mt-1.5 overflow-hidden"><div class="h-full rounded-full {{ $pct >= 90 ? 'bg-amber-500' : 'bg-brand-600' }}" style="width: {{ $pct }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="{{ $card }}">
            <div class="font-semibold mb-3">Team ({{ $members->count() }})</div>
            <div class="divide-y divide-slate-100">
                @foreach ($members as $m)
                    <div class="flex items-center gap-3 py-2.5 text-sm">
                        <span class="w-8 h-8 rounded-full bg-brand-50 text-brand-700 grid place-items-center font-semibold uppercase text-xs">{{ mb_substr($m->name, 0, 1) }}</span>
                        <span class="flex-1">{{ $m->name }} <span class="text-slate-500">· {{ $m->email }}</span></span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 capitalize">{{ $m->pivot->role }}</span>
                    </div>
                @endforeach
            </div>
            <div class="font-semibold mt-5 mb-3">WhatsApp numbers ({{ $numbers->count() }})</div>
            @forelse ($numbers as $n)
                <div class="flex items-center gap-3 py-2 text-sm">
                    <x-icon name="whatsapp" class="w-4 h-4 text-emerald-600" />
                    <span class="flex-1">{{ $n->label() }}</span>
                    <span class="text-xs text-slate-500">{{ $n->status }} · quality {{ $n->quality_rating ?? '—' }} · limit {{ $n->messaging_limit ?? '—' }}</span>
                </div>
            @empty
                <div class="text-sm text-slate-500">No number connected yet.</div>
            @endforelse
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 font-semibold">Payments</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $p)
                        <tr>
                            <td class="px-6 py-3">{{ ($p->paid_at ?? $p->created_at)->format('d M Y') }}</td>
                            <td class="px-3 py-3">{{ $p->planName() }} <span class="text-xs text-slate-400">{{ $p->gateway }}</span></td>
                            <td class="px-3 py-3 text-right">₹{{ number_format($p->total, 2) }}</td>
                            <td class="px-3 py-3">{{ ucfirst($p->status) }}</td>
                            <td class="px-6 py-3 text-right">@if ($p->invoice)<a href="{{ route('admin.invoices.show', $p->invoice) }}" target="_blank" class="text-brand-700 font-semibold">{{ $p->invoice->number }}</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td class="px-6 py-8 text-center text-slate-500">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <aside class="space-y-6">
        <form method="POST" action="{{ route('admin.customers.plan', $w) }}" class="{{ $card }} space-y-3">
            @csrf
            <div class="font-semibold">Give plan or extend trial</div>
            <p class="text-xs text-slate-500">For cash or bank-transfer payments, free months or a longer trial. No invoice is made.</p>
            <select name="action" class="{{ $input }}" onchange="document.getElementById('plan-fields').classList.toggle('hidden', this.value !== 'plan')">
                <option value="plan">Set a paid plan</option>
                <option value="trial">Extend free trial</option>
            </select>
            <div id="plan-fields" class="grid grid-cols-2 gap-2">
                <select name="plan" class="{{ $input }}">@foreach (config('billing.plans') as $k => $p)<option value="{{ $k }}" @selected($w->plan === $k)>{{ $p['name'] }}</option>@endforeach</select>
                <select name="cycle" class="{{ $input }}">@foreach (config('billing.cycles') as $k => $c)<option value="{{ $k }}" @selected($w->plan_cycle === $k)>{{ $c['label'] }}</option>@endforeach</select>
            </div>
            <label class="block text-sm font-medium text-slate-700">Active until</label>
            <input type="date" name="ends_at" value="{{ now()->addMonth()->toDateString() }}" min="{{ now()->addDay()->toDateString() }}" class="{{ $input }}" required>
            <x-btn icon="check">Save</x-btn>
        </form>

        <form method="POST" action="{{ route('admin.customers.note', $w) }}" class="{{ $card }} space-y-3">
            @csrf
            <div class="font-semibold">Private note</div>
            <textarea name="admin_note" rows="4" maxlength="1000" placeholder="Only super admins see this." class="{{ $input }}">{{ $w->admin_note }}</textarea>
            <x-btn variant="outline">Save note</x-btn>
        </form>

        <div class="{{ $card }}">
            <div class="font-semibold">Billing details</div>
            <div class="text-sm text-slate-600 mt-2 space-y-0.5">
                <div>{{ $w->billing_name ?: '—' }}</div>
                @if ($w->billing_gstin)<div>GSTIN {{ $w->billing_gstin }}</div>@endif
                <div>{{ $w->billing_address }}</div>
                <div>{{ $w->billing_state }}</div>
                <div>{{ $w->billing_phone }} {{ $w->billing_email }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.customers.suspend', $w) }}" class="{{ $card }}" onsubmit="return confirm('{{ $w->isSuspended() ? 'Turn this account back on?' : 'Suspend this account? Its team will not be able to use the panel.' }}')">
            @csrf
            <div class="font-semibold">{{ $w->isSuspended() ? 'Account suspended' : 'Suspend account' }}</div>
            <p class="text-xs text-slate-500 mt-1 mb-3">{{ $w->isSuspended() ? 'Suspended on '.$w->suspended_at->format('d M Y').'. Nobody in this team can use the panel.' : 'Use this for misuse or unpaid dues. You can turn it back on any time.' }}</p>
            <x-btn :variant="$w->isSuspended() ? 'outline' : 'danger'">{{ $w->isSuspended() ? 'Turn account back on' : 'Suspend' }}</x-btn>
        </form>
    </aside>
</div>
@endsection
