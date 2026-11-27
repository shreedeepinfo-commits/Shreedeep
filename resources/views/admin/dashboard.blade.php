@extends('layouts.admin', ['title' => 'Overview'])
@php use App\Support\Num; use App\Http\Controllers\Admin\AdminController; @endphp

@section('content')
<x-page-header title="Platform overview" subtitle="All your customers, plans and revenue in one place.">
    <x-btn :href="route('admin.customers')" icon="users">All customers</x-btn>
</x-page-header>

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-950 via-brand-800 to-brand-600 text-white p-6 mb-6">
    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/5"></div>
    <div class="relative grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div><div class="text-brand-100 text-sm">Monthly recurring revenue</div><div class="text-3xl font-bold mt-1">₹{{ Num::indian($mrr) }}</div><div class="text-xs text-brand-100 mt-1">before GST</div></div>
        <div><div class="text-brand-100 text-sm">Revenue this month</div><div class="text-3xl font-bold mt-1">₹{{ Num::indian($revenueMonth) }}</div><div class="text-xs text-brand-100 mt-1">₹{{ Num::indian($revenueTotal) }} all time</div></div>
        <div><div class="text-brand-100 text-sm">Customers</div><div class="text-3xl font-bold mt-1">{{ Num::indian($total) }}</div><div class="text-xs text-brand-100 mt-1">{{ $signups30 }} new in 30 days</div></div>
        <div><div class="text-brand-100 text-sm">Paying customers</div><div class="text-3xl font-bold mt-1">{{ Num::indian($counts['active']) }}</div><div class="text-xs text-brand-100 mt-1">{{ $total ? round($counts['active'] * 100 / $total) : 0 }}% of all</div></div>
    </div>
</section>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="On free trial" :value="$counts['trial']" sub="Can become paying" icon="clock" tone="sky" />
    <x-stat-card label="Expired" :value="$counts['expired']" sub="Need a follow-up call" icon="bolt" tone="amber" />
    <x-stat-card label="WhatsApp numbers" :value="Num::indian($numbers)" sub="Connected on the platform" icon="whatsapp" tone="green" />
    <x-stat-card label="Messages this month" :value="Num::indian($messagesMonth)" :sub="$campaignsMonth.' campaigns'" icon="send" />
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="font-semibold mb-4">Revenue, last 6 months</div>
        <div class="h-64"><canvas id="revenueChart"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="font-semibold mb-4">Customers by plan</div>
        @foreach (config('billing.plans') as $key => $p)
            @php $n = $plans[$key] ?? 0; $pct = $counts['active'] ? round($n * 100 / $counts['active']) : 0; @endphp
            <div class="mb-4">
                <div class="flex justify-between text-sm"><span class="font-medium">{{ $p['name'] }}</span><span class="text-slate-500">{{ $n }}</span></div>
                <div class="h-2 rounded-full bg-slate-100 mt-1.5 overflow-hidden"><div class="h-full bg-brand-600 rounded-full" style="width: {{ $pct }}%"></div></div>
            </div>
        @endforeach
        <div class="grid grid-cols-2 gap-2 mt-6">
            @foreach (AdminController::STATUSES as $k => [$label, $cls])
                <a href="{{ route('admin.customers', ['status' => $k]) }}" class="rounded-xl px-3 py-2 text-sm {{ $cls }} flex justify-between"><span>{{ $label }}</span><b>{{ $counts[$k] }}</b></a>
            @endforeach
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    @foreach ([['New customers', $recentSignups, 'signup'], ['Plans ending in 7 days', $endingSoon, 'ending']] as [$heading, $list, $kind])
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 font-semibold">{{ $heading }}</div>
            @forelse ($list as $w)
                <a href="{{ route('admin.customers.show', $w) }}" class="flex items-center gap-3 px-5 py-3 border-b border-slate-50 hover:bg-slate-50">
                    <span class="w-9 h-9 rounded-full bg-brand-50 text-brand-700 grid place-items-center font-semibold uppercase text-sm">{{ mb_substr($w->name, 0, 1) }}</span>
                    <span class="flex-1 min-w-0"><span class="block text-sm font-medium truncate">{{ $w->name }}</span><span class="block text-xs text-slate-500 truncate">{{ $w->owner?->email }}</span></span>
                    <span class="text-xs text-slate-500">{{ $kind === 'signup' ? $w->created_at->diffForHumans() : $w->daysLeft().' days · '.$w->planName() }}</span>
                </a>
            @empty
                <div class="p-6 text-sm text-slate-500 text-center">Nothing here.</div>
            @endforelse
        </div>
    @endforeach
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 font-semibold flex justify-between">Latest payments <a href="{{ route('admin.payments') }}" class="text-sm text-brand-700 font-semibold">All →</a></div>
        @forelse ($recentPayments as $p)
            <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-50">
                <span class="flex-1 min-w-0"><span class="block text-sm font-medium truncate">{{ $p->workspace?->name }}</span><span class="block text-xs text-slate-500">{{ $p->planName() }} · {{ $p->paid_at?->format('d M') }}</span></span>
                <span class="text-sm font-semibold text-emerald-700">₹{{ Num::indian($p->total) }}</span>
            </div>
        @empty
            <div class="p-6 text-sm text-slate-500 text-center">No payments yet.</div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    if (window.Chart) {
        const data = @json($revenueChart);
        Chart.defaults.font.family = 'Poppins, sans-serif';
        new Chart(document.getElementById('revenueChart'), {
            type: 'bar',
            data: { labels: data.map(d => d[0]), datasets: [{ data: data.map(d => d[1]), backgroundColor: '#0B4E99', borderRadius: 8 }] },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } }, x: { grid: { display: false } } } },
        });
    }
</script>
@endpush
