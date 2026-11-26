@extends('layouts.guest', ['title' => 'Create account'])

@section('content')
<h1 class="text-xl font-semibold mb-6">Create your account</h1>
<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf
    @foreach ([['name','Your name','text'],['company','Business name','text'],['email','Email','email'],['password','Password','password'],['password_confirmation','Confirm password','password']] as [$field, $label, $type])
        <label class="block">
            <span class="text-sm font-medium">{{ $label }}</span>
            <input name="{{ $field }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($field) }}" required
                   class="mt-1 w-full rounded border-slate-300 border px-3 py-2">
            @error($field)<span class="text-sm text-red-600">{{ $message }}</span>@enderror
        </label>
    @endforeach
    <button class="w-full rounded bg-emerald-600 text-white py-2 font-semibold hover:bg-emerald-700">Sign up</button>
</form>
<p class="mt-4 text-sm text-center">Already have an account? <a class="text-emerald-700 underline" href="{{ route('login') }}">Log in</a></p>
@endsection
