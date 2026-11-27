@extends('layouts.admin', ['title' => $reseller->name])
@php
    use App\Http\Controllers\Admin\AdminController;
    use App\Models\WalletTransaction;
    use App\Support\Num;
    $in = 'w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm';
@endphp

@section('content')
<section class="relative overflow-hidden rounded-3xl text-white p-6 mb-6" style="background: linear-gradient(90deg, #061a31, {{ $reseller->color }})">
    <div class="relative flex flex-wrap items-center gap-6">
        <div class="flex-1 min-w-[14rem]">
            <div class="text-sm opacity-80">{{ $reseller->parent ? 'Sub-reseller of '.$reseller->parent->name : 'Your reseller' }}</div>
            <div class="text-2xl font-bold">{{ $reseller->name }} @if ($reseller->suspended_at)<span class="text-xs px-2 py-0.5 rounded-full bg-red-500">Suspended</span>@endif</div>
            <div class="text-sm opacity-80 mt-1">{{ $reseller->host() }} · {{ $reseller->user?->email }} · {{ $reseller->support_phone }}</div>
        </div>
        <div class="rounded-2xl bg-white/10 border border-white/10 px-5 py-4"><div class="text-xs opacity-80">Wallet</div><div class="text-2xl font-bold">{{ $reseller->balanceLabel() }}</div></div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.resellers.impersonate', $reseller) }}">@csrf<x-btn icon="eye" class="!bg-white !text-brand-700">Open their panel</x-btn></form>
            <form method="POST" action="{{ route('admin.resellers.suspend', $reseller) }}">@csrf<x-btn variant="outline" icon="pause">{{ $reseller->suspended_at ? 'Resume' : 'Suspend' }}</x-btn></form>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="space-y-6">
        <form method="POST" action="{{ route('admin.resellers.wallet', $reseller) }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-3">
            @csrf
            <div class="font-semibold">Recharge / adjust wallet</div>
            <div class="grid grid-cols-2 gap-2">
                <select name="type" class="{{ $in }}"><option value="credit">Add (+)</option><option value="debit">Deduct (−)</option></select>
                <select name="reason" class="{{ $in }}"><option value="recharge">Recharge</option><option value="adjustment">Adjustment</option><option value="refund">Refund</option></select>
            </div>
            <input type="number" name="amount" min="1" step="1" required placeholder="Amount ₹" class="{{ $in }}">
            <input name="note" maxlength="150" placeholder="Note, e.g. UPI ref 4512…" class="{{ $in }}">
            <x-btn icon="check">Update wallet</x-btn>
        </form>
        <form method="POST" action="{{ route('admin.resellers.update', $reseller) }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-3">
            @csrf @method('PUT')
            <div class="font-semibold">Rental cost per customer</div>
            @include('partials.reseller-costs', ['values' => collect(config('billing.plans'))->map(fn ($p, $k) => $reseller->cost($k))->all(), 'min' => $reseller->parent ? collect(config('billing.plans'))->map(fn ($p, $k) => $reseller->parent->cost($k))->all() : []])
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_create_resellers" value="1" class="rounded" @checked($reseller->can_create_resellers)> Can create sub-resellers</label>
            <x-btn icon="check">Save</x-btn>
        </form>
    </div>

    <div class="xl:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 font-semibold">Customers ({{ $customers->count() }})</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                @forelse ($customers as $w)
                    @php [$label, $cls] = AdminController::STATUSES[$w->status()]; @endphp
                    <tr><td class="px-6 py-3"><a href="{{ route('admin.customers.show', $w) }}" class="font-medium hover:text-brand-700">{{ $w->name }}</a><div class="text-xs text-slate-500">{{ $w->owner?->email }}</div></td>
                        <td class="px-3 py-3">{{ $w->planName() }}</td>
                        <td class="px-3 py-3"><span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $cls }}">{{ $label }}</span></td>
                        <td class="px-6 py-3 text-right text-slate-500">{{ $w->accessEndsAt()?->format('d M Y') ?? '—' }}</td></tr>
                @empty
                    <tr><td class="px-6 py-10 text-center text-slate-500">No customers yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($children->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 font-semibold">Sub-resellers</div>
                @foreach ($children as $c)
                    <a href="{{ route('admin.resellers.show', $c) }}" class="flex items-center gap-3 px-6 py-3 border-b border-slate-50 hover:bg-slate-50 text-sm"><span class="flex-1 font-medium">{{ $c->name }}</span><span class="text-slate-500">{{ $c->workspaces_count }} customers</span><b>{{ $c->balanceLabel() }}</b></a>
                @endforeach
            </div>
        @endif
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 font-semibold">Wallet history</div>
            @forelse ($transactions as $t)
                <div class="flex items-center gap-3 px-6 py-3 border-b border-slate-50 text-sm">
                    <div class="flex-1 min-w-0"><div class="truncate">{{ $t->description }}</div><div class="text-xs text-slate-500">{{ $t->created_at->format('d M Y, H:i') }} · {{ WalletTransaction::REASONS[$t->reason] ?? $t->reason }}</div></div>
                    <b class="{{ $t->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ $t->amountLabel() }}</b>
                    <span class="text-xs text-slate-500 w-24 text-right">₹{{ Num::indian($t->balance_after / 100) }}</span>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-slate-500 text-sm">No wallet activity yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
