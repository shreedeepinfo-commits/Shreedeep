@extends('layouts.reseller', ['title' => 'Brand & prices'])
@php
    use App\Support\Num;
    $in = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none';
    $lb = 'block text-xs font-semibold text-slate-500 mb-1';
@endphp

@section('content')
<x-page-header title="Brand & prices" subtitle="Your customers see your name, logo, colour and prices. Never ours." />

<form method="POST" action="{{ route('reseller.settings.update') }}" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    @csrf @method('PUT')
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
        <div class="font-semibold">White label</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div><label class="{{ $lb }}">Brand name</label><input name="name" value="{{ old('name', $me->name) }}" required maxlength="100" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Product name</label><input name="product_name" value="{{ old('product_name', $me->product_name) }}" maxlength="100" placeholder="WhatsApp Business Suite" class="{{ $in }}"></div>
            <div class="sm:col-span-2"><label class="{{ $lb }}">Logo URL (https, square works best)</label><input name="logo_url" value="{{ old('logo_url', $me->logo_url) }}" maxlength="500" placeholder="https://yoursite.com/logo.png" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Brand colour</label><input type="color" name="color" value="{{ old('color', $me->color) }}" class="h-11 w-full rounded-xl border border-slate-200 p-1"></div>
            <div><label class="{{ $lb }}">Free trial (days)</label><input type="number" name="trial_days" value="{{ old('trial_days', $me->trial_days) }}" min="0" max="{{ config('reseller.max_trial_days') }}" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Support email</label><input type="email" name="support_email" value="{{ old('support_email', $me->support_email) }}" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Support WhatsApp / phone</label><input name="support_phone" value="{{ old('support_phone', $me->support_phone) }}" maxlength="16" class="{{ $in }}"></div>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-sm space-y-2">
            <div><b>Your panel address:</b> <a href="{{ $me->url() }}/login" target="_blank" class="text-brand-700">{{ $me->slug }}.{{ config('reseller.base_domain') }}</a></div>
            <label class="{{ $lb }}">Own domain (optional)</label>
            <input name="custom_domain" value="{{ old('custom_domain', $me->custom_domain) }}" placeholder="wa.yourcompany.com" class="{{ $in }}">
            <p class="text-xs text-slate-500">Add a DNS <b>CNAME</b> record from your domain to <code>{{ config('reseller.base_domain') }}</code>, then save it here. SSL is issued automatically on the server.</p>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div class="font-semibold">Prices your customers see (per month)</div>
            @foreach (config('billing.plans') as $key => $p)
                <div class="grid grid-cols-3 gap-3 items-center">
                    <div class="font-medium">{{ $p['name'] }}</div>
                    <div class="text-xs text-slate-500">Your cost ₹{{ Num::indian($me->cost($key)) }}</div>
                    <input type="number" step="1" min="0" name="prices[{{ $key }}]" value="{{ old("prices.$key", $me->price($key)) }}" required class="{{ $in }}">
                </div>
            @endforeach
            <p class="text-xs text-slate-500">Quarterly and yearly discounts ({{ collect(config('billing.cycles'))->pluck('discount')->filter()->map(fn ($d) => $d.'%')->join(', ') }}) apply on both your price and your cost. Collect payment from your customers your own way.</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-3">
            <div class="font-semibold">Wallet alert</div>
            <div><label class="{{ $lb }}">Warn me when balance is below (₹)</label><input type="number" name="low_balance_alert" value="{{ old('low_balance_alert', $me->low_balance_alert / 100) }}" min="0" class="{{ $in }}"></div>
        </div>
        <x-btn icon="check">Save settings</x-btn>
    </div>
</form>
@endsection
