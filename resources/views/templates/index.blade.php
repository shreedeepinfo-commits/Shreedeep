@extends('layouts.app', ['title' => 'Templates'])

@php
    $badge = fn ($s) => match ($s) {
        'APPROVED' => 'bg-emerald-50 text-emerald-700',
        'REJECTED', 'DISABLED' => 'bg-red-50 text-red-700',
        'PAUSED' => 'bg-amber-50 text-amber-700',
        default => 'bg-amber-50 text-amber-700',
    };
    $langs = ['en' => 'English', 'en_US' => 'English (US)', 'en_GB' => 'English (UK)', 'hi' => 'Hindi', 'gu' => 'Gujarati', 'mr' => 'Marathi', 'ta' => 'Tamil', 'te' => 'Telugu', 'bn' => 'Bengali', 'kn' => 'Kannada', 'ml' => 'Malayalam', 'pa' => 'Punjabi'];
    $query = fn (array $extra) => route('templates.index', array_filter(array_merge(request()->only('status', 'category', 'q', 't'), $extra), fn ($v) => $v !== null && $v !== ''));
@endphp

@section('content')
<x-page-header title="Message Templates" subtitle="Create and manage Meta-approved WhatsApp templates">
    <form method="POST" action="{{ route('templates.sync') }}">@csrf<x-btn variant="outline" icon="refresh" :disabled="$accounts->isEmpty()">Sync templates</x-btn></form>
    <x-btn :href="route('templates.create')" icon="plus">New template</x-btn>
</x-page-header>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Approved" :value="$kpis['approved']" sub="Ready to send" icon="check" tone="green" :href="$query(['status' => 'APPROVED', 't' => null])" />
    <x-stat-card label="Pending review" :value="$kpis['pending']" sub="In review at Meta" icon="clock" tone="amber" :href="$query(['status' => 'PENDING', 't' => null])" />
    <x-stat-card label="Rejected / Paused" :value="$kpis['rejected']" sub="Need attention" icon="alert" tone="red" :href="$query(['status' => 'REJECTED', 't' => null])" />
    <x-stat-card label="Total templates" :value="$kpis['total']" sub="All templates" icon="document" tone="slate" :href="$query(['status' => null, 't' => null])" />
</div>

@if ($accounts->isEmpty())
    <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-600">Connect a WhatsApp number first. <a class="text-brand-700 underline" href="{{ route('whatsapp.index') }}">Connect now</a></div>
@else
<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <div class="xl:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="border-b border-slate-200 px-4 pt-2">
            <nav class="flex gap-1 overflow-x-auto">
                @foreach (\App\Http\Controllers\TemplateController::TABS as $key => $label)
                    @php $n = ['all' => $kpis['total'], 'APPROVED' => $kpis['approved'], 'PENDING' => $kpis['pending'], 'REJECTED' => $kpis['rejected']][$key]; @endphp
                    <a href="{{ $query(['status' => $key === 'all' ? null : $key, 't' => null]) }}" class="whitespace-nowrap px-4 py-3 text-sm border-b-2 -mb-px {{ $status === $key ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800' }} {{ $key === 'REJECTED' && $n ? 'text-red-600' : '' }}">{{ $label }} <span class="opacity-60">({{ $n }})</span></a>
                @endforeach
            </nav>
        </div>
        <form class="flex flex-col md:flex-row gap-2 p-4 border-b border-slate-100">
            @if ($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
            <div class="relative flex-1">
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                <input name="q" value="{{ request('q') }}" placeholder="Search templates…" class="w-full border border-slate-200 rounded-xl pl-9 pr-3 py-2.5 text-sm">
            </div>
            <select name="category" onchange="this.form.submit()" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <option value="">All categories</option>
                @foreach (['MARKETING' => 'Marketing', 'UTILITY' => 'Utility', 'AUTHENTICATION' => 'Authentication'] as $k => $v)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>

        <div class="p-4 space-y-3 max-h-[70vh] overflow-y-auto">
            @forelse ($templates as $t)
                @php $isSel = $selected?->id === $t->id; $fmt = $t->headerFormat(); @endphp
                <a href="{{ $query(['t' => $t->id]) }}" class="block rounded-2xl border p-4 transition {{ $isSel ? 'border-brand-500 bg-brand-50/60 ring-2 ring-brand-100' : 'border-slate-200 hover:border-brand-300' }}">
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 grid place-items-center shrink-0"><x-icon :name="in_array($fmt, ['IMAGE', 'VIDEO', 'DOCUMENT']) ? 'image' : 'text'" class="w-5 h-5" /></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-900">{{ $t->name }}</span>
                                <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-brand-50 text-brand-700">{{ $t->category }}</span>
                                <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $fmt ?? 'TEXT' }}</span>
                                <span class="ml-auto text-xs font-semibold px-2 py-0.5 rounded-full {{ $badge($t->status) }}">{{ $t->status === 'APPROVED' ? '✓ ' : '' }}{{ $t->status }}</span>
                            </div>
                            <p class="text-sm text-slate-600 mt-2 line-clamp-2">{{ $t->bodyText() }}</p>
                            @if ($t->rejected_reason)<p class="text-xs text-red-600 mt-1">Reason: {{ str_replace('_', ' ', $t->rejected_reason) }}</p>@endif
                            <div class="text-xs text-slate-400 mt-2 pt-2 border-t border-slate-100">Language: {{ $langs[$t->language] ?? $t->language }} · Updated {{ $t->updated_at->format('d M Y, h:i A') }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="text-center py-12 text-slate-500 text-sm">No templates here. Click <b>Sync templates</b> or create a new one.</div>
            @endforelse
        </div>
    </div>

    <div class="xl:col-span-2 xl:sticky xl:top-28 self-start bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div class="font-semibold">Template details</div>
            @if ($selected)
                <div class="flex gap-2">
                    @if ($selected->status === 'APPROVED')
                        <a href="{{ route('campaigns.create', ['template' => $selected->id]) }}" title="Use in campaign" class="w-9 h-9 rounded-lg bg-brand-50 text-brand-600 grid place-items-center hover:bg-brand-100"><x-icon name="send" class="w-4 h-4" /></a>
                    @endif
                    <form method="POST" action="{{ route('templates.destroy', $selected) }}" onsubmit="return confirm('Delete this template in Meta too?')">@csrf @method('DELETE')
                        <button title="Delete" class="w-9 h-9 rounded-lg bg-red-50 text-red-600 grid place-items-center hover:bg-red-100"><x-icon name="trash" class="w-4 h-4" /></button></form>
                </div>
            @endif
        </div>
        @if ($selected)
            <div class="p-5">
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="font-semibold">{{ $selected->name }}</div>
                    <div class="text-xs text-slate-500 mt-1">{{ $selected->category }} · {{ $langs[$selected->language] ?? $selected->language }} · {{ $selected->whatsappAccount?->display_phone_number }}</div>
                    <div class="text-[11px] font-semibold uppercase tracking-widest text-slate-400 mt-4">Variables</div>
                    @if ($selected->bodyVariableCount() || $selected->headerVariableCount())
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            @for ($i = 1; $i <= $selected->bodyVariableCount(); $i++)<code class="text-xs px-2 py-0.5 rounded bg-brand-50 text-brand-700">{{ '{' }}{{ '{'.$i.'}' }}}</code>@endfor
                        </div>
                    @else
                        <div class="text-sm text-slate-500 mt-1">No variables used</div>
                    @endif
                    @if ($selected->rejected_reason)
                        <div class="mt-4 rounded-lg bg-red-50 text-red-700 text-sm p-3">Meta rejected this template: <b>{{ str_replace('_', ' ', $selected->rejected_reason) }}</b>. Edit the wording and submit it again as a new template.</div>
                    @endif
                </div>
                <div class="mt-5">
                    <x-phone-preview :name="$selected->whatsappAccount?->verified_name ?? 'Your Business'" :number="$selected->whatsappAccount?->display_phone_number">
                        <x-wa-bubble :header="$selected->component('HEADER')['text'] ?? null" :header-format="$selected->headerFormat()"
                                     :body="$selected->bodyText()" :footer="$selected->component('FOOTER')['text'] ?? null"
                                     :buttons="$selected->component('BUTTONS')['buttons'] ?? []" />
                    </x-phone-preview>
                </div>
            </div>
        @else
            <div class="p-10 text-center text-sm text-slate-500">Select a template to see its details and preview.</div>
        @endif
    </div>
</div>
@endif
@endsection
