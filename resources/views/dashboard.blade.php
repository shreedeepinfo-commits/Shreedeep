@extends('layouts.app', ['title' => 'Dashboard'])

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $connected = $account && $account->status === 'connected';
    $quality = $account?->quality_rating;
    $qualityColor = ['GREEN' => 'text-emerald-300', 'YELLOW' => 'text-amber-300', 'RED' => 'text-red-300'][$quality] ?? 'text-white';
    $done = collect($checklist)->where(2, true)->count();
@endphp

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600 text-white p-6 lg:p-8 shadow-xl">
    <div class="absolute -right-16 -top-16 w-72 h-72 rounded-full bg-white/5"></div>
    <div class="absolute right-40 -bottom-24 w-56 h-56 rounded-full bg-white/5"></div>
    <div class="relative flex flex-col lg:flex-row lg:items-start gap-6">
        <div class="flex-1">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-white/10 border border-white/15"><x-icon name="bolt" class="w-3.5 h-3.5" /> {{ $greeting }}, {{ Str::before(auth()->user()->name, ' ') }}!</span>
            <h1 class="text-2xl lg:text-3xl font-bold mt-4">Ready to reach your customers?</h1>
            <p class="text-brand-100 mt-2 max-w-xl">You have <b class="text-white">{{ number_format($contacts) }} contacts</b> ready to go. Launch your next campaign in 60 seconds.</p>
            <div class="flex flex-wrap gap-3 mt-5">
                <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-white text-brand-700 px-5 py-2.5 font-semibold shadow hover:bg-brand-50"><x-icon name="send" class="w-4 h-4" /> Send Campaign</a>
                <a href="{{ route('contacts.create') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-5 py-2.5 font-semibold hover:bg-white/10"><x-icon name="user-plus" class="w-4 h-4" /> Add Contact</a>
            </div>
        </div>
        <div class="flex gap-10 lg:pr-4">
            <div><div class="text-4xl font-bold">{{ $campaignsThisMonth }}</div><div class="text-xs text-brand-200 mt-1">Campaigns this month</div></div>
            <div><div class="text-4xl font-bold">{{ number_format($replies) }}</div><div class="text-xs text-brand-200 mt-1">Replies (30 days)</div></div>
        </div>
    </div>

    <div class="relative grid grid-cols-2 lg:grid-cols-5 gap-3 mt-7">
        @foreach ([
            ['WhatsApp API', $account ? 'Business Profile' : 'Not connected', 'whatsapp', 'text-white'],
            ['WhatsApp Status', $connected ? 'LIVE' : ($account ? strtoupper($account->status) : 'OFFLINE'), 'pulse', $connected ? 'text-emerald-300' : 'text-red-300'],
            ['Quality Rating', $quality ?? '—', 'shield', $qualityColor],
            ['Messaging Limit', $account?->messaging_limit ?? '—', 'chat', 'text-white'],
            ['Profile Name', $account?->verified_name ?? '—', 'user', 'text-white'],
        ] as [$label, $value, $icon, $color])
            <a href="{{ route('whatsapp.index') }}" class="flex items-center gap-3 rounded-2xl bg-white/10 border border-white/10 px-4 py-3 hover:bg-white/15">
                <span class="w-9 h-9 rounded-xl bg-white/10 grid place-items-center shrink-0"><x-icon :name="$icon" class="w-5 h-5" /></span>
                <span class="min-w-0">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-brand-200">{{ $label }}</span>
                    <span class="block font-semibold truncate {{ $color }}">@if ($label === 'WhatsApp Status' && $connected)<span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-1 animate-pulse"></span>@endif{{ $value }}</span>
                </span>
            </a>
        @endforeach
    </div>
</section>

{{-- Funnel --}}
<h2 class="flex items-center gap-2 text-lg font-semibold mt-8 mb-4"><x-icon name="chart" class="w-5 h-5 text-brand-600" /> Messaging Funnel <span class="text-sm font-normal text-slate-400">last 30 days</span></h2>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach ([
        ['Total Sent', number_format($funnel['sent']), 'Dispatched outbound messages', 'send', 'from-brand-600 to-brand-800'],
        ['Delivered Rate', $funnel['deliveredPct'].'%', number_format($funnel['delivered']).' messages delivered', 'check', 'from-sky-500 to-brand-600'],
        ['Read / Open Rate', $funnel['readPct'].'%', number_format($funnel['read']).' messages read', 'eye', 'from-indigo-500 to-violet-600'],
        ['Failed Rate', $funnel['failedPct'].'%', number_format($funnel['failed']).' failed messages', 'alert', 'from-rose-500 to-red-600'],
    ] as [$label, $value, $sub, $icon, $gradient])
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $gradient }} text-white p-5 shadow-lg">
            <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="relative flex items-start justify-between">
                <div class="text-sm font-medium text-white/85">{{ $label }}</div>
                <x-icon :name="$icon" class="w-7 h-7 text-white/90" />
            </div>
            <div class="relative text-3xl font-bold mt-4">{{ $value }}</div>
            <div class="relative text-sm text-white/80 mt-1">{{ $sub }}</div>
        </div>
    @endforeach
</div>

{{-- Charts + checklist --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-8">
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="font-semibold mb-3">Outbound &amp; Inbound Messages (last 7 days)</div>
        <div class="h-64"><canvas id="messagesChart"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="font-semibold mb-3">Contacts Growth</div>
        <div class="h-64"><canvas id="contactsChart"></canvas></div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center gap-3 mb-1">
            <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">Getting started checklist</div>
            <div class="text-xs font-semibold text-brand-600">{{ $done }} of {{ count($checklist) }} done</div>
        </div>
        <div class="h-1.5 rounded-full bg-slate-100 mb-4"><div class="h-1.5 rounded-full bg-brand-600" style="width: {{ round($done * 100 / count($checklist)) }}%"></div></div>
        <div class="space-y-2">
            @foreach ($checklist as $i => [$label, $hint, $isDone, $url])
                <a href="{{ $url }}" class="flex items-center gap-4 rounded-xl border border-slate-200 px-4 py-3 hover:border-brand-300 hover:bg-brand-50/40">
                    @if ($isDone)
                        <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 grid place-items-center"><x-icon name="check" class="w-4 h-4" /></span>
                    @else
                        <span class="w-8 h-8 rounded-full bg-brand-950 text-white grid place-items-center text-sm font-semibold">{{ $i + 1 }}</span>
                    @endif
                    <span class="flex-1">
                        <span class="block font-medium {{ $isDone ? 'text-slate-400 line-through' : '' }}">{{ $label }}</span>
                        <span class="block text-xs text-slate-500">{{ $hint }}</span>
                    </span>
                    <span class="text-slate-300">›</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Recent campaigns</div>
            <a href="{{ route('campaigns.index') }}" class="text-sm text-brand-600 hover:underline">View all</a>
        </div>
        @forelse ($campaigns as $c)
            <a href="{{ route('campaigns.show', $c) }}" class="flex items-center justify-between py-3 border-t first:border-t-0 text-sm hover:text-brand-700">
                <span class="min-w-0">
                    <span class="block font-medium truncate">{{ $c->name }}</span>
                    <span class="block text-xs text-slate-500">{{ number_format($c->total) }} contacts · {{ $c->created_at->format('d M') }}</span>
                </span>
                @include('campaigns.status', ['status' => $c->status])
            </a>
        @empty
            <div class="text-sm text-slate-500 py-6 text-center">No campaigns yet.</div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const chartData = @json($chart);
    Chart.defaults.font.family = 'Poppins, sans-serif';
    Chart.defaults.color = '#64748b';
    const grid = { color: '#eef2f7' };
    new Chart(document.getElementById('messagesChart'), {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                { label: 'Outbound', data: chartData.outbound, borderColor: '#0B4E99', backgroundColor: 'rgba(11,78,153,0.08)', fill: true, tension: 0.4, pointRadius: 3 },
                { label: 'Replies', data: chartData.inbound, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.06)', fill: true, tension: 0.4, pointRadius: 3 },
            ],
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { x: { grid }, y: { grid, beginAtZero: true, ticks: { precision: 0 } } } },
    });
    new Chart(document.getElementById('contactsChart'), {
        type: 'bar',
        data: { labels: chartData.labels, datasets: [{ label: 'New contacts', data: chartData.contacts, backgroundColor: '#3f86d1', borderRadius: 6 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid, beginAtZero: true, ticks: { precision: 0 } } } },
    });
</script>
@endpush
