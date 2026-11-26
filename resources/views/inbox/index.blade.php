@extends('layouts.app', ['title' => 'Inbox'])

@push('head')<meta http-equiv="refresh" content="20">@endpush

@section('content')
<h1 class="text-2xl font-semibold mb-6">Inbox</h1>
<div class="bg-white rounded-xl shadow flex h-[75vh] overflow-hidden">
    <div class="w-80 border-r flex flex-col">
        <form class="p-3 border-b"><input name="q" value="{{ request('q') }}" placeholder="Search" class="w-full border rounded px-3 py-1.5 text-sm"></form>
        <div class="flex-1 overflow-y-auto">
            @forelse ($conversations as $c)
                <a href="{{ route('inbox.show', $c) }}" class="block px-4 py-3 border-b text-sm {{ $contact?->id === $c->id ? 'bg-emerald-50' : 'hover:bg-slate-50' }}">
                    <div class="font-medium">{{ $c->name ?: '+'.$c->phone }}</div>
                    <div class="text-xs text-slate-500">+{{ $c->phone }} · {{ $c->last_message_at?->diffForHumans() }}</div>
                </a>
            @empty
                <div class="p-6 text-sm text-slate-500">No conversations yet. Replies to your campaigns will show up here.</div>
            @endforelse
        </div>
    </div>

    <div class="flex-1 flex flex-col">
        @if ($contact)
            <div class="px-5 py-3 border-b">
                <div class="font-semibold">{{ $contact->name ?: '+'.$contact->phone }}</div>
                <div class="text-xs text-slate-500">+{{ $contact->phone }} @if($contact->opted_out_at)<span class="text-red-700">· opted out</span>@endif</div>
            </div>
            <div class="flex-1 overflow-y-auto p-5 space-y-2 bg-[#efeae2]" id="thread">
                @foreach ($thread as $m)
                    <div class="flex {{ $m->direction === 'out' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[70%] rounded-lg px-3 py-2 text-sm shadow {{ $m->direction === 'out' ? 'bg-[#d9fdd3]' : 'bg-white' }}">
                            <div class="whitespace-pre-line">{{ $m->body ?: '['.$m->type.']' }}</div>
                            <div class="text-[10px] text-slate-500 text-right mt-1">
                                @if ($m->campaign_id)campaign · @endif{{ $m->created_at->format('d M, h:i A') }}
                                @if ($m->direction === 'out') · {{ $m->status }}@endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('inbox.reply', $contact) }}" class="p-3 border-t flex gap-2">
                @csrf
                @if ($windowOpen)
                    <input name="body" placeholder="Type a reply" class="flex-1 border rounded px-3 py-2" autocomplete="off" required>
                    <button class="rounded bg-emerald-600 text-white px-4 font-semibold">Send</button>
                @else
                    <div class="text-sm text-slate-600">The 24-hour reply window is closed. Send an approved template from <a href="{{ route('campaigns.create') }}" class="underline">Campaigns</a>.</div>
                @endif
            </form>
            <script>document.getElementById('thread').scrollTop = 1e9;</script>
        @else
            <div class="flex-1 flex items-center justify-center text-slate-500">Select a conversation</div>
        @endif
    </div>
</div>
@endsection
