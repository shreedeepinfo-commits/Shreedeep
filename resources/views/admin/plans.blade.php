@extends('layouts.admin', ['title' => 'Plans'])
@php use App\Support\Num; use App\Services\Billing\Billing; @endphp

@section('content')
<x-page-header title="Plans" subtitle="What customers can buy. Prices are before GST." />

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    @foreach ($plans as $key => $p)
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex justify-between items-center">
                <div class="font-bold text-lg">{{ $p['name'] }}</div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-brand-50 text-brand-700">{{ $customers[$key] ?? 0 }} customers</span>
            </div>
            <div class="text-sm text-slate-500">{{ $p['tagline'] }}</div>
            <table class="w-full text-sm mt-4">
                @foreach ($cycles as $ck => $c)
                    <tr class="border-b border-slate-100"><td class="py-2">{{ $c['label'] }} @if ($c['discount'])<span class="text-xs text-emerald-600">−{{ $c['discount'] }}%</span>@endif</td><td class="py-2 text-right font-semibold">₹{{ Num::indian(Billing::price($key, $ck)) }}</td></tr>
                @endforeach
            </table>
            <ul class="mt-4 space-y-1.5 text-sm">
                @foreach ($p['limits'] as $lk => $lv)
                    <li class="flex justify-between"><span class="text-slate-500">{{ config("billing.limit_labels.$lk") }}</span><b>{{ $lv === null ? 'Unlimited' : Num::indian($lv) }}</b></li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
<div class="rounded-2xl bg-brand-50 border border-brand-100 p-5 text-sm text-brand-900">
    Free trial: <b>{{ config('billing.trial_days') }} days</b> with {{ config('billing.plans.'.config('billing.trial_plan').'.name') }} limits.
    Monthly prices can be changed in <code>.env</code> (<code>PLAN_STARTER_PRICE</code>, <code>PLAN_GROWTH_PRICE</code>, <code>PLAN_PRO_PRICE</code>); limits and features live in <code>config/billing.php</code>.
</div>
@endsection
