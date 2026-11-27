@extends('layouts.admin', ['title' => 'Resellers'])
@php use App\Support\Num; @endphp

@section('content')
<x-page-header title="Resellers" subtitle="White-label partners. They sell your panel under their own brand and pay you rental from their wallet." />

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <x-stat-card label="Resellers" :value="Num::indian($totals['count'])" icon="team" />
    <x-stat-card label="Their customers" :value="Num::indian($totals['customers'])" icon="users" tone="green" />
    <x-stat-card label="Wallet balances (advance with you)" :value="'₹'.Num::indian($totals['balance'] / 100)" icon="card" tone="amber" />
</div>

<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <div class="xl:col-span-3 space-y-4">
        <form class="flex gap-2"><input name="q" value="{{ $q }}" placeholder="Search reseller…" class="flex-1 border border-slate-200 rounded-xl px-3 py-2.5 text-sm"><x-btn icon="search">Search</x-btn></form>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase"><tr><th class="text-left px-5 py-3">Reseller</th><th class="text-left px-3 py-3">Under</th><th class="text-right px-3 py-3">Customers</th><th class="text-right px-3 py-3">Sub</th><th class="text-right px-5 py-3">Wallet</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($resellers as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3"><a href="{{ route('admin.resellers.show', $r) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $r->name }}</a>
                            @if ($r->suspended_at)<span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-700">Suspended</span>@endif
                            <div class="text-xs text-slate-500">{{ $r->host() }} · {{ $r->user?->email }}</div></td>
                        <td class="px-3 py-3 text-slate-500">{{ $r->parent?->name ?? 'You' }}</td>
                        <td class="px-3 py-3 text-right">{{ $r->workspaces_count }}</td>
                        <td class="px-3 py-3 text-right">{{ $r->children_count }}</td>
                        <td class="px-5 py-3 text-right font-semibold {{ $r->isLowBalance() ? 'text-red-600' : '' }}">{{ $r->balanceLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No resellers yet. Create your first partner →</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $resellers->links() }}
    </div>
    <div class="xl:col-span-2">
        @include('partials.reseller-form', ['action' => route('admin.resellers.store'), 'parent' => null])
    </div>
</div>
@endsection
