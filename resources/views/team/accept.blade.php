@extends('layouts.guest', ['title' => 'Join '.$invitation->workspace->name])

@section('content')
<div class="text-center mb-6">
    <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon name="team" class="w-7 h-7" /></div>
    <h1 class="text-xl font-semibold mt-3">Join {{ $invitation->workspace->name }}</h1>
    <p class="text-sm text-slate-500 mt-1">You are invited as <b>{{ \App\Support\CurrentWorkspace::ROLES[$invitation->role] ?? $invitation->role }}</b> ({{ $invitation->email }}).</p>
</div>

@if ($existing)
    @if (auth()->id() === $existing->id)
        <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
            @csrf
            <button class="w-full rounded-xl bg-brand-600 text-white py-2.5 font-semibold hover:bg-brand-700">Accept invitation</button>
        </form>
    @else
        <p class="text-sm text-center text-slate-600">You already have an account. <a class="text-brand-700 font-semibold underline" href="{{ route('login') }}">Log in as {{ $invitation->email }}</a>, then open this link again.</p>
    @endif
@else
    <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}" class="space-y-4">
        @csrf
        <label class="block">
            <span class="text-sm font-medium">Your name</span>
            <input name="name" value="{{ old('name', $invitation->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
            @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block">
            <span class="text-sm font-medium">Choose a password</span>
            <input name="password" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
            @error('password')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block">
            <span class="text-sm font-medium">Confirm password</span>
            <input name="password_confirmation" type="password" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <button class="w-full rounded-xl bg-brand-600 text-white py-2.5 font-semibold hover:bg-brand-700">Join the team</button>
    </form>
@endif
@endsection
