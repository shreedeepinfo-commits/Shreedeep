<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>Account suspended · {{ config('brand.name') }}</title>
</head>
<body class="min-h-screen bg-slate-50 grid place-items-center p-6">
    <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center">
        <span class="w-14 h-14 mx-auto rounded-2xl bg-red-50 text-red-600 grid place-items-center"><x-icon name="bolt" class="w-7 h-7" /></span>
        <h1 class="text-xl font-bold mt-4">This account is paused</h1>
        <p class="text-sm text-slate-500 mt-2">{{ $workspace->name }} has been suspended. Please contact {{ config('brand.name') }} support to turn it back on.</p>
        <div class="flex justify-center gap-2 mt-6">
            @if (session('impersonator_id'))
                <form method="POST" action="{{ route('admin.impersonate.stop') }}">@csrf<x-btn>Back to Super Admin</x-btn></form>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<x-btn variant="outline">Log out</x-btn></form>
        </div>
    </div>
</body>
</html>
