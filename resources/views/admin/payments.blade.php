@extends('layouts.admin', ['title' => 'Payments & invoices'])
@php use App\Support\Num; @endphp

@section('content')
<x-page-header title="Payments & invoices" subtitle="Every plan payment on the platform, with GST invoices." />

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Paid payments" :value="Num::indian($totals->n ?? 0)" sub="All time" icon="card" />
    <x-stat-card label="Revenue (before GST)" :value="'₹'.Num::indian($totals->amount ?? 0)" sub="All time" icon="chart" tone="green" />
    <x-stat-card label="GST collected" :value="'₹'.Num::indian($totals->tax ?? 0)" sub="To deposit with the government" icon="document" tone="amber" />
    <x-stat-card label="Total received" :value="'₹'.Num::indian($totals->total ?? 0)" sub="Including GST" icon="check" tone="sky" />
</div>

<div class="flex gap-2 mb-4">
    @foreach (['' => 'All', 'paid' => 'Paid', 'created' => 'Not completed', 'failed' => 'Failed'] as $k => $l)
        <a href="{{ route('admin.payments', array_filter(['status' => $k])) }}" class="px-4 py-2 rounded-xl text-sm font-semibold {{ ($status ?? '') === $k ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $l }}</a>
    @endforeach
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-5 py-3">Date</th><th class="text-left px-3 py-3">Customer</th><th class="text-left px-3 py-3">Plan</th><th class="text-left px-3 py-3">Gateway</th><th class="text-right px-3 py-3">Amount</th><th class="text-right px-3 py-3">GST</th><th class="text-right px-3 py-3">Total</th><th class="text-left px-3 py-3">Status</th><th class="text-right px-5 py-3">Invoice</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $p)
                    <tr>
                        <td class="px-5 py-3 whitespace-nowrap">{{ ($p->paid_at ?? $p->created_at)->format('d M Y') }}</td>
                        <td class="px-3 py-3">@if ($p->workspace)<a href="{{ route('admin.customers.show', $p->workspace) }}" class="font-medium hover:text-brand-700">{{ $p->workspace->name }}</a>@endif</td>
                        <td class="px-3 py-3">{{ $p->planName() }}</td>
                        <td class="px-3 py-3 capitalize">{{ $p->gateway }}</td>
                        <td class="px-3 py-3 text-right">₹{{ number_format($p->amount, 2) }}</td>
                        <td class="px-3 py-3 text-right">₹{{ number_format($p->tax, 2) }}</td>
                        <td class="px-3 py-3 text-right font-semibold">₹{{ number_format($p->total, 2) }}</td>
                        <td class="px-3 py-3"><span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ ['paid' => 'bg-emerald-50 text-emerald-700', 'failed' => 'bg-red-50 text-red-700'][$p->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ['paid' => 'Paid', 'failed' => 'Failed', 'created' => 'Not completed'][$p->status] ?? $p->status }}</span></td>
                        <td class="px-5 py-3 text-right">@if ($p->invoice)<a href="{{ route('admin.invoices.show', $p->invoice) }}" target="_blank" class="text-brand-700 font-semibold">{{ $p->invoice->number }}</a>@else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-10 text-center text-slate-500">No payments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
