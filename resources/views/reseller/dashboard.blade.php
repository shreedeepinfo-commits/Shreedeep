@extends('layouts.reseller', ['title' => 'Overview'])
@php use App\Support\Num; @endphp

@section('content')
<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-950 via-brand-800 to-brand-600 text-white p-6 mb-6">
    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/5"></div>
    <div class="relative flex flex-wrap items-center gap-6">
        <div class="flex-1 min-w-[14rem]">
            <div class="text-brand-100 text-sm">Welcome, {{ $me->name }}</div>
            <div class="text-2xl font-bold mt-1">Your WhatsApp panel business</div>
            <div class="text-sm text-brand-100 mt-2">Customers log in at <a href="{{ $me->url() }}/login" target="_blank" class="underline text-white">{{ $me->host() }}</a></div>
        </div>
        <div class="rounded-2xl bg-white/10 border border-white/10 px-5 py-4">
            <div class="text-xs text-brand-100">Wallet balance</div>
            <div class="text-2xl font-bold">{{ $me->balanceLabel() }}</div>
        </div>
        <div class="flex gap-2">
            <x-btn :href="route('reseller.customers').'#new'" icon="user-plus" class="!bg-white !text-brand-700">Add user</x-btn>
        </div>
    </div>
</section>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Customers" :value="Num::indian($stats['customers'])" icon="users" :href="route('reseller.customers')" />
    <x-stat-card label="Paid plans" :value="Num::indian($stats['active'])" icon="check" tone="green" />
    <x-stat-card label="On trial" :value="Num::indian($stats['trial'])" icon="clock" tone="amber" />
    <x-stat-card label="Expired" :value="Num::indian($stats['expired'])" icon="alert" tone="red" />
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="font-semibold mb-3">Monthly rental</div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Your customers pay you</span><b>₹{{ Num::indian($monthlyValue) }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">You pay (wallet)</span><b>₹{{ Num::indian($monthlyCost) }}</b></div>
            <div class="flex justify-between border-t border-slate-100 pt-2"><span>Your margin</span><b class="text-emerald-600">₹{{ Num::indian($monthlyValue - $monthlyCost) }}</b></div>
        </div>
        <p class="text-xs text-slate-500 mt-3">Rental only. WhatsApp message charges are paid by each customer directly to Meta.</p>
        @if ($me->can_create_resellers)<p class="text-sm mt-3"><a href="{{ route('reseller.resellers') }}" class="text-brand-700 font-semibold">{{ $children }} sub-resellers →</a></p>@endif
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-semibold">Ending in the next 7 days</div>
        @forelse ($expiring as $w)
            <div class="px-6 py-3 border-b border-slate-50 flex items-center gap-3 text-sm">
                <div class="flex-1"><div class="font-medium">{{ $w->name }}</div><div class="text-xs text-slate-500">{{ $w->planName() }} · {{ $w->accessEndsAt()->format('d M') }}</div></div>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $w->auto_renew && $w->hasActivePlan() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $w->auto_renew && $w->hasActivePlan() ? 'Auto renews' : 'Needs action' }}</span>
            </div>
        @empty
            <div class="px-6 py-10 text-center text-sm text-slate-500">Nothing ending this week.</div>
        @endforelse
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-semibold flex justify-between">Wallet activity <a href="{{ route('reseller.wallet') }}" class="text-sm text-brand-700">All →</a></div>
        @forelse ($transactions as $t)
            <div class="px-6 py-3 border-b border-slate-50 flex items-center gap-3 text-sm">
                <div class="flex-1 min-w-0"><div class="truncate">{{ $t->description }}</div><div class="text-xs text-slate-500">{{ $t->created_at->diffForHumans() }}</div></div>
                <b class="{{ $t->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ $t->amountLabel() }}</b>
            </div>
        @empty
            <div class="px-6 py-10 text-center text-sm text-slate-500">No wallet activity yet.</div>
        @endforelse
    </div>
</div>
@endsection
