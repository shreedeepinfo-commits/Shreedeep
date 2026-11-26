@forelse ($conversations as $c)
    @php
        $left = $c->windowSecondsLeft();
        $statusColor = ['open' => 'bg-emerald-500', 'pending' => 'bg-amber-500', 'resolved' => 'bg-slate-400'][$c->chat_status] ?? 'bg-emerald-500';
    @endphp
    <a href="{{ route('inbox.show', ['contact' => $c, 'tab' => $tab]) }}" class="flex gap-3 px-4 py-3 border-b border-slate-100 transition {{ $contact?->id === $c->id ? 'bg-brand-50 border-l-4 border-l-brand-600' : 'hover:bg-slate-50' }}">
        <span class="relative w-10 h-10 rounded-full bg-brand-100 text-brand-700 grid place-items-center font-semibold uppercase shrink-0">
            {{ mb_substr($c->name ?: '#', 0, 1) }}
            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white {{ $statusColor }}" title="{{ ucfirst($c->chat_status ?? 'open') }}"></span>
        </span>
        <span class="flex-1 min-w-0">
            <span class="flex items-center gap-2">
                <span class="font-semibold text-sm truncate flex-1 {{ $c->unread_count ? 'text-slate-900' : 'text-slate-700' }}">{{ $c->name ?: '+'.$c->phone }}</span>
                <span class="text-[10px] text-slate-400 shrink-0">{{ $c->last_message_at?->shortAbsoluteDiffForHumans() }}</span>
            </span>
            <span class="flex items-center gap-2 mt-0.5">
                <span class="text-xs text-slate-500 truncate flex-1">{{ $c->assignedUser ? '👤 '.Str::before($c->assignedUser->name, ' ') : 'Unassigned' }}</span>
                @if ($left > 0)<span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 shrink-0" title="Reply window">{{ intdiv($left, 3600) }}h left</span>@endif
                @if ($c->unread_count)<span class="min-w-5 h-5 px-1.5 rounded-full bg-brand-600 text-white text-[11px] font-semibold grid place-items-center shrink-0">{{ $c->unread_count }}</span>@endif
            </span>
        </span>
    </a>
@empty
    <div class="p-8 text-center text-sm text-slate-500">
        <x-icon name="chat" class="w-10 h-10 mx-auto text-slate-300" />
        <div class="mt-2">No conversations here.</div>
        <div class="text-xs mt-1">Customer replies to your campaigns and chatbots show up here.</div>
    </div>
@endforelse
