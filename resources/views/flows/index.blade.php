@extends('layouts.app', ['title' => 'Chatbot Builder'])

@section('content')
<x-page-header title="Chatbot Builder" subtitle="Visual WhatsApp chatbots: set up once, they answer 24/7.">
    <x-btn :href="route('flows.create', ['starter' => 'blank'])" icon="plus">Create flow</x-btn>
</x-page-header>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Total flows" :value="$stats['total']" :sub="$stats['active'].' live'" icon="flow" />
    <x-stat-card label="Conversations triggered" :value="number_format($stats['triggered'])" sub="All time" icon="chat" tone="green" />
    <x-stat-card label="Completed" :value="number_format($stats['completed'])" sub="Reached the end" icon="check" tone="sky" />
    <x-stat-card label="In progress now" :value="number_format($stats['live'])" sub="Last 24 hours" icon="pulse" tone="amber" />
</div>

<section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-950 via-brand-800 to-brand-600 text-white p-6 mb-6">
    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/5"></div>
    <div class="relative flex flex-col lg:flex-row lg:items-center gap-4">
        <span class="w-12 h-12 rounded-2xl bg-white/10 grid place-items-center"><x-icon name="bolt" class="w-6 h-6" /></span>
        <div class="flex-1">
            <div class="font-semibold text-lg">Automate your entire customer journey</div>
            <div class="text-brand-100 text-sm">Customer sends a keyword like "hi" → your flow asks with buttons and menus → answers are saved to the contact → hand over to your team when needed.</div>
        </div>
    </div>
    <div class="relative grid grid-cols-1 md:grid-cols-3 gap-3 mt-5">
        @foreach ($starters as $key => [$label, $desc])
            <a href="{{ route('flows.create', ['starter' => $key]) }}" class="rounded-2xl bg-white/10 border border-white/10 hover:bg-white/15 p-4">
                <div class="font-semibold">{{ $label }}</div>
                <div class="text-xs text-brand-100 mt-1">{{ $desc }}</div>
                <div class="text-xs font-semibold mt-3">Use this →</div>
            </a>
        @endforeach
    </div>
</section>

@if ($flows->isEmpty())
    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon name="flow" class="w-7 h-7" /></div>
        <div class="font-semibold mt-4">No flows yet</div>
        <p class="text-sm text-slate-500 mt-1">Pick a ready-made flow above, or start from scratch. It takes under 5 minutes.</p>
    </div>
@else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach ($flows as $f)
            @php $steps = count($f->definition['nodes'] ?? []); @endphp
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 grid place-items-center shrink-0"><x-icon name="flow" class="w-5 h-5" /></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('flows.edit', $f) }}" class="font-semibold text-slate-900 hover:text-brand-700 truncate">{{ $f->name }}</a>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $f->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $f->status === 'active' ? '● Live' : 'Draft' }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach ($f->triggers as $t)<code class="text-xs px-2 py-0.5 rounded bg-slate-100">{{ $t }}</code>@endforeach
                        </div>
                        <div class="text-xs text-slate-500 mt-2">{{ $steps }} steps · {{ $f->whatsappAccount?->display_phone_number ?? 'All numbers' }} · updated {{ $f->updated_at->diffForHumans() }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="rounded-xl bg-slate-50 p-3"><div class="text-lg font-bold">{{ number_format($f->triggered_count) }}</div><div class="text-xs text-slate-500">Triggered</div></div>
                    <div class="rounded-xl bg-slate-50 p-3"><div class="text-lg font-bold">{{ number_format($f->completed_count) }}</div><div class="text-xs text-slate-500">Completed</div></div>
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <x-btn :href="route('flows.edit', $f)" variant="outline" icon="pencil">Edit</x-btn>
                    <form method="POST" action="{{ route('flows.toggle', $f) }}">@csrf<x-btn variant="outline" :icon="$f->status === 'active' ? 'pause' : 'play'">{{ $f->status === 'active' ? 'Pause' : 'Go live' }}</x-btn></form>
                    <form method="POST" action="{{ route('flows.duplicate', $f) }}">@csrf<x-btn variant="outline">Duplicate</x-btn></form>
                    <form method="POST" action="{{ route('flows.destroy', $f) }}" onsubmit="return confirm('Delete this flow?')" class="ml-auto">@csrf @method('DELETE')<x-btn variant="danger" icon="trash">Delete</x-btn></form>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
