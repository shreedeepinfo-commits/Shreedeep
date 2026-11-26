@props(['label', 'value', 'sub' => null, 'icon' => 'chart', 'tone' => 'brand', 'href' => null])
@php
    $tones = [
        'brand' => ['bg-brand-50 text-brand-600', 'text-brand-600', 'border-b-brand-500'],
        'green' => ['bg-emerald-50 text-emerald-600', 'text-emerald-600', 'border-b-emerald-500'],
        'amber' => ['bg-amber-50 text-amber-600', 'text-amber-600', 'border-b-amber-500'],
        'red' => ['bg-red-50 text-red-600', 'text-red-600', 'border-b-red-500'],
        'sky' => ['bg-sky-50 text-sky-600', 'text-sky-600', 'border-b-sky-500'],
        'slate' => ['bg-slate-100 text-slate-600', 'text-slate-500', 'border-b-slate-300'],
    ][$tone];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="flex items-center gap-4 bg-white rounded-2xl border border-slate-200 border-b-4 {{ $tones[2] }} shadow-sm p-5 {{ $href ? 'hover:shadow-md transition' : '' }}">
    <span class="w-12 h-12 rounded-xl grid place-items-center shrink-0 {{ $tones[0] }}"><x-icon :name="$icon" class="w-6 h-6" /></span>
    <span class="min-w-0">
        <span class="block text-2xl font-bold text-slate-900">{{ $value }}</span>
        <span class="block text-sm text-slate-600">{{ $label }}</span>
        @if ($sub)<span class="block text-xs font-medium {{ $tones[1] }}">{{ $sub }}</span>@endif
    </span>
</{{ $tag }}>
