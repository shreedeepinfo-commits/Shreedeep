@extends('layouts.guest', ['title' => 'Log in'])

@section('content')
<h1 class="text-xl font-semibold mb-6">Log in</h1>
<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <label class="block">
        <span class="text-sm font-medium">Email</span>
        <input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded border-slate-300 border px-3 py-2">
        @error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
    </label>
    <label class="block">
        <span class="text-sm font-medium">Password</span>
        <input name="password" type="password" required class="mt-1 w-full rounded border-slate-300 border px-3 py-2">
    </label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
    <button class="w-full rounded bg-emerald-600 text-white py-2 font-semibold hover:bg-emerald-700">Log in</button>
</form>
<p class="mt-4 text-sm text-center">New here? <a class="text-emerald-700 underline" href="{{ route('register') }}">Create an account</a></p>
@endsection
