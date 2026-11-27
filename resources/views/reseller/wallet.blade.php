@extends('layouts.reseller', ['title' => 'Wallet'])

@section('content')
<x-page-header title="Wallet" subtitle="Rental advance. Activations and auto renewals of your customers are paid from here." />

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="space-y-6">
        <div class="rounded-3xl bg-gradient-to-br from-brand-800 to-brand-600 text-white p-6">
            <div class="text-sm text-brand-100">Balance</div>
            <div class="text-4xl font-bold mt-1">{{ $me->balanceLabel() }}</div>
            <div class="text-xs text-brand-100 mt-2">Alert below ₹{{ \App\Support\Num::indian($me->low_balance_alert / 100) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-sm text-slate-600 space-y-2">
            <div class="font-semibold text-slate-800">How to recharge</div>
            @if ($parent)
                <p>Your wallet is topped up by <b>{{ $parent->name }}</b>. Contact them:</p>
                <p>{{ $parent->support_phone }} {{ $parent->support_email }}</p>
            @else
                <p>Pay {{ config('billing.seller.name') }} by UPI or bank transfer and share the reference. The balance is added the same day.</p>
                <p>{{ config('billing.seller.email') }}</p>
            @endif
            <p class="text-xs text-slate-500">This wallet is only for panel rental. WhatsApp message charges are paid by each customer directly to Meta.</p>
        </div>
    </div>
    <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase"><tr><th class="text-left px-5 py-3">Date</th><th class="text-left px-3 py-3">Details</th><th class="text-right px-3 py-3">Amount</th><th class="text-right px-5 py-3">Balance</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($transactions as $t)
                <tr>
                    <td class="px-5 py-3 whitespace-nowrap text-slate-500">{{ $t->created_at->format('d M Y, H:i') }}</td>
                    <td class="px-3 py-3">{{ $t->description }}<div class="text-xs text-slate-400">{{ \App\Models\WalletTransaction::REASONS[$t->reason] ?? $t->reason }}</div></td>
                    <td class="px-3 py-3 text-right font-semibold {{ $t->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ $t->amountLabel() }}</td>
                    <td class="px-5 py-3 text-right">₹{{ \App\Support\Num::indian($t->balance_after / 100) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
