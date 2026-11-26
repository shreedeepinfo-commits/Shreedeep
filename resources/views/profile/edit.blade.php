@extends('layouts.app', ['title' => 'My Profile'])

@section('content')
<x-page-header title="My Profile" subtitle="Your name, email and password." />

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 max-w-5xl">
    <form method="POST" action="{{ route('profile.update') }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        @csrf @method('PUT')
        <div class="flex items-center gap-4 mb-6">
            <span class="w-14 h-14 rounded-full bg-brand-600 text-white grid place-items-center text-xl font-semibold uppercase">{{ mb_substr($user->name, 0, 1) }}</span>
            <div>
                <div class="font-semibold">{{ $user->name }}</div>
                <div class="text-sm text-slate-500">{{ \App\Support\CurrentWorkspace::ROLES[app(\App\Support\CurrentWorkspace::class)->role()] ?? '' }} · {{ $currentWorkspace?->name }}</div>
            </div>
        </div>
        <label class="block text-sm font-medium text-slate-600">Name</label>
        <input name="name" value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
        <label class="block text-sm font-medium text-slate-600 mt-4">Email</label>
        <input name="email" type="email" value="{{ old('email', $user->email) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
        <x-btn class="mt-5">Save profile</x-btn>
    </form>

    <form method="POST" action="{{ route('profile.password') }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        @csrf @method('PUT')
        <div class="font-semibold mb-4 flex items-center gap-2"><x-icon name="shield" class="w-5 h-5 text-brand-600" /> Change password</div>
        @foreach ([['current_password', 'Current password'], ['password', 'New password'], ['password_confirmation', 'Confirm new password']] as [$field, $label])
            <label class="block text-sm font-medium text-slate-600 {{ $loop->first ? '' : 'mt-4' }}">{{ $label }}</label>
            <input name="{{ $field }}" type="password" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
        @endforeach
        <x-btn class="mt-5">Change password</x-btn>
        <p class="text-xs text-slate-400 mt-3">Two-factor login is coming with the security update.</p>
    </form>
</div>
@endsection
