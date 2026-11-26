@props(['title', 'subtitle' => null])
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $title }}</h1>
        @if ($subtitle)<p class="text-slate-500 text-sm mt-1">{{ $subtitle }}</p>@endif
    </div>
    <div class="flex flex-wrap gap-2">{{ $slot }}</div>
</div>
