@extends('layouts.reseller', ['title' => 'Sub-resellers'])

@section('content')
<x-page-header title="Sub-resellers" subtitle="Partners under you. They pay you their cost from their wallet; you top it up from yours." />

<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <div class="xl:col-span-3 space-y-4">
        @forelse ($children as $c)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="w-10 h-10 rounded-xl grid place-items-center text-white font-bold" style="background: {{ $c->color }}">{{ mb_substr($c->name, 0, 1) }}</span>
                    <div class="flex-1 min-w-[10rem]">
                        <div class="font-semibold">{{ $c->name }} @if ($c->suspended_at)<span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-700">Suspended</span>@endif</div>
                        <div class="text-xs text-slate-500">{{ $c->host() }} · {{ $c->user?->email }} · {{ $c->workspaces_count }} customers</div>
                    </div>
                    <div class="text-right"><div class="text-xs text-slate-500">Wallet</div><div class="font-bold">{{ $c->balanceLabel() }}</div></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <form method="POST" action="{{ route('reseller.resellers.transfer', $c) }}" class="flex gap-2 items-end">
                        @csrf
                        <div class="flex-1"><label class="block text-xs font-semibold text-slate-500 mb-1">Add balance from your wallet (₹)</label><input type="number" name="amount" min="1" step="1" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm"></div>
                        <x-btn icon="send" class="!py-2">Send</x-btn>
                    </form>
                    <form method="POST" action="{{ route('reseller.resellers.suspend', $c) }}" class="flex items-end justify-end">@csrf<x-btn :variant="$c->suspended_at ? 'outline' : 'danger'" icon="pause" class="!py-2">{{ $c->suspended_at ? 'Resume' : 'Suspend' }}</x-btn></form>
                </div>
                <details class="mt-3">
                    <summary class="text-sm text-brand-700 cursor-pointer">Edit costs</summary>
                    <form method="POST" action="{{ route('reseller.resellers.update', $c) }}" class="mt-3 space-y-3">
                        @csrf @method('PUT')
                        @include('partials.reseller-costs', ['values' => collect(config('billing.plans'))->map(fn ($p, $k) => $c->cost($k))->all(), 'min' => collect(config('billing.plans'))->map(fn ($p, $k) => $me->cost($k))->all()])
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_create_resellers" value="1" class="rounded" @checked($c->can_create_resellers)> Can create their own sub-resellers</label>
                        <x-btn icon="check" class="!py-2">Save</x-btn>
                    </form>
                </details>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">No sub-resellers yet.</div>
        @endforelse
    </div>
    <div class="xl:col-span-2">
        @include('partials.reseller-form', ['action' => route('reseller.resellers.store'), 'parent' => $me])
    </div>
</div>
@endsection
