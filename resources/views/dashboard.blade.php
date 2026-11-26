@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
<h1 class="text-2xl font-semibold mb-6">Dashboard</h1>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @foreach (($stats ?? ['Connected numbers' => 0, 'Contacts' => 0, 'Messages sent' => 0]) as $label => $value)
        <div class="bg-white rounded-xl shadow p-5">
            <div class="text-sm text-slate-500">{{ $label }}</div>
            <div class="text-3xl font-bold mt-1">{{ number_format($value) }}</div>
        </div>
    @endforeach
</div>
@endsection
