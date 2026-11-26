@extends('layouts.app', ['title' => $contact->displayName()])

@section('content')
@include('contacts.partials.tabs')

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    {{-- Left: profile + lead --}}
    <div class="space-y-6">
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600 text-white p-6 shadow-lg">
            <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-white/5"></div>
            <div class="relative flex items-center gap-4">
                <span class="w-14 h-14 rounded-2xl bg-white/15 grid place-items-center text-2xl font-bold uppercase">{{ mb_substr($contact->name ?: '#', 0, 1) }}</span>
                <div class="min-w-0">
                    <div class="text-lg font-bold truncate">{{ $contact->name ?: 'Unknown' }}</div>
                    <div class="text-brand-100 text-sm font-mono">+{{ $contact->phone }}</div>
                    @if ($contact->email)<div class="text-brand-200 text-xs truncate">{{ $contact->email }}</div>@endif
                </div>
            </div>
            <div class="relative flex flex-wrap gap-2 mt-4">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white text-brand-700">{{ $contact->leadLabel() }}</span>
                @if ($contact->blocked_at)<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-900 text-white">Blacklisted</span>
                @elseif ($contact->opted_out_at)<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-red-500 text-white">Opted out</span>
                @else<span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-400/90 text-white">Subscribed</span>@endif
            </div>
            <div class="relative grid grid-cols-3 gap-2 mt-5 text-center">
                @foreach ([['Sent', $stats['sent']], ['Received', $stats['received']], ['Campaigns', $stats['campaigns']]] as [$l, $v])
                    <div class="rounded-xl bg-white/10 py-2"><div class="text-lg font-bold">{{ number_format($v) }}</div><div class="text-[11px] text-brand-200">{{ $l }}</div></div>
                @endforeach
            </div>
            <div class="relative flex flex-wrap gap-2 mt-5">
                <a href="{{ route('inbox.show', $contact) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white text-brand-700 px-3 py-2 text-sm font-semibold hover:bg-brand-50"><x-icon name="chat" class="w-4 h-4" /> Chat</a>
                <a href="{{ route('contacts.edit', $contact) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/30 px-3 py-2 text-sm font-semibold hover:bg-white/10"><x-icon name="pencil" class="w-4 h-4" /> Edit</a>
                @if ($isManager)
                    <form method="POST" action="{{ route('contacts.block', $contact) }}" onsubmit="return confirm(@js($contact->blocked_at ? 'Remove this number from the blacklist?' : 'Blacklist this number? It will never get campaigns, chats or bot replies.'))">
                        @csrf
                        <button class="inline-flex items-center gap-1.5 rounded-xl border border-white/30 px-3 py-2 text-sm font-semibold hover:bg-white/10"><x-icon name="shield" class="w-4 h-4" /> {{ $contact->blocked_at ? 'Unblock' : 'Blacklist' }}</button>
                    </form>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('contacts.lead', $contact) }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 space-y-3">
            @csrf
            <div class="font-semibold flex items-center gap-2"><x-icon name="chart" class="w-5 h-5 text-brand-600" /> Lead</div>
            <div class="grid grid-cols-2 gap-3">
                <label class="block"><span class="text-xs font-medium text-slate-500">Stage</span>
                    <select name="lead_status" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        @foreach (\App\Models\Contact::LEAD_STATUSES as $key => [$label])<option value="{{ $key }}" @selected($contact->lead_status === $key)>{{ $label }}</option>@endforeach
                    </select></label>
                <label class="block"><span class="text-xs font-medium text-slate-500">Deal value (₹)</span>
                    <input name="deal_value" type="number" step="0.01" min="0" value="{{ $contact->deal_value !== null ? (float) $contact->deal_value : '' }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></label>
            </div>
            <label class="block"><span class="text-xs font-medium text-slate-500">Owner</span>
                <select name="assigned_user_id" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Nobody</option>
                    @foreach ($members as $m)<option value="{{ $m->id }}" @selected($contact->assigned_user_id === $m->id)>{{ $m->name }}</option>@endforeach
                </select></label>
            <label class="block"><span class="text-xs font-medium text-slate-500">Lead source</span>
                <input name="opt_in_source" value="{{ $contact->opt_in_source }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block"><span class="text-xs font-medium text-slate-500">Follow-up on</span>
                    <input name="follow_up_at" type="datetime-local" value="{{ $contact->follow_up_at?->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></label>
                <label class="block"><span class="text-xs font-medium text-slate-500">Reminder note</span>
                    <input name="follow_up_note" value="{{ $contact->follow_up_note }}" placeholder="Call about site visit" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></label>
            </div>
            <x-btn class="w-full">Save lead</x-btn>
        </form>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <div class="font-semibold mb-3">Details</div>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Lists</dt><dd class="text-right">{{ $contact->groups->pluck('name')->implode(', ') ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tags</dt><dd class="text-right">{{ implode(', ', $contact->tags ?? []) ?: '—' }}</dd></div>
                @foreach ($contact->fields ?? [] as $k => $v)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 capitalize">{{ str_replace('_', ' ', $k) }}</dt><dd class="text-right font-medium">{{ is_scalar($v) ? $v : json_encode($v) }}</dd></div>
                @endforeach
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Added</dt><dd>{{ $contact->created_at?->format('d M Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Last message</dt><dd>{{ $contact->last_message_at?->diffForHumans() ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    {{-- Right: notes + activity timeline --}}
    <div class="xl:col-span-2 space-y-6">
        <form method="POST" action="{{ route('contacts.notes.store', $contact) }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex gap-3">
            @csrf
            <textarea name="body" rows="2" placeholder="Add an internal note (only your team sees it)" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm" required></textarea>
            <button class="self-end rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 px-4 py-2 text-sm font-semibold">Add note</button>
        </form>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <div class="font-semibold mb-4">Activity &amp; conversation history</div>
            <ol class="relative border-l-2 border-slate-100 ml-3 space-y-5">
                @forelse ($timeline as $row)
                    @php $item = $row['item']; @endphp
                    <li class="ml-6">
                        @if ($row['kind'] === 'message')
                            @php $out = $item->direction === 'out'; @endphp
                            <span class="absolute -left-[13px] w-6 h-6 rounded-full grid place-items-center ring-4 ring-white {{ $out ? 'bg-brand-600 text-white' : 'bg-emerald-500 text-white' }}"><x-icon :name="$out ? 'send' : 'chat'" class="w-3 h-3" /></span>
                            <div class="text-xs text-slate-400">
                                {{ $out ? ($item->campaign ? 'Campaign: '.$item->campaign->name : ($item->user ? 'Sent by '.$item->user->name : 'Sent by chatbot')) : 'Customer wrote' }}
                                · {{ $row['at']->format('d M Y, h:i A') }}
                                @if ($out) · <span class="{{ $item->status === 'failed' ? 'text-red-600' : '' }}">{{ ucfirst($item->status) }}</span>@endif
                            </div>
                            <div class="mt-1 inline-block max-w-full rounded-xl px-3 py-2 text-sm whitespace-pre-line break-words {{ $out ? 'bg-brand-50' : 'bg-slate-100' }}">{{ $item->body ?: '['.$item->type.']' }}</div>
                        @elseif ($item->system)
                            <span class="absolute -left-[13px] w-6 h-6 rounded-full grid place-items-center ring-4 ring-white bg-slate-200 text-slate-600"><x-icon name="bolt" class="w-3 h-3" /></span>
                            <div class="text-sm text-slate-600">{{ $item->body }} <span class="text-xs text-slate-400">· {{ $item->user?->name }} · {{ $row['at']->format('d M Y, h:i A') }}</span></div>
                        @else
                            <span class="absolute -left-[13px] w-6 h-6 rounded-full grid place-items-center ring-4 ring-white bg-amber-400 text-white"><x-icon name="pencil" class="w-3 h-3" /></span>
                            <div class="text-xs text-slate-400">Note by {{ $item->user?->name }} · {{ $row['at']->format('d M Y, h:i A') }}</div>
                            <div class="mt-1 rounded-xl bg-amber-50 border border-amber-100 px-3 py-2 text-sm whitespace-pre-line break-words">{{ $item->body }}</div>
                        @endif
                    </li>
                @empty
                    <li class="ml-6 text-sm text-slate-500">No activity yet.</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>
@endsection
