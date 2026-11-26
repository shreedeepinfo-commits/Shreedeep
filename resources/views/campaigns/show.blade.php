@extends('layouts.app', ['title' => $campaign->name])

@php
    $pct = fn ($n) => $stats['total'] ? round($n * 100 / $stats['total'], 1) : 0;
    $live = in_array($campaign->status, ['running', 'scheduled']);
    $done = $stats['total'] - $stats['queued'];
@endphp

@section('content')
<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600 text-white p-6 lg:p-8 shadow-xl">
    <div class="absolute -right-16 -top-16 w-72 h-72 rounded-full bg-white/5"></div>
    <div class="relative flex flex-col lg:flex-row lg:items-start gap-6">
        <div class="flex-1 min-w-0">
            <a href="{{ route('campaigns.index') }}" class="text-xs text-brand-200 hover:text-white">‹ All campaigns</a>
            <h1 class="text-2xl lg:text-3xl font-bold mt-2 truncate">{{ $campaign->name }}</h1>
            <div class="text-sm text-brand-100 mt-1">
                Template <span class="font-mono">{{ $campaign->template?->name }}</span> · {{ ucfirst(strtolower($campaign->template?->category ?? '')) }} · from {{ $campaign->whatsappAccount?->label() }}
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <span class="bg-white rounded-full px-1">@include('campaigns.status', ['status' => $campaign->status])</span>
                @if ($campaign->scheduled_at)<span class="text-xs px-3 py-1 rounded-full bg-white/10">📅 {{ $campaign->scheduled_at->format('d M Y, h:i A') }}</span>@endif
                <span class="text-xs px-3 py-1 rounded-full bg-white/10">≈ ₹{{ \App\Support\Num::indian($campaign->estimatedCost()) }} Meta charge</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($live)
                <form method="POST" action="{{ route('campaigns.pause', $campaign) }}">@csrf<button class="inline-flex items-center gap-1.5 rounded-xl border border-white/30 px-4 py-2 text-sm font-semibold hover:bg-white/10"><x-icon name="pause" class="w-4 h-4" /> Pause</button></form>
            @endif
            @if ($campaign->status === 'paused')
                <form method="POST" action="{{ route('campaigns.resume', $campaign) }}">@csrf<button class="inline-flex items-center gap-1.5 rounded-xl bg-white text-brand-700 px-4 py-2 text-sm font-semibold hover:bg-brand-50"><x-icon name="play" class="w-4 h-4" /> Resume</button></form>
            @endif
            @if ($retryable > 0 && $campaign->status !== 'cancelled')
                <form method="POST" action="{{ route('campaigns.retry', $campaign) }}" onsubmit="return confirm(@js('Send '.$retryable.' failed messages once more?'))">@csrf<button class="inline-flex items-center gap-1.5 rounded-xl bg-white text-brand-700 px-4 py-2 text-sm font-semibold hover:bg-brand-50"><x-icon name="refresh" class="w-4 h-4" /> Retry {{ number_format($retryable) }} failed</button></form>
            @endif
            <form method="POST" action="{{ route('campaigns.duplicate', $campaign) }}">@csrf<button class="inline-flex items-center gap-1.5 rounded-xl border border-white/30 px-4 py-2 text-sm font-semibold hover:bg-white/10"><x-icon name="clipboard" class="w-4 h-4" /> Duplicate</button></form>
            <a href="{{ route('campaigns.export', $campaign) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/30 px-4 py-2 text-sm font-semibold hover:bg-white/10"><x-icon name="upload" class="w-4 h-4" /> Report</a>
            @unless (in_array($campaign->status, ['completed', 'cancelled']))
                <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}" onsubmit="return confirm('Cancel this campaign? Unsent messages will not be sent.')">@csrf<button class="inline-flex items-center gap-1.5 rounded-xl border border-red-300/60 text-red-100 px-4 py-2 text-sm font-semibold hover:bg-red-500/20">Cancel</button></form>
            @endunless
        </div>
    </div>

    @if ($stats['total'])
        <div class="relative mt-6">
            <div class="flex justify-between text-xs text-brand-200 mb-1.5"><span>Progress</span><span id="progress-text">{{ number_format($done) }} of {{ number_format($stats['total']) }} processed</span></div>
            <div class="h-2.5 rounded-full bg-white/15 overflow-hidden flex">
                <div class="bg-emerald-400" style="width: {{ $pct($stats['read']) }}%" title="Read"></div>
                <div class="bg-sky-300" style="width: {{ $pct($stats['delivered'] - $stats['read']) }}%" title="Delivered"></div>
                <div class="bg-white/70" style="width: {{ $pct($stats['sent'] - $stats['delivered']) }}%" title="Sent"></div>
                <div class="bg-red-400" style="width: {{ $pct($stats['failed']) }}%" title="Failed"></div>
            </div>
        </div>
    @endif
</section>

<div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3 mt-6">
    @foreach ([
        ['total', 'Audience', 'users', 'text-slate-900', null],
        ['queued', 'Waiting', 'clock', 'text-amber-600', 'queued'],
        ['sent', 'Sent', 'send', 'text-brand-700', 'sent'],
        ['delivered', 'Delivered', 'check', 'text-sky-600', 'delivered'],
        ['read', 'Read', 'eye', 'text-emerald-600', 'read'],
        ['replied', 'Replied', 'chat', 'text-violet-600', null],
        ['failed', 'Failed', 'alert', 'text-red-600', 'failed'],
    ] as [$key, $label, $icon, $color, $filter])
        <a href="{{ $filter ? route('campaigns.show', [$campaign, 'status' => $filter]) : route('campaigns.show', $campaign) }}" class="bg-white rounded-2xl shadow-sm border {{ request('status') === $filter && $filter ? 'border-brand-500 ring-2 ring-brand-100' : 'border-slate-200' }} p-4 hover:border-brand-300 transition">
            <div class="flex items-center justify-between text-xs text-slate-500">{{ $label }} <x-icon :name="$icon" class="w-4 h-4 {{ $color }}" /></div>
            <div class="text-2xl font-bold mt-1 {{ $color }}">{{ \App\Support\Num::indian($stats[$key]) }}</div>
            @if ($key !== 'total')<div class="text-xs text-slate-400">{{ $pct($stats[$key]) }}%</div>@endif
        </a>
    @endforeach
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <span class="font-semibold">Recipients @if (request('status'))<span class="text-sm font-normal text-slate-400">· {{ request('status') }}</span>@endif</span>
            @if (request('status'))<a href="{{ route('campaigns.show', $campaign) }}" class="text-xs text-brand-700 hover:underline">Show all</a>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Contact</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Sent</th><th class="px-5 py-3">Delivered</th><th class="px-5 py-3">Read</th><th class="px-5 py-3">Error</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($messages as $m)
                    @php $badge = ['read' => 'bg-emerald-50 text-emerald-700', 'delivered' => 'bg-sky-50 text-sky-700', 'sent' => 'bg-brand-50 text-brand-700', 'failed' => 'bg-red-50 text-red-700', 'queued' => 'bg-amber-50 text-amber-700'][$m->status] ?? 'bg-slate-100'; @endphp
                    <tr>
                        <td class="px-5 py-3">
                            @if ($m->contact)<a href="{{ route('contacts.show', $m->contact) }}" class="font-medium hover:text-brand-700">{{ $m->contact->name ?: '—' }}</a>@endif
                            <div class="text-xs font-mono text-slate-500">+{{ $m->phone }}</div>
                        </td>
                        <td class="px-5 py-3"><span class="text-xs font-semibold px-2 py-1 rounded-lg {{ $badge }}">{{ ucfirst($m->status) }}</span>@if ($m->retry_count)<span class="text-[10px] text-slate-400 ml-1">retried</span>@endif</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $m->sent_at?->format('d M, h:i A') }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $m->delivered_at?->format('h:i A') }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $m->read_at?->format('h:i A') }}</td>
                        <td class="px-5 py-3 text-xs text-red-600 max-w-xs">{{ $m->error_message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">No recipients here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $messages->links() }}</div>
    </div>

    <div class="space-y-6">
        <form method="POST" action="{{ route('campaigns.retarget', $campaign) }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            @csrf
            <div class="font-semibold flex items-center gap-2"><x-icon name="refresh" class="w-5 h-5 text-brand-600" /> Follow-up campaign</div>
            <p class="text-sm text-slate-500 mt-1">Save a group from this campaign as a list and send them another template.</p>
            <div class="grid grid-cols-2 gap-2 mt-4">
                @foreach ([['read', 'Read it', $stats['read']], ['unread', 'Did not read', $stats['sent'] - $stats['read']], ['replied', 'Replied', $stats['replied']], ['failed', 'Failed', $stats['failed']]] as [$key, $label, $count])
                    <label class="rounded-xl border border-slate-200 p-3 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <input type="radio" name="segment" value="{{ $key }}" class="sr-only" @checked($loop->first)>
                        <div class="text-sm font-semibold">{{ $label }}</div>
                        <div class="text-xs text-slate-500">{{ \App\Support\Num::indian($count) }} contacts</div>
                    </label>
                @endforeach
            </div>
            <x-btn class="w-full mt-4" icon="send">Create list &amp; new campaign</x-btn>
        </form>

        @if ($failures->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                <div class="font-semibold mb-3 flex items-center gap-2"><x-icon name="alert" class="w-5 h-5 text-red-500" /> Why messages failed</div>
                <div class="space-y-2">
                    @foreach ($failures as $f)
                        <div class="flex items-start gap-3 text-sm">
                            <span class="shrink-0 text-xs font-bold px-2 py-0.5 rounded-lg bg-red-50 text-red-700">{{ number_format($f->c) }}</span>
                            <span class="text-slate-600">{{ $f->error_message ?: 'Unknown error' }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-slate-400 mt-3">Temporary errors can be retried once. Numbers not on WhatsApp or users who stopped marketing messages cannot.</p>
            </div>
        @endif
    </div>
</div>

@if ($live)
    <script>setTimeout(() => location.reload(), 10000);</script>
@endif
@endsection
