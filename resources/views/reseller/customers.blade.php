@extends('layouts.reseller', ['title' => 'Customers'])
@php
    use App\Http\Controllers\Admin\AdminController;
    use App\Services\Resellers\Wallet;
    use App\Support\Num;
    $in = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none';
    $lb = 'block text-xs font-semibold text-slate-500 mb-1';
    $planOptions = collect(config('billing.plans'))->flatMap(fn ($p, $k) => collect(config('billing.cycles'))->mapWithKeys(fn ($c, $ck) => ["$k|$ck" => $p['name'].' · '.$c['label'].' — ₹'.Num::indian(Wallet::cost($me, $k, $ck) / 100).' from wallet']));
@endphp

@section('content')
<x-page-header title="Customers" subtitle="Businesses using your panel. Create, activate, renew and log in for them." />

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-4">
        <form class="flex gap-2">
            <input name="q" value="{{ $q }}" placeholder="Search business or email…" class="{{ $in }}">
            <x-btn icon="search">Search</x-btn>
        </form>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            @forelse ($customers as $w)
                @php [$label, $cls] = AdminController::STATUSES[$w->status()]; @endphp
                <div class="px-5 py-4 border-b border-slate-100">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex-1 min-w-[12rem]">
                            <div class="font-semibold text-slate-900">{{ $w->name }}</div>
                            <div class="text-xs text-slate-500">{{ $w->owner?->email }} · {{ $w->planName() }} · ends {{ $w->accessEndsAt()?->format('d M Y') ?? '—' }}</div>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $cls }}">{{ $label }}</span>
                        @if ($w->renew_failed_at)<span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-700">Renewal failed: low wallet</span>@endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <form method="POST" action="{{ route('reseller.customers.activate', $w) }}" class="flex gap-2" onsubmit="const [p,c]=this.pc.value.split('|');this.plan.value=p;this.cycle.value=c;return confirm('Charge your wallet and activate this plan?')">
                            @csrf
                            <input type="hidden" name="plan"><input type="hidden" name="cycle">
                            <select name="pc" class="border border-slate-200 rounded-xl px-2 py-2 text-xs">
                                @foreach ($planOptions as $k => $l)<option value="{{ $k }}" @selected($k === ($w->plan ?: 'starter').'|'.($w->plan_cycle ?: 'monthly'))>{{ $l }}</option>@endforeach
                            </select>
                            <x-btn icon="check" class="!py-2 !text-xs">{{ $w->hasActivePlan() ? 'Renew / change' : 'Activate' }}</x-btn>
                        </form>
                        <form method="POST" action="{{ route('reseller.customers.toggle', $w) }}">@csrf<input type="hidden" name="field" value="auto_renew"><x-btn variant="outline" icon="refresh" class="!py-2 !text-xs">Auto renew: {{ $w->auto_renew ? 'On' : 'Off' }}</x-btn></form>
                        <form method="POST" action="{{ route('reseller.customers.toggle', $w) }}">@csrf<input type="hidden" name="field" value="suspended"><x-btn :variant="$w->suspended_at ? 'outline' : 'danger'" icon="pause" class="!py-2 !text-xs">{{ $w->suspended_at ? 'Resume' : 'Suspend' }}</x-btn></form>
                        <form method="POST" action="{{ route('reseller.customers.impersonate', $w) }}">@csrf<x-btn variant="outline" icon="eye" class="!py-2 !text-xs">Open panel</x-btn></form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center text-slate-500">No customers yet. Add your first one →</div>
            @endforelse
        </div>
        {{ $customers->links() }}
    </div>

    <form id="new" method="POST" action="{{ route('reseller.customers.store') }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-3 h-fit">
        @csrf
        <div class="font-semibold">Add customer</div>
        <div><label class="{{ $lb }}">Business name</label><input name="company" value="{{ old('company') }}" required maxlength="255" class="{{ $in }}"></div>
        <div><label class="{{ $lb }}">Owner name</label><input name="name" value="{{ old('name') }}" required maxlength="255" class="{{ $in }}"></div>
        <div><label class="{{ $lb }}">Owner email (login)</label><input type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="{{ $in }}"></div>
        <div><label class="{{ $lb }}">Phone (optional)</label><input name="phone" value="{{ old('phone') }}" maxlength="16" class="{{ $in }}"></div>
        <div>
            <label class="{{ $lb }}">Start with</label>
            <select name="pc" class="{{ $in }}" onchange="const [p,c]=this.value.split('|');this.form.plan.value=p||'';this.form.cycle.value=c||''">
                <option value="">{{ $me->trial_days }}-day free trial (no charge)</option>
                @foreach ($planOptions as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
            <input type="hidden" name="plan"><input type="hidden" name="cycle">
        </div>
        <x-btn icon="user-plus">Create customer</x-btn>
        <p class="text-xs text-slate-500">Login and workspace are created instantly. The customer connects their own WhatsApp number and adds their card in Meta for message charges.</p>
    </form>
</div>
@endsection
