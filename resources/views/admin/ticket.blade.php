@extends('layouts.admin', ['title' => 'Ticket '.$ticket->number()])
@php use App\Models\SupportTicket; [$sl, $sc] = SupportTicket::STATUSES[$ticket->status]; @endphp

@section('content')
<x-page-header :title="$ticket->subject" :subtitle="$ticket->number().' · '.(SupportTicket::CATEGORIES[$ticket->category] ?? '').' · '.($ticket->priority === 'high' ? 'Urgent' : 'Normal')">
    <x-btn :href="route('admin.tickets')" variant="outline">← Tickets</x-btn>
    @if ($ticket->workspace)<x-btn :href="route('admin.customers.show', $ticket->workspace)" variant="outline" icon="users">{{ $ticket->workspace->name }}</x-btn>@endif
</x-page-header>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2">
        @include('support.thread', ['staffView' => true])
        <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mt-6 space-y-3">
            @csrf
            <textarea name="body" rows="5" required maxlength="5000" placeholder="Reply to the customer…" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm"></textarea>
            <div class="flex flex-wrap items-center gap-3">
                <x-btn icon="send">Send reply</x-btn>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="close" value="1" class="rounded text-brand-600"> Close the ticket after replying</label>
            </div>
        </form>
    </div>
    <aside class="space-y-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 text-sm space-y-2">
            <div><span class="text-xs font-semibold px-3 py-1 rounded-full {{ $sc }}">{{ $sl }}</span></div>
            <div class="pt-2"><span class="text-slate-500">Customer:</span> {{ $ticket->workspace?->name }}</div>
            <div><span class="text-slate-500">Raised by:</span> {{ $ticket->user?->name }} · {{ $ticket->user?->email }}</div>
            <div><span class="text-slate-500">Plan:</span> {{ $ticket->workspace?->planName() }}</div>
            <div><span class="text-slate-500">Opened:</span> {{ $ticket->created_at->format('d M Y, h:i A') }}</div>
        </div>
        <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
            @csrf
            <div class="font-semibold text-sm">Change status</div>
            <select name="status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm">@foreach (SupportTicket::STATUSES as $k => [$l])<option value="{{ $k }}" @selected($ticket->status === $k)>{{ $l }}</option>@endforeach</select>
            <x-btn variant="outline">Update</x-btn>
        </form>
        @if ($ticket->workspace)
            <form method="POST" action="{{ route('admin.customers.impersonate', $ticket->workspace) }}">@csrf<x-btn variant="outline" icon="eye" class="w-full">Open their panel to check</x-btn></form>
        @endif
    </aside>
</div>
@endsection
