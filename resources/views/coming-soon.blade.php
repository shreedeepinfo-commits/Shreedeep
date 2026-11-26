@extends('layouts.app', ['title' => $title])

@section('content')
<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600 text-white p-8 lg:p-10 shadow-xl">
    <div class="absolute -right-16 -top-16 w-72 h-72 rounded-full bg-white/5"></div>
    <div class="absolute right-40 -bottom-24 w-56 h-56 rounded-full bg-white/5"></div>
    <div class="relative flex flex-col md:flex-row md:items-center gap-6">
        <div class="w-16 h-16 rounded-2xl bg-white/10 border border-white/15 grid place-items-center shrink-0"><x-icon :name="$icon" class="w-8 h-8" /></div>
        <div class="flex-1">
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider px-3 py-1 rounded-full bg-white/10 border border-white/15">Coming soon · {{ $phase }}</span>
            <h1 class="text-2xl lg:text-3xl font-bold mt-3">{{ $title }}</h1>
            <p class="text-brand-100 mt-1">{{ $description }}</p>
        </div>
    </div>
</section>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mt-6">
    <div class="font-semibold mb-4">What you will get</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @foreach ($features as $feature)
            <div class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <span class="w-7 h-7 rounded-full bg-brand-50 text-brand-600 grid place-items-center shrink-0"><x-icon name="check" class="w-4 h-4" /></span>
                <span class="text-sm text-slate-700">{{ $feature }}</span>
            </div>
        @endforeach
    </div>
</div>
@endsection
