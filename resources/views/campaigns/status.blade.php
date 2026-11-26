@php
    $cls = ['running' => 'bg-sky-100 text-sky-800', 'completed' => 'bg-emerald-100 text-emerald-800', 'paused' => 'bg-amber-100 text-amber-800',
            'scheduled' => 'bg-violet-100 text-violet-800', 'cancelled' => 'bg-slate-200 text-slate-700'][$status] ?? 'bg-slate-100';
@endphp
<span class="text-xs px-2 py-0.5 rounded {{ $cls }}">{{ $status }}</span>
