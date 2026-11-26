@php
    [$cls, $label] = [
        'running' => ['bg-sky-50 text-sky-700', 'Sending'],
        'completed' => ['bg-emerald-50 text-emerald-700', 'Sent'],
        'paused' => ['bg-amber-50 text-amber-700', 'Paused'],
        'scheduled' => ['bg-violet-50 text-violet-700', 'Scheduled'],
        'cancelled' => ['bg-slate-100 text-slate-600', 'Cancelled'],
    ][$status] ?? ['bg-slate-100 text-slate-600', ucfirst($status)];
@endphp
<span class="inline-block text-xs font-semibold px-2.5 py-1 rounded-full {{ $cls }}">{{ $label }}</span>
