@extends('layouts.app', ['title' => 'Automations'])

@section('content')
<x-page-header title="Automations" subtitle="Messages that go out on their own, at the right time, 24/7.">
    <x-btn :href="route('automations.create', ['type' => 'keyword'])" icon="plus">New keyword reply</x-btn>
</x-page-header>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Automations" :value="$stats['total']" :sub="$stats['active'].' on'" icon="bolt" />
    <x-stat-card label="Messages sent" :value="\App\Support\Num::indian($stats['sent'])" sub="All time" icon="send" tone="green" />
    <x-stat-card label="In drip sequences" :value="\App\Support\Num::indian($stats['in_drip'])" sub="Contacts waiting for next step" icon="clock" tone="sky" />
    <x-stat-card label="Keyword replies" :value="($automations['keyword'] ?? collect())->count()" sub="Instant answers" icon="chat" tone="amber" />
</div>

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-950 via-brand-800 to-brand-600 text-white p-6 mb-6">
    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/5"></div>
    <div class="relative flex items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-white/10 grid place-items-center"><x-icon name="bolt" class="w-6 h-6" /></span>
        <div>
            <div class="font-semibold text-lg">Set it once, it works every day</div>
            <div class="text-brand-100 text-sm">Greet new customers, reply after hours, answer common questions, nurture leads and send birthday wishes, without lifting a finger.</div>
        </div>
    </div>
    <div class="relative grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mt-5">
        @foreach (\App\Models\Automation::TYPES as $key => [$label, $desc, $icon])
            @php $single = in_array($key, ['welcome', 'away']) ? ($automations[$key] ?? collect())->first() : null; @endphp
            <a href="{{ $single ? route('automations.edit', $single) : route('automations.create', ['type' => $key]) }}" class="rounded-2xl bg-white/10 border border-white/10 hover:bg-white/15 p-4">
                <x-icon :name="$icon" class="w-5 h-5 text-brand-100" />
                <div class="font-semibold mt-2">{{ $label }}</div>
                <div class="text-xs text-brand-100 mt-1">{{ $desc }}</div>
                <div class="text-xs font-semibold mt-3">{{ $single ? ($single->active ? '● On · Edit →' : 'Off · Edit →') : 'Set up →' }}</div>
            </a>
        @endforeach
    </div>
</section>

@php
    $sections = [
        'Instant replies' => ['welcome', 'away', 'keyword'],
        'Scheduled messages' => ['drip', 'date'],
    ];
@endphp

@foreach ($sections as $heading => $types)
    @php $items = collect($types)->flatMap(fn ($t) => $automations[$t] ?? []); @endphp
    <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3 mt-2">{{ $heading }}</h2>
    @if ($items->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 mb-6">
            Nothing here yet. Pick a card above to set one up.
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            @foreach ($items as $a)
                @php $c = $a->config; $e = $enrollments[$a->id] ?? collect(); @endphp
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 grid place-items-center shrink-0"><x-icon :name="\App\Models\Automation::TYPES[$a->type][2]" class="w-5 h-5" /></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('automations.edit', $a) }}" class="font-semibold text-slate-900 hover:text-brand-700 truncate">{{ $a->name }}</a>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $a->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $a->active ? '● On' : 'Off' }}</span>
                            </div>
                            <div class="text-xs text-brand-700 font-medium mt-0.5">{{ $a->typeLabel() }}</div>
                            <div class="text-sm text-slate-600 mt-2">
                                @switch($a->type)
                                    @case('welcome')
                                        <p class="line-clamp-2">{{ $c['message'] ?? '' }}</p>
                                        @break
                                    @case('away')
                                        @php $open = collect($c['hours'] ?? [])->filter(fn ($h) => ! empty($h['open'])); @endphp
                                        <p>Open {{ $open->count() }} days a week · {{ $c['timezone'] ?? '' }}</p>
                                        <p class="text-xs mt-1 {{ $a->isOpenAt(now()) ? 'text-emerald-700' : 'text-amber-700' }}">{{ $a->isOpenAt(now()) ? 'Open right now' : 'Closed right now: away reply is active' }}</p>
                                        @break
                                    @case('keyword')
                                        <div class="flex flex-wrap gap-1">@foreach ($c['keywords'] ?? [] as $k)<code class="text-xs px-2 py-0.5 rounded bg-slate-100">{{ $k }}</code>@endforeach</div>
                                        <p class="line-clamp-2 mt-2">{{ $c['reply'] ?? '' }}</p>
                                        @break
                                    @case('drip')
                                        <p>{{ count($c['steps'] ?? []) }} steps · {{ collect($c['steps'] ?? [])->map(fn ($s) => $templates[$s['template_id']] ?? '?')->join(' → ') }}</p>
                                        <p class="text-xs text-slate-500 mt-1">{{ $e['active'] ?? 0 }} in progress · {{ $e['done'] ?? 0 }} finished</p>
                                        @break
                                    @case('date')
                                        <p>Field <code class="text-xs px-1.5 py-0.5 rounded bg-slate-100">{{ $c['field'] ?? '' }}</code> · {{ ($c['days_before'] ?? 0) ? $c['days_before'].' day(s) before' : 'on the day' }} at {{ $c['time'] ?? '' }}</p>
                                        <p class="text-xs text-slate-500 mt-1">Template: {{ $templates[$c['template_id'] ?? 0] ?? '—' }}</p>
                                        @break
                                @endswitch
                            </div>
                        </div>
                        <div class="text-right shrink-0"><div class="text-lg font-bold">{{ \App\Support\Num::indian($a->sent_count) }}</div><div class="text-xs text-slate-500">sent</div></div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <x-btn :href="route('automations.edit', $a)" variant="outline" icon="pencil">Edit</x-btn>
                        <form method="POST" action="{{ route('automations.toggle', $a) }}">@csrf<x-btn variant="outline" :icon="$a->active ? 'pause' : 'play'">{{ $a->active ? 'Turn off' : 'Turn on' }}</x-btn></form>
                        <form method="POST" action="{{ route('automations.destroy', $a) }}" onsubmit="return confirm('Delete this automation?')" class="ml-auto">@csrf @method('DELETE')<x-btn variant="danger" icon="trash">Delete</x-btn></form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endforeach
@endsection
