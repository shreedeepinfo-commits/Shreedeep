@extends('layouts.app', ['title' => 'Ticket '.$ticket->number()])
@php use App\Models\SupportTicket; [$sl, $sc] = SupportTicket::STATUSES[$ticket->status]; @endphp

@section('content')
<x-page-header :title="$ticket->subject" :subtitle="$ticket->number().' · '.(SupportTicket::CATEGORIES[$ticket->category] ?? '').' · opened '.$ticket->created_at->format('d M Y')">
    <x-btn :href="route('support.index')" variant="outline">← All tickets</x-btn>
    @if ($ticket->status !== 'closed')
        <form method="POST" action="{{ route('support.close', $ticket) }}">@csrf<x-btn variant="outline" icon="check">Mark solved</x-btn></form>
    @endif
</x-page-header>

<div class="max-w-3xl">
    <div class="mb-4"><span class="text-xs font-semibold px-3 py-1 rounded-full {{ $sc }}">{{ $sl }}</span></div>
    @include('support.thread', ['staffView' => false])
    <form method="POST" action="{{ route('support.reply', $ticket) }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mt-6 space-y-3">
        @csrf
        <textarea name="body" rows="4" required maxlength="5000" placeholder="{{ $ticket->status === 'closed' ? 'Write to open this ticket again…' : 'Write a reply…' }}" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm"></textarea>
        <x-btn icon="send">Send reply</x-btn>
    </form>
</div>
@endsection
