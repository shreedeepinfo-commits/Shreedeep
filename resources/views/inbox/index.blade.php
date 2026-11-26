@extends('layouts.app', ['title' => 'Live Chat'])

@php
    $left = $contact?->windowSecondsLeft() ?? 0;
    $lastId = $thread->max('id') ?? 0;
@endphp

@section('content')
<x-page-header title="Live Chat" subtitle="Shared team inbox: reply, assign, tag and resolve customer chats.">
    <x-btn variant="outline" icon="bolt" id="notifyBtn" type="button" class="hidden">Enable notifications</x-btn>
    <x-btn variant="outline" icon="list" :href="route('quick-replies.index')">Quick replies</x-btn>
</x-page-header>

{{-- Tabs --}}
<div class="flex flex-wrap gap-2 mb-4">
    @foreach (\App\Http\Controllers\InboxController::TABS as $key => $label)
        <a href="{{ route('inbox.index', ['tab' => $key]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition {{ $tab === $key ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/30' : 'bg-white border border-slate-200 text-slate-600 hover:border-brand-300' }}">
            {{ $label }}
            <span data-count="{{ $key }}" class="text-xs px-1.5 py-0.5 rounded-md {{ $tab === $key ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">{{ number_format($counts[$key]) }}</span>
        </a>
    @endforeach
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex h-[calc(100vh-15rem)] min-h-[32rem] overflow-hidden">
    {{-- Conversations --}}
    <div class="w-full md:w-80 border-r border-slate-200 flex-col {{ $contact ? 'hidden md:flex' : 'flex' }}">
        <form class="p-3 border-b border-slate-200">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input name="q" value="{{ request('q') }}" placeholder="Search name or number" class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 py-2 text-sm focus:bg-white focus:border-brand-400 outline-none">
            </div>
        </form>
        <div id="convList" class="flex-1 overflow-y-auto">
            @include('inbox.partials.list')
        </div>
    </div>

    {{-- Thread --}}
    <div class="flex-1 flex-col min-w-0 {{ $contact ? 'flex' : 'hidden md:flex' }}">
        @if ($contact)
            <div class="px-5 py-3 border-b border-slate-200 flex items-center gap-3">
                <a href="{{ route('inbox.index', ['tab' => $tab]) }}" class="md:hidden p-1 -ml-2 text-slate-500">‹</a>
                <span class="w-10 h-10 rounded-full bg-brand-600 text-white grid place-items-center font-semibold uppercase shrink-0">{{ mb_substr($contact->name ?: '#', 0, 1) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold truncate">{{ $contact->name ?: '+'.$contact->phone }}</div>
                    <div class="text-xs text-slate-500">+{{ $contact->phone }} @if ($contact->opted_out_at)<span class="text-red-600">· opted out</span>@endif</div>
                </div>
                <span id="windowTimer" data-seconds="{{ $left }}" class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full {{ $left > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    <x-icon name="clock" class="w-3.5 h-3.5" /> <span>{{ $left > 0 ? gmdate('H:i:s', $left).' left' : 'Window closed' }}</span>
                </span>
                <div class="flex rounded-xl border border-slate-200 overflow-hidden text-xs font-semibold">
                    @foreach (['open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved'] as $value => $label)
                        <form method="POST" action="{{ route('inbox.status', $contact) }}">
                            @csrf<input type="hidden" name="status" value="{{ $value }}">
                            <button class="px-3 py-2 {{ ($contact->chat_status ?? 'open') === $value ? 'bg-brand-600 text-white' : 'hover:bg-slate-50 text-slate-600' }}">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
            </div>

            <div id="thread" class="flex-1 overflow-y-auto p-5 space-y-2 bg-[#efeae2]">
                @foreach ($thread as $m)
                    @include('inbox.partials.message', ['m' => $m])
                @endforeach
            </div>

            <div id="composerWrap" class="border-t border-slate-200 p-3 {{ $left > 0 ? '' : 'hidden' }}">
                <div id="qrMenu" class="hidden mb-2 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg"></div>
                <div id="filePreview" class="hidden mb-2 items-center gap-2 text-sm bg-brand-50 text-brand-800 rounded-xl px-3 py-2">
                    <x-icon name="document" class="w-4 h-4" /> <span class="flex-1 truncate" id="fileName"></span>
                    <button type="button" id="fileClear" class="text-brand-700 font-bold px-1">×</button>
                </div>
                <form id="composer" method="POST" action="{{ route('inbox.reply', $contact) }}" enctype="multipart/form-data" class="flex items-end gap-2">
                    @csrf
                    <label class="p-2.5 rounded-xl hover:bg-slate-100 text-slate-500 cursor-pointer" title="Attach photo, video, audio or document">
                        <x-icon name="upload" class="w-5 h-5" />
                        <input type="file" name="file" id="fileInput" class="hidden" accept="image/jpeg,image/png,video/mp4,video/3gpp,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                    </label>
                    <button type="button" id="qrBtn" class="p-2.5 rounded-xl hover:bg-slate-100 text-slate-500" title="Quick replies (type /)"><x-icon name="bolt" class="w-5 h-5" /></button>
                    <textarea name="body" id="bodyInput" rows="1" placeholder="Type a reply… ( / for quick replies )" class="flex-1 resize-none rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-brand-400 outline-none max-h-40"></textarea>
                    <button class="inline-flex items-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-4 py-2.5 text-sm font-semibold"><x-icon name="send" class="w-4 h-4" /> Send</button>
                </form>
                <div id="sendError" class="hidden text-xs text-red-600 mt-2"></div>
            </div>
            <div id="windowClosed" class="border-t border-slate-200 p-4 text-sm text-slate-600 bg-amber-50 {{ $left > 0 ? 'hidden' : '' }}">
                The 24-hour reply window is closed. Send an approved template from <a href="{{ route('campaigns.create') }}" class="font-semibold text-brand-700 underline">Campaigns</a> to restart the chat.
            </div>
        @else
            <div class="flex-1 grid place-items-center text-center p-8 bg-slate-50">
                <div>
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon name="chat" class="w-8 h-8" /></div>
                    <div class="font-semibold mt-4">Select a conversation</div>
                    <div class="text-sm text-slate-500 mt-1">New messages appear here live.</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Contact details --}}
    @if ($contact)
        <aside class="hidden xl:flex w-80 border-l border-slate-200 flex-col overflow-y-auto">
            <div class="p-5 border-b border-slate-200 text-center">
                <span class="w-16 h-16 mx-auto rounded-full bg-brand-100 text-brand-700 grid place-items-center text-2xl font-semibold uppercase">{{ mb_substr($contact->name ?: '#', 0, 1) }}</span>
                <div class="font-semibold mt-3">{{ $contact->name ?: 'Unknown' }}</div>
                <div class="text-sm text-slate-500">+{{ $contact->phone }}</div>
                @if ($contact->email)<div class="text-xs text-slate-400">{{ $contact->email }}</div>@endif
                <a href="{{ route('contacts.edit', $contact) }}" class="inline-block mt-2 text-xs font-semibold text-brand-700 hover:underline">Edit contact</a>
            </div>

            <div class="p-5 border-b border-slate-200">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Assigned to</div>
                <form method="POST" action="{{ route('inbox.assign', $contact) }}">
                    @csrf
                    <select name="user_id" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Unassigned</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected($contact->assigned_user_id === $member->id)>{{ $member->name }}{{ $member->id === auth()->id() ? ' (me)' : '' }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="p-5 border-b border-slate-200">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Tags</div>
                @if ($contact->tags)
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        @foreach ($contact->tags as $t)<span class="text-xs px-2 py-1 rounded-lg bg-brand-50 text-brand-700 font-medium">{{ $t }}</span>@endforeach
                    </div>
                @endif
                <form method="POST" action="{{ route('inbox.tags', $contact) }}" class="flex gap-2">
                    @csrf
                    <input name="tags" value="{{ implode(', ', $contact->tags ?? []) }}" placeholder="hot lead, pune" class="flex-1 min-w-0 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <button class="rounded-xl bg-slate-100 hover:bg-slate-200 px-3 text-sm font-semibold">Save</button>
                </form>
            </div>

            @if ($contact->fields)
                <div class="p-5 border-b border-slate-200">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Details</div>
                    <dl class="text-sm space-y-1">
                        @foreach ($contact->fields as $k => $v)
                            <div class="flex gap-2"><dt class="text-slate-500 capitalize">{{ str_replace('_', ' ', $k) }}:</dt><dd class="font-medium truncate">{{ is_scalar($v) ? $v : json_encode($v) }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endif

            <div class="p-5 flex-1">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Internal notes</div>
                <form method="POST" action="{{ route('inbox.notes.store', $contact) }}" class="mb-3">
                    @csrf
                    <textarea name="body" rows="2" placeholder="Only your team sees this" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required></textarea>
                    <button class="mt-1 w-full rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 py-1.5 text-sm font-semibold">Add note</button>
                </form>
                <div class="space-y-2">
                    @foreach ($contact->notes as $note)
                        @if ($note->system)
                            <div class="text-[11px] text-slate-400 text-center">{{ $note->body }} · {{ $note->user?->name }} · {{ $note->created_at->diffForHumans() }}</div>
                        @else
                            <div class="rounded-xl bg-amber-50 border border-amber-100 p-3 text-sm">
                                <div class="whitespace-pre-line break-words">{{ $note->body }}</div>
                                <div class="flex items-center justify-between text-[11px] text-amber-800/70 mt-1">
                                    <span>{{ $note->user?->name }} · {{ $note->created_at->diffForHumans() }}</span>
                                    <form method="POST" action="{{ route('inbox.notes.destroy', [$contact, $note]) }}">@csrf @method('DELETE')<button class="hover:text-red-600">Delete</button></form>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </aside>
    @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const state = {
        tab: @json($tab),
        q: @json(request('q', '')),
        contact: @json($contact?->id),
        lastId: @json($lastId),
        pollUrl: @json(route('inbox.poll')),
        quickReplies: @json($quickReplies->map(fn ($r) => ['shortcut' => $r->shortcut, 'body' => $r->body])->values()),
        windowSeconds: @json($left),
    };
    const thread = document.getElementById('thread');
    const scrollDown = () => { if (thread) thread.scrollTop = thread.scrollHeight; };
    scrollDown();

    // 24-hour window countdown
    const timer = document.getElementById('windowTimer');
    const fmt = s => [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60].map(n => String(n).padStart(2, '0')).join(':');
    function renderWindow() {
        if (!timer) return;
        const open = state.windowSeconds > 0;
        timer.querySelector('span').textContent = open ? fmt(state.windowSeconds) + ' left' : 'Window closed';
        timer.className = timer.className.replace(/bg-\S+ text-\S+/, open ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500');
        document.getElementById('composerWrap')?.classList.toggle('hidden', !open);
        document.getElementById('windowClosed')?.classList.toggle('hidden', open);
    }
    setInterval(() => { if (state.windowSeconds > 0) { state.windowSeconds--; renderWindow(); } }, 1000);

    // Quick replies: button or typing "/"
    const body = document.getElementById('bodyInput');
    const menu = document.getElementById('qrMenu');
    function showMenu(filter) {
        if (!menu) return;
        const items = state.quickReplies.filter(r => r.shortcut.startsWith(filter));
        if (!items.length) { menu.classList.add('hidden'); return; }
        menu.innerHTML = '';
        items.forEach(r => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full text-left px-3 py-2 hover:bg-brand-50 text-sm border-b border-slate-100';
            const s = document.createElement('span'); s.className = 'font-semibold text-brand-700'; s.textContent = '/' + r.shortcut + ' ';
            const t = document.createElement('span'); t.className = 'text-slate-500'; t.textContent = r.body.slice(0, 90);
            b.append(s, t);
            b.onclick = () => { body.value = r.body; menu.classList.add('hidden'); body.focus(); };
            menu.appendChild(b);
        });
        menu.classList.remove('hidden');
    }
    body?.addEventListener('input', () => {
        body.style.height = 'auto'; body.style.height = Math.min(body.scrollHeight, 160) + 'px';
        const m = body.value.match(/^\/(\S*)$/);
        m ? showMenu(m[1].toLowerCase()) : menu.classList.add('hidden');
    });
    document.getElementById('qrBtn')?.addEventListener('click', () => menu.classList.contains('hidden') ? showMenu('') : menu.classList.add('hidden'));

    // Attachment preview
    const fileInput = document.getElementById('fileInput');
    const preview = document.getElementById('filePreview');
    fileInput?.addEventListener('change', () => {
        const f = fileInput.files[0];
        preview.classList.toggle('hidden', !f); preview.classList.toggle('flex', !!f);
        document.getElementById('fileName').textContent = f ? f.name : '';
    });
    document.getElementById('fileClear')?.addEventListener('click', () => { fileInput.value = ''; fileInput.dispatchEvent(new Event('change')); });

    // Send without reloading; Enter sends, Shift+Enter adds a line.
    const form = document.getElementById('composer');
    const err = document.getElementById('sendError');
    body?.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    form?.addEventListener('submit', async e => {
        e.preventDefault();
        if (!body.value.trim() && !fileInput.files.length) return;
        const btn = form.querySelector('button:last-child'); btn.disabled = true; err.classList.add('hidden');
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Could not send');
            thread.insertAdjacentHTML('beforeend', data.html);
            state.lastId = Math.max(state.lastId, data.id);
            body.value = ''; body.style.height = 'auto'; fileInput.value = ''; fileInput.dispatchEvent(new Event('change'));
            scrollDown();
        } catch (x) { err.textContent = x.message; err.classList.remove('hidden'); }
        btn.disabled = false; body.focus();
    });

    // Desktop notifications
    const notifyBtn = document.getElementById('notifyBtn');
    if ('Notification' in window && Notification.permission === 'default') {
        notifyBtn.classList.remove('hidden');
        notifyBtn.onclick = () => Notification.requestPermission().then(() => notifyBtn.classList.add('hidden'));
    }
    let seenInbound = Number(localStorage.getItem('inbox.seenInbound') || 0);
    const baseTitle = document.title;

    // Live updates
    async function poll() {
        const params = new URLSearchParams({ tab: state.tab, q: state.q || '', after: state.lastId });
        if (state.contact) params.set('contact', state.contact);
        try {
            const res = await fetch(state.pollUrl + '?' + params, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            document.getElementById('convList').innerHTML = data.list;
            Object.entries(data.counts).forEach(([k, v]) => { const el = document.querySelector('[data-count="' + k + '"]'); if (el) el.textContent = v.toLocaleString('en-IN'); });
            if (data.messages.length && thread) {
                const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;
                data.messages.forEach(html => {
                    const id = (html.match(/data-id="(\d+)"/) || [])[1];
                    if (!id || !thread.querySelector('[data-id="' + id + '"]')) thread.insertAdjacentHTML('beforeend', html);
                });
                if (nearBottom) scrollDown();
            }
            state.lastId = Math.max(state.lastId, data.last_id || 0);
            if (typeof data.window_seconds === 'number') { state.windowSeconds = data.window_seconds; renderWindow(); }
            document.title = (data.counts.unread ? '(' + data.counts.unread + ') ' : '') + baseTitle;
            const li = data.latest_inbound;
            if (li && li.id > seenInbound) {
                if (seenInbound && (document.hidden || li.contact_id !== state.contact) && 'Notification' in window && Notification.permission === 'granted') {
                    const n = new Notification('New message from ' + li.name, { body: li.body });
                    n.onclick = () => { window.focus(); location.href = @json(url('/inbox')) + '/' + li.contact_id; };
                }
                seenInbound = li.id; localStorage.setItem('inbox.seenInbound', String(li.id));
            }
        } catch (_) {}
    }
    setInterval(poll, 5000);
})();
</script>
@endpush
