@extends('layouts.app', ['title' => 'Sales Pipeline'])

@section('content')
<x-page-header title="Contacts & CRM" subtitle="Drag a lead to the next stage as the deal moves forward.">
    <x-btn variant="outline" :href="route('crm.pipeline', $mine ? [] : ['mine' => 1])">{{ $mine ? 'Show all leads' : 'Only my leads' }}</x-btn>
</x-page-header>

@include('contacts.partials.tabs')

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Open leads" :value="\App\Support\Num::indian($stages->except(['won', 'lost'])->sum('count'))" icon="users" />
    <x-stat-card label="Pipeline value" :value="'₹'.\App\Support\Num::indian($stages->except(['won', 'lost'])->sum('value'))" icon="chart" tone="sky" />
    <x-stat-card label="Won" :value="'₹'.\App\Support\Num::indian($stages['won']['value'])" :sub="$stages['won']['count'].' deals'" icon="check" tone="green" />
    @php $closed = $stages['won']['count'] + $stages['lost']['count']; @endphp
    <x-stat-card label="Win rate" :value="($closed ? round($stages['won']['count'] * 100 / $closed) : 0).'%'" sub="Won of closed deals" icon="bolt" />
</div>

<div class="flex gap-4 overflow-x-auto pb-4" id="board">
    @foreach ($stages as $stage)
        <div class="w-72 shrink-0 flex flex-col rounded-2xl bg-slate-100/70 border border-slate-200 max-h-[70vh]">
            <div class="px-4 py-3 flex items-center gap-2">
                <span class="text-xs font-semibold px-2 py-1 rounded-lg {{ $stage['color'] }}">{{ $stage['label'] }}</span>
                <span class="text-xs text-slate-500" data-stage-count="{{ $stage['key'] }}">{{ number_format($stage['count']) }}</span>
                <span class="ml-auto text-xs font-semibold text-slate-600">₹{{ \App\Support\Num::indian($stage['value']) }}</span>
            </div>
            <div class="flex-1 overflow-y-auto px-3 pb-3 space-y-2 min-h-[6rem]" data-dropzone="{{ $stage['key'] }}">
                @foreach ($stage['contacts'] as $c)
                    <a href="{{ route('contacts.show', $c) }}" draggable="true" data-contact="{{ $c->id }}" data-url="{{ route('contacts.lead', $c) }}"
                       class="block rounded-xl bg-white border border-slate-200 p-3 shadow-sm hover:border-brand-300 cursor-grab active:cursor-grabbing">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-xs font-semibold uppercase">{{ mb_substr($c->name ?: '#', 0, 1) }}</span>
                            <span class="font-medium text-sm truncate flex-1">{{ $c->displayName() }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-2 text-[11px] text-slate-500">
                            @if ($c->deal_value)<span class="font-semibold text-slate-700">₹{{ \App\Support\Num::indian($c->deal_value) }}</span>@endif
                            @if ($c->follow_up_at)<span class="{{ $c->follow_up_at->isPast() ? 'text-red-600' : '' }}">📅 {{ $c->follow_up_at->format('d M') }}</span>@endif
                            <span class="ml-auto truncate">{{ $c->assignedUser ? Str::before($c->assignedUser->name, ' ') : '' }}</span>
                        </div>
                        @if ($c->tags)<div class="flex flex-wrap gap-1 mt-2">@foreach (array_slice($c->tags, 0, 3) as $t)<span class="text-[10px] px-1.5 py-0.5 rounded bg-brand-50 text-brand-700">{{ $t }}</span>@endforeach</div>@endif
                    </a>
                @endforeach
                @if ($stage['count'] > $perStage)
                    <a href="{{ route('contacts.index', ['stage' => $stage['key']]) }}" class="block text-center text-xs font-semibold text-brand-700 py-2">View all {{ number_format($stage['count']) }} →</a>
                @endif
            </div>
        </div>
    @endforeach
</div>
<div id="boardError" class="hidden mt-2 text-sm text-red-600"></div>
@endsection

@push('scripts')
<script>
(() => {
    const token = document.querySelector('meta[name=csrf-token]')?.content || @json(csrf_token());
    let dragged = null;
    document.querySelectorAll('[data-contact]').forEach(card => {
        card.addEventListener('dragstart', e => { dragged = card; e.dataTransfer.effectAllowed = 'move'; card.classList.add('opacity-50'); });
        card.addEventListener('dragend', () => card.classList.remove('opacity-50'));
    });
    document.querySelectorAll('[data-dropzone]').forEach(zone => {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('bg-brand-50'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('bg-brand-50'));
        zone.addEventListener('drop', async e => {
            e.preventDefault(); zone.classList.remove('bg-brand-50');
            if (!dragged || dragged.parentElement === zone) return;
            const from = dragged.parentElement, card = dragged;
            zone.prepend(card);
            const bump = (key, d) => { const el = document.querySelector('[data-stage-count="' + key + '"]'); if (el) el.textContent = Math.max(0, parseInt(el.textContent.replace(/,/g, ''), 10) + d).toLocaleString(); };
            bump(from.dataset.dropzone, -1); bump(zone.dataset.dropzone, 1);
            try {
                const res = await fetch(card.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ lead_status: zone.dataset.dropzone }) });
                if (!res.ok) throw new Error();
            } catch (_) {
                from.prepend(card); bump(from.dataset.dropzone, 1); bump(zone.dataset.dropzone, -1);
                const err = document.getElementById('boardError'); err.textContent = 'Could not move the lead. Please try again.'; err.classList.remove('hidden');
            }
        });
    });
})();
</script>
@endpush
