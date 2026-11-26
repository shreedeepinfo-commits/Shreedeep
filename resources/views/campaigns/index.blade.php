@extends('layouts.app', ['title' => 'Campaigns'])

@section('content')
<x-page-header title="Campaigns" subtitle="Manage, track and create your WhatsApp campaigns">
    <x-btn :href="route('campaigns.create')" icon="plus">New campaign</x-btn>
</x-page-header>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Total campaigns" :value="number_format($kpis['total'])" :sub="'+ '.$kpis['thisMonth'].' this month'" icon="megaphone" />
    <x-stat-card label="Messages sent" :value="number_format($kpis['sentThisMonth'])" sub="This month" icon="send" tone="green" />
    <x-stat-card label="Scheduled" :value="number_format($kpis['scheduled'])" sub="Upcoming" icon="calendar" tone="sky" :href="route('campaigns.index', ['tab' => 'scheduled'])" />
    <x-stat-card label="Need attention" :value="number_format($kpis['attention'])" sub="Paused or with failures" icon="alert" tone="amber" />
</div>

<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 border-b border-slate-200 mb-5">
    <nav class="flex gap-1 overflow-x-auto -mb-px">
        @foreach (\App\Http\Controllers\CampaignController::TABS as $key => $label)
            <a href="{{ route('campaigns.index', array_filter(['tab' => $key === 'all' ? null : $key, 'q' => request('q')])) }}"
               class="whitespace-nowrap px-4 py-3 text-sm border-b-2 {{ $tab === $key ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                {{ $label }} <span class="opacity-60">({{ $tabCounts[$key] ?? 0 }})</span>
            </a>
        @endforeach
    </nav>
    <form class="relative pb-2">
        @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
        <x-icon name="search" class="w-4 h-4 absolute left-3 top-3 text-slate-400" />
        <input name="q" value="{{ request('q') }}" placeholder="Search campaigns…" class="w-full lg:w-72 border border-slate-200 rounded-xl pl-9 pr-3 py-2.5 text-sm">
    </form>
</div>

<div class="space-y-3">
    @forelse ($campaigns as $c)
        @php
            $s = $counts[$c->id] ?? collect();
            $read = $s['read'] ?? 0; $delivered = ($s['delivered'] ?? 0) + $read; $sent = ($s['sent'] ?? 0) + $delivered; $failed = $s['failed'] ?? 0;
            $done = $c->total ? round(($sent + $failed) * 100 / $c->total) : 0;
            $bar = ['running' => 'border-l-sky-500', 'completed' => 'border-l-emerald-500', 'scheduled' => 'border-l-violet-500', 'paused' => 'border-l-amber-500', 'cancelled' => 'border-l-slate-300'][$c->status] ?? 'border-l-slate-300';
            $tplStatus = $c->template?->status;
        @endphp
        <a href="{{ route('campaigns.show', $c) }}" class="block bg-white rounded-2xl border border-slate-200 border-l-4 {{ $bar }} shadow-sm hover:shadow-md transition p-5">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-slate-900 truncate">{{ $c->name }}</span>
                        @if ($c->template)<span class="inline-flex items-center gap-1 text-[11px] font-semibold uppercase px-2 py-0.5 rounded-md bg-brand-50 text-brand-700"><x-icon name="megaphone" class="w-3 h-3" /> {{ $c->template->category }}</span>@endif
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-sm text-slate-500">
                        <span>{{ $c->scheduled_at && $c->status === 'scheduled' ? 'Scheduled '.$c->scheduled_at->format('M d, h:i A') : 'Created '.$c->created_at->format('M d, h:i A') }}</span>
                        <span>·</span><span>{{ number_format($c->total) }} contacts</span>
                        <span>·</span><span>Template: <code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 text-xs">{{ $c->template?->name }}</code></span>
                        @if ($tplStatus)
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full {{ $tplStatus === 'APPROVED' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}"><x-icon name="check" class="w-3 h-3" /> Meta {{ ucfirst(strtolower($tplStatus)) }}</span>
                        @endif
                    </div>
                    @if (in_array($c->status, ['running', 'paused']))
                        <div class="mt-3 h-1.5 rounded-full bg-slate-100 max-w-md"><div class="h-1.5 rounded-full bg-brand-600" style="width: {{ $done }}%"></div></div>
                    @endif
                </div>
                <div class="flex items-center gap-6 text-center">
                    <div><div class="font-semibold">@include('campaigns.status', ['status' => $c->status])</div><div class="text-[10px] uppercase tracking-wider text-slate-400 mt-1">Status</div></div>
                    <div><div class="font-bold text-slate-900">{{ number_format($sent) }}</div><div class="text-[10px] uppercase tracking-wider text-slate-400">Sent</div></div>
                    <div><div class="font-bold text-emerald-600">{{ number_format($read) }}</div><div class="text-[10px] uppercase tracking-wider text-slate-400">Read</div></div>
                    <div><div class="font-bold text-red-600">{{ number_format($failed) }}</div><div class="text-[10px] uppercase tracking-wider text-slate-400">Failed</div></div>
                    <span class="text-slate-300 text-xl">›</span>
                </div>
            </div>
        </a>
    @empty
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon name="megaphone" class="w-7 h-7" /></div>
            <div class="font-semibold mt-4">No campaigns here</div>
            <div class="text-sm text-slate-500 mt-1">Create a campaign to reach your customers in bulk.</div>
            <x-btn :href="route('campaigns.create')" icon="plus" class="mt-4">New campaign</x-btn>
        </div>
    @endforelse
</div>
<div class="mt-4">{{ $campaigns->links() }}</div>
@endsection
