@extends('layouts.guest', ['title' => config('app.name')])

@section('content')
<h1 class="text-2xl font-semibold mb-2">WhatsApp bulk messaging, on the official Cloud API</h1>
<p class="text-slate-600 mb-6">Connect your own WhatsApp Business number in one click, upload contacts, and send approved template campaigns with full delivery reports.</p>
<div class="flex gap-3">
    <a href="{{ route('register') }}" class="flex-1 text-center rounded bg-brand-600 text-white py-2 font-semibold">Get started</a>
    <a href="{{ route('login') }}" class="flex-1 text-center rounded border border-brand-600 text-brand-700 py-2 font-semibold">Log in</a>
</div>
@endsection
