@extends('layouts.app', ['title' => 'Analytics'])
@php
    use App\Support\Num;
    use App\Http\Controllers\AnalyticsController;
    $card = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $campaignRows = $campaigns['rows'];
    $fmtWait = fn (?int $s) => $s === null ? '—' : ($s < 60 ? $s.'s' : ($s < 3600 ? round($s / 60).' min' : round($s / 3600, 1).' h'));
    $maxHour = max(1, max($hours));
    $bestHour = array_search(max($hours), $hours);
    $sourceTotal = max(1, collect($sources)->sum('count'));
@endphp

@section('content')
<x-page-header title="Analytics" :subtitle="$from->format('d M Y').' – '.$to->format('d M Y').' · India time'">
    <x-btn :href="route('analytics.export', request()->only('days', 'from', 'to'))" variant="outline" icon="download">Download CSV</x-btn>
</x-page-header>

<form class="flex flex-wrap items-center gap-2 mb-6">
    @foreach (AnalyticsController::RANGES as $k => $label)
        <a href="{{ route('analytics.index', ['days' => $k]) }}" class="px-4 py-2 rounded-xl text-sm font-semibold {{ $rangeKey === (string) $k ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/30' : 'bg-white border border-slate-200 text-slate-600 hover:text-brand-700' }}">{{ $label }}</a>
    @endforeach
    <span class="text-sm text-slate-400 mx-1">or</span>
    <input type="date" name="from" value="{{ $rangeKey === 'custom' ? $from->toDateString() : '' }}" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
    <input type="date" name="to" value="{{ $rangeKey === 'custom' ? $to->toDateString() : '' }}" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
    <x-btn variant="outline">Apply</x-btn>
</form>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
    <x-stat-card label="Messages sent" :value="Num::indian($kpi['attempted'])" :sub="Num::indian($kpi['failed']).' failed ('.$kpi['failedPct'].'%)'" icon="send" />
    <x-stat-card label="Delivered" :value="$kpi['deliveredPct'].'%'" :sub="Num::indian($kpi['delivered']).' messages'" icon="check" tone="green" />
    <x-stat-card label="Read" :value="$kpi['readPct'].'%'" :sub="Num::indian($kpi['read']).' of delivered'" icon="eye" tone="sky" />
    <x-stat-card label="Reply rate" :value="$kpi['replyRate'].'%'" :sub="Num::indian($kpi['conversations']).' customers replied'" icon="chat" tone="amber" />
</div>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Messages received" :value="Num::indian($kpi['inbound'])" sub="From customers" icon="chat" tone="green" />
    <x-stat-card label="New contacts" :value="Num::indian($kpi['newContacts'])" :sub="Num::indian($kpi['optOuts']).' opted out'" icon="users" tone="sky" />
    <x-stat-card label="Estimated Meta charges" :value="'₹'.Num::indian($kpi['cost'])" sub="Delivered templates (approx.)" icon="card" tone="amber" />
    <x-stat-card label="Best time to send" :value="max($hours) ? sprintf('%d–%d %s', $bestHour % 12 ?: 12, ($bestHour + 1) % 12 ?: 12, $bestHour < 12 ? 'AM' : 'PM') : '—'" sub="When customers reply most" icon="clock" />
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="xl:col-span-2 {{ $card }} p-6">
        <div class="font-semibold mb-4">Messages by day</div>
        <div class="h-72"><canvas id="dailyChart"></canvas></div>
    </div>
    <div class="{{ $card }} p-6">
        <div class="font-semibold mb-4">Where messages come from</div>
        @foreach ($sources as $s)
            @php $p = round($s['count'] * 100 / $sourceTotal); @endphp
            <div class="mb-4">
                <div class="flex justify-between text-sm"><span>{{ $s['label'] }}</span><span class="text-slate-500">{{ Num::indian($s['count']) }} · {{ $p }}%</span></div>
                <div class="h-2 rounded-full bg-slate-100 mt-1.5 overflow-hidden"><div class="h-full rounded-full bg-brand-600" style="width: {{ $p }}%"></div></div>
            </div>
        @endforeach
        <div class="font-semibold mt-6 mb-3">Customer activity by hour</div>
        <div class="flex items-end gap-0.5 h-24">
            @foreach ($hours as $h => $n)
                <div class="flex-1 rounded-t {{ $h === $bestHour && $n ? 'bg-emerald-500' : 'bg-brand-200' }}" style="height: {{ max(3, round($n * 100 / $maxHour)) }}%" title="{{ $h }}:00 – {{ $n }} messages"></div>
            @endforeach
        </div>
        <div class="flex justify-between text-[10px] text-slate-400 mt-1"><span>12 AM</span><span>6 AM</span><span>12 PM</span><span>6 PM</span><span>11 PM</span></div>
    </div>
</div>

<div class="{{ $card }} overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <span class="font-semibold">Campaign performance</span>
        <span class="text-xs text-slate-500">{{ Num::indian($campaigns['replies']) }} customers replied to these campaigns</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-6 py-3">Campaign</th><th class="text-right px-3 py-3">Audience</th><th class="text-right px-3 py-3">Delivered</th><th class="text-right px-3 py-3">Read</th><th class="text-right px-3 py-3">Failed</th><th class="px-3 py-3 w-40">Read rate</th><th class="text-right px-6 py-3">Est. cost</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($campaignRows as $r)
                    <tr>
                        <td class="px-6 py-3"><a href="{{ route('campaigns.show', $r['campaign']) }}" class="font-medium hover:text-brand-700">{{ $r['campaign']->name }}</a><div class="text-xs text-slate-500">{{ $r['campaign']->template?->name }} · {{ $r['campaign']->created_at->format('d M') }}</div></td>
                        <td class="px-3 py-3 text-right">{{ Num::indian($r['total']) }}</td>
                        <td class="px-3 py-3 text-right">{{ Num::indian($r['delivered']) }} <span class="text-xs text-slate-400">{{ $r['deliveredPct'] }}%</span></td>
                        <td class="px-3 py-3 text-right">{{ Num::indian($r['read']) }}</td>
                        <td class="px-3 py-3 text-right {{ $r['failed'] ? 'text-red-600' : '' }}">{{ Num::indian($r['failed']) }}</td>
                        <td class="px-3 py-3"><div class="flex items-center gap-2"><div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-emerald-500 rounded-full" style="width: {{ $r['readPct'] }}%"></div></div><span class="text-xs w-9 text-right">{{ $r['readPct'] }}%</span></div></td>
                        <td class="px-6 py-3 text-right">₹{{ Num::indian($r['cost']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-10 text-center text-slate-500">No campaigns in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <div class="{{ $card }} overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-semibold">Team performance</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase"><tr><th class="text-left px-6 py-3">Member</th><th class="text-right px-3 py-3">Replies</th><th class="text-right px-3 py-3">Chats</th><th class="text-right px-3 py-3">Open now</th><th class="text-right px-6 py-3">Avg. reply time</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($agents as $a)
                    <tr>
                        <td class="px-6 py-3"><div class="flex items-center gap-2"><span class="w-8 h-8 rounded-full bg-brand-50 text-brand-700 grid place-items-center font-semibold uppercase text-xs">{{ mb_substr($a['user']->name, 0, 1) }}</span><span>{{ $a['user']->name }} <span class="text-xs text-slate-400 capitalize">{{ $a['role'] }}</span></span></div></td>
                        <td class="px-3 py-3 text-right font-semibold">{{ Num::indian($a['replies']) }}</td>
                        <td class="px-3 py-3 text-right">{{ Num::indian($a['chats']) }}</td>
                        <td class="px-3 py-3 text-right">{{ $a['open'] }}</td>
                        <td class="px-6 py-3 text-right {{ ($a['avgResponse'] ?? 0) > 3600 ? 'text-amber-600' : '' }}">{{ $fmtWait($a['avgResponse']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="space-y-6">
        <div class="{{ $card }} p-6">
            <div class="font-semibold mb-3">Top failure reasons</div>
            @forelse ($failures as $f)
                <div class="flex items-start gap-3 py-2 border-b border-slate-50 text-sm">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-red-50 text-red-700 shrink-0">{{ $f->error_code ?: '—' }}</span>
                    <span class="flex-1 text-slate-600">{{ \Illuminate\Support\Str::limit($f->error_message ?: 'Unknown error', 110) }}@if ($hint = \App\Support\MetaErrors::hint($f->error_code))<span class="block text-xs text-amber-700 mt-0.5">{{ $hint }}</span>@endif</span>
                    <span class="font-semibold">{{ Num::indian($f->c) }}</span>
                </div>
            @empty
                <div class="text-sm text-slate-500">No failed messages. 🎉</div>
            @endforelse
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="{{ $card }} p-6">
                <div class="font-semibold mb-3">Top contact tags</div>
                @forelse ($topTags as $tag => $n)
                    <div class="flex justify-between text-sm py-1"><span class="px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 text-xs">{{ $tag }}</span><span>{{ Num::indian($n) }}</span></div>
                @empty
                    <div class="text-sm text-slate-500">No tags yet.</div>
                @endforelse
            </div>
            <div class="{{ $card }} p-6">
                <div class="font-semibold mb-3">Automations (all time)</div>
                @forelse ($automations as $a)
                    <div class="flex justify-between text-sm py-1"><span class="truncate">{{ $a->name }}</span><span class="font-semibold">{{ Num::indian($a->sent_count) }}</span></div>
                @empty
                    <div class="text-sm text-slate-500">No automatic messages yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    if (window.Chart) {
        const d = @json($daily);
        Chart.defaults.font.family = 'Poppins, sans-serif';
        Chart.defaults.color = '#64748b';
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: d.map(r => new Date(r.date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })),
                datasets: [
                    { label: 'Sent', data: d.map(r => r.out), borderColor: '#0B4E99', backgroundColor: 'rgba(11,78,153,.08)', fill: true, tension: .35 },
                    { label: 'Delivered', data: d.map(r => r.delivered), borderColor: '#10b981', tension: .35 },
                    { label: 'Read', data: d.map(r => r.read), borderColor: '#0ea5e9', tension: .35 },
                    { label: 'Received', data: d.map(r => r.in), borderColor: '#f59e0b', tension: .35 },
                ],
            },
            options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom' } }, scales: { x: { grid: { display: false } } } },
        });
    }
</script>
@endpush
