@extends('layouts.app', ['title' => $title])

@section('content')
<div class="max-w-xl mx-auto mt-10 bg-white rounded-2xl shadow-sm border border-slate-200 p-10 text-center">
    <div class="w-16 h-16 mx-auto rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon :name="$icon" class="w-8 h-8" /></div>
    <h1 class="text-2xl font-semibold mt-5">{{ $title }}</h1>
    <p class="text-slate-500 mt-2">{{ $description }}</p>
    <span class="inline-block mt-5 text-xs font-semibold uppercase tracking-wider px-3 py-1 rounded-full bg-brand-600 text-white">Coming soon</span>
</div>
@endsection
