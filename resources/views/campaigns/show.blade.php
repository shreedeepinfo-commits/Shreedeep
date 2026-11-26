@extends('layouts.app', ['title' => $campaign->name])

@push('head')
    @if (in_array($campaign->status, ['running', 'scheduled']))<meta http-equiv="refresh" content="10">@endif
@endpush

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold">{{ $campaign->name }}</h1>
        <div class="text-sm text-slate-500 mt-1">
            Template <span class="font-mono">{{ $campaign->template?->name }}</span> · from {{ $campaign->whatsappAccount?->label() }}
            @if ($campaign->scheduled_at) · scheduled {{ $campaign->scheduled_at->format('d M Y, h:i A') }}@endif
        </div>
        <div class="mt-2">@include('campaigns.status', ['status' => $campaign->status])</div>
    </div>
    <div class="flex gap-2">
        @if (in_array($campaign->status, ['running', 'scheduled']))
            <form method="POST" action="{{ route('campaigns.pause', $campaign) }}">@csrf<button class="rounded border px-3 py-1 text-sm">Pause</button></form>
        @endif
        @if ($campaign->status === 'paused')
            <form method="POST" action="{{ route('campaigns.resume', $campaign) }}">@csrf<button class="rounded bg-emerald-600 text-white px-3 py-1 text-sm">Resume</button></form>
        @endif
        @unless (in_array($campaign->status, ['completed', 'cancelled']))
            <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}" onsubmit="return confirm('Cancel this campaign? Unsent messages will not be sent.')">@csrf<button class="rounded border border-red-300 text-red-700 px-3 py-1 text-sm">Cancel</button></form>
        @endunless
        <a href="{{ route('campaigns.export', $campaign) }}" class="rounded border px-3 py-1 text-sm">Download report (CSV)</a>
    </div>
</div>

@php $pct = fn ($n) => $stats['total'] ? round($n * 100 / $stats['total']) : 0; @endphp
<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
    @foreach (['total' => 'Total', 'queued' => 'Waiting', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed'] as $key => $label)
        <a href="?status={{ $key === 'total' ? '' : $key }}" class="bg-white rounded-xl shadow p-4 block">
            <div class="text-xs text-slate-500">{{ $label }}</div>
            <div class="text-2xl font-bold {{ $key === 'failed' ? 'text-red-700' : '' }}">{{ number_format($stats[$key]) }}</div>
            @if ($key !== 'total')<div class="text-xs text-slate-500">{{ $pct($stats[$key]) }}%</div>@endif
        </a>
    @endforeach
</div>

@if ($failures->isNotEmpty())
    <div class="bg-white rounded-xl shadow p-5 mb-6">
        <h2 class="font-semibold mb-2">Top failure reasons</h2>
        @foreach ($failures as $f)<div class="text-sm"><b>{{ $f->c }}</b> × {{ $f->error_message }}</div>@endforeach
    </div>
@endif

<div class="bg-white rounded-xl shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-100 text-left"><tr><th class="p-3">Phone</th><th class="p-3">Name</th><th class="p-3">Status</th><th class="p-3">Sent</th><th class="p-3">Delivered</th><th class="p-3">Read</th><th class="p-3">Error</th></tr></thead>
        <tbody>
        @foreach ($messages as $m)
            <tr class="border-t">
                <td class="p-3 font-mono">+{{ $m->phone }}</td>
                <td class="p-3">{{ $m->contact?->name }}</td>
                <td class="p-3">{{ $m->status }}</td>
                <td class="p-3">{{ $m->sent_at?->format('h:i:s A') }}</td>
                <td class="p-3">{{ $m->delivered_at?->format('h:i:s A') }}</td>
                <td class="p-3">{{ $m->read_at?->format('h:i:s A') }}</td>
                <td class="p-3 text-red-700 text-xs">{{ $m->error_message }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $messages->links() }}</div>
@endsection
