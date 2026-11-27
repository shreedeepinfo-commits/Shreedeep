@extends('layouts.admin', ['title' => 'Support tickets'])
@php use App\Models\SupportTicket; @endphp

@section('content')
<x-page-header title="Support tickets" subtitle="Questions and problems from your customers. Urgent and oldest first." />

<div class="flex flex-wrap gap-2 mb-4">
    <a href="{{ route('admin.tickets') }}" class="px-4 py-2 rounded-xl text-sm font-semibold {{ ! $status ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">Not closed · {{ ($counts['open'] ?? 0) + ($counts['answered'] ?? 0) }}</a>
    @foreach (SupportTicket::STATUSES as $k => [$label])
        <a href="{{ route('admin.tickets', ['status' => $k]) }}" class="px-4 py-2 rounded-xl text-sm font-semibold {{ $status === $k ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $label }} · {{ $counts[$k] ?? 0 }}</a>
    @endforeach
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    @forelse ($tickets as $t)
        @php [$sl, $sc] = SupportTicket::STATUSES[$t->status]; @endphp
        <a href="{{ route('admin.tickets.show', $t) }}" class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-slate-50 hover:bg-slate-50">
            <span class="text-xs text-slate-400 w-14">{{ $t->number() }}</span>
            <span class="flex-1 min-w-0">
                <span class="block font-medium truncate">@if ($t->priority === 'high')<span class="text-red-600">● </span>@endif{{ $t->subject }}</span>
                <span class="block text-xs text-slate-500">{{ $t->workspace?->name }} · {{ $t->user?->name }} · {{ SupportTicket::CATEGORIES[$t->category] ?? $t->category }}</span>
            </span>
            <span class="text-xs text-slate-500">{{ $t->last_reply_at?->diffForHumans() }}</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $sc }}">{{ $sl }}</span>
        </a>
    @empty
        <div class="p-10 text-center text-sm text-slate-500">No tickets here. 🎉</div>
    @endforelse
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
@endsection
