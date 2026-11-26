@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
<h1 class="text-2xl font-semibold mb-6">Dashboard</h1>

@unless ($hasNumber ?? true)
    <div class="mb-6 rounded-xl bg-emerald-700 text-white p-6 flex items-center justify-between">
        <div>
            <div class="text-lg font-semibold">Connect your WhatsApp Business number</div>
            <div class="text-sm text-emerald-100">Takes 2 minutes with Meta's official signup. You keep ownership of your number.</div>
        </div>
        <a href="{{ route('whatsapp.index') }}" class="rounded bg-white text-emerald-800 px-4 py-2 font-semibold">Connect WhatsApp</a>
    </div>
@endunless

<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
    @foreach (($stats ?? []) as $label => $value)
        <div class="bg-white rounded-xl shadow p-5">
            <div class="text-sm text-slate-500">{{ $label }}</div>
            <div class="text-3xl font-bold mt-1">{{ number_format($value) }}</div>
        </div>
    @endforeach
</div>

@if (isset($campaigns) && $campaigns->isNotEmpty())
    <h2 class="text-lg font-semibold mt-8 mb-3">Recent campaigns</h2>
    <div class="bg-white rounded-xl shadow divide-y">
        @foreach ($campaigns as $c)
            <a href="{{ route('campaigns.show', $c) }}" class="flex justify-between px-5 py-3 text-sm hover:bg-slate-50">
                <span><b>{{ $c->name }}</b> <span class="text-slate-500 font-mono">{{ $c->template?->name }}</span></span>
                <span>{{ number_format($c->total) }} contacts · @include('campaigns.status', ['status' => $c->status])</span>
            </a>
        @endforeach
    </div>
@endif
@endsection
