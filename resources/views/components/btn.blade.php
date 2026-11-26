@props(['href' => null, 'variant' => 'primary', 'icon' => null])
@php
    $cls = [
        'primary' => 'bg-brand-600 hover:bg-brand-700 text-white shadow-sm shadow-brand-600/30',
        'outline' => 'bg-white border border-slate-300 hover:border-brand-400 hover:text-brand-700 text-slate-700',
        'danger' => 'bg-white border border-red-200 text-red-700 hover:bg-red-50',
    ][$variant];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition $cls"]) }}>@if($icon)<x-icon :name="$icon" class="w-4 h-4" />@endif{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition $cls"]) }}>@if($icon)<x-icon :name="$icon" class="w-4 h-4" />@endif{{ $slot }}</button>
@endif
