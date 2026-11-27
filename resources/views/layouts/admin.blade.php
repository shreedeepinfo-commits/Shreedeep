<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>{{ isset($title) ? $title.' · ' : '' }}Super Admin · {{ config('brand.name') }}</title>
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
@php
    $nav = [
        ['Overview', 'admin.dashboard', 'home'],
        ['Customers', 'admin.customers', 'users'],
        ['Payments & invoices', 'admin.payments', 'card'],
        ['Plans', 'admin.plans', 'crown'],
    ];
    $user = auth()->user();
@endphp
<div class="flex min-h-screen">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-brand-950 text-brand-100 flex flex-col -translate-x-full lg:translate-x-0 transition-transform">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-5 h-20 border-b border-white/10">
            <img src="{{ config('brand.logo_url') }}" alt="" class="w-11 h-11 rounded-full bg-white object-contain p-0.5" onerror="this.style.display='none'">
            <div class="leading-tight">
                <div class="text-white font-bold tracking-wide uppercase text-sm">{{ config('brand.name') }}</div>
                <div class="text-amber-300 text-xs font-semibold">Super Admin</div>
            </div>
        </a>
        <nav class="flex-1 overflow-y-auto py-4 px-3">
            @foreach ($nav as [$label, $route, $icon])
                @php $active = $route === 'admin.dashboard' ? request()->routeIs('admin.dashboard') : request()->routeIs($route.'*'); @endphp
                <a href="{{ route($route) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm mb-0.5 transition {{ $active ? 'bg-brand-600 text-white font-semibold shadow-lg shadow-brand-900/50' : 'hover:bg-white/5 hover:text-white' }}">
                    <x-icon :name="$icon" /><span class="flex-1">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="p-3 border-t border-white/10">
            @csrf
            <button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm hover:bg-white/5 hover:text-white"><x-icon name="logout" /> Log out</button>
        </form>
    </aside>

    <div class="flex-1 lg:ml-64 min-w-0">
        <header class="sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-slate-200">
            <div class="flex items-center gap-3 px-4 lg:px-8 h-20">
                <button class="lg:hidden p-2 rounded hover:bg-slate-100" onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')"><x-icon name="menu" /></button>
                <div class="min-w-0">
                    <div class="text-lg font-semibold text-slate-900 truncate">{{ $title ?? 'Overview' }}</div>
                    <div class="text-xs text-slate-500">{{ now()->format('l, d M Y') }}</div>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <span class="hidden md:inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200"><x-icon name="crown" class="w-4 h-4" /> Platform owner</span>
                    <span class="w-10 h-10 rounded-full bg-brand-600 text-white grid place-items-center font-semibold uppercase">{{ mb_substr($user->name, 0, 1) }}</span>
                    <span class="hidden md:block text-sm font-medium">{{ $user->name }}</span>
                </div>
            </div>
        </header>
        <main class="p-4 lg:p-8">
            @if (session('status'))
                <div class="mb-5 rounded-xl bg-brand-50 border border-brand-100 text-brand-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
