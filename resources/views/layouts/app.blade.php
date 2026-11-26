<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="flex min-h-screen">
    <aside class="w-60 bg-emerald-800 text-emerald-50 flex flex-col">
        <div class="px-5 py-5 border-b border-emerald-700">
            <div class="text-lg font-bold">{{ config('app.name') }}</div>
            <div class="text-xs text-emerald-200 truncate">{{ $currentWorkspace?->name }}</div>
        </div>
        @php
            $nav = [
                ['Dashboard', 'dashboard'],
                ['WhatsApp Numbers', 'whatsapp.index'],
                ['Contacts', 'contacts.index'],
                ['Templates', 'templates.index'],
                ['Campaigns', 'campaigns.index'],
                ['Inbox', 'inbox.index'],
            ];
        @endphp
        <nav class="flex-1 py-3">
            @foreach ($nav as [$label, $route])
                @if (Route::has($route))
                    <a href="{{ route($route) }}"
                       class="block px-5 py-2 text-sm {{ request()->routeIs(Str::before($route, '.').'*') ? 'bg-emerald-900 font-semibold' : 'hover:bg-emerald-700' }}">{{ $label }}</a>
                @endif
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="px-5 py-4 border-t border-emerald-700">
            @csrf
            <div class="text-xs text-emerald-200 mb-2 truncate">{{ auth()->user()->email }}</div>
            <button class="text-sm underline">Log out</button>
        </form>
    </aside>
    <main class="flex-1 p-8">
        @if (session('status'))
            <div class="mb-4 rounded bg-emerald-100 text-emerald-900 px-4 py-3 text-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded bg-red-100 text-red-900 px-4 py-3 text-sm">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
