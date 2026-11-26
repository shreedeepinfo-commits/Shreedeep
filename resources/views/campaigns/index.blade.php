@extends('layouts.app', ['title' => 'Campaigns'])

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Campaigns</h1>
    <a href="{{ route('campaigns.create') }}" class="rounded bg-emerald-600 text-white px-4 py-2 font-semibold">New campaign</a>
</div>
<div class="bg-white rounded-xl shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-100 text-left"><tr>
            <th class="p-3">Name</th><th class="p-3">Template</th><th class="p-3">Status</th>
            <th class="p-3 text-right">Total</th><th class="p-3 text-right">Sent</th><th class="p-3 text-right">Delivered</th><th class="p-3 text-right">Read</th><th class="p-3 text-right">Failed</th><th class="p-3">Created</th>
        </tr></thead>
        <tbody>
        @forelse ($campaigns as $c)
            @php
                $s = $counts[$c->id] ?? collect();
                $read = $s['read'] ?? 0; $delivered = ($s['delivered'] ?? 0) + $read; $sent = ($s['sent'] ?? 0) + $delivered;
            @endphp
            <tr class="border-t">
                <td class="p-3"><a href="{{ route('campaigns.show', $c) }}" class="underline font-medium">{{ $c->name }}</a></td>
                <td class="p-3 font-mono text-xs">{{ $c->template?->name }}</td>
                <td class="p-3">@include('campaigns.status', ['status' => $c->status])</td>
                <td class="p-3 text-right">{{ number_format($c->total) }}</td>
                <td class="p-3 text-right">{{ number_format($sent) }}</td>
                <td class="p-3 text-right">{{ number_format($delivered) }}</td>
                <td class="p-3 text-right">{{ number_format($read) }}</td>
                <td class="p-3 text-right text-red-700">{{ number_format($s['failed'] ?? 0) }}</td>
                <td class="p-3 text-slate-500">{{ $c->created_at->format('d M, h:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="p-8 text-center text-slate-500">No campaigns yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $campaigns->links() }}</div>
@endsection
