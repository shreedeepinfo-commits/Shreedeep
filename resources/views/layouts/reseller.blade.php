<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>{{ isset($title) ? $title.' · ' : '' }}Partner panel · {{ config('brand.name') }}</title>
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
@php
    $me = app('reseller');
    $nav = array_filter([
        ['Overview', 'reseller.dashboard', 'home'],
        ['Users / Customers', 'reseller.customers', 'users'],
        $me->can_create_resellers ? ['Sub-resellers', 'reseller.resellers', 'team'] : null,
        ['Wallet', 'reseller.wallet', 'card'],
        ['Brand & prices', 'reseller.settings', 'cog'],
    ]);
    $user = auth()->user();
@endphp
<div class="flex min-h-screen">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-brand-950 text-brand-100 flex flex-col -translate-x-full lg:translate-x-0 transition-transform">
        <a href="{{ route('reseller.dashboard') }}" class="flex items-center gap-3 px-5 h-20 border-b border-white/10">
            <img src="{{ config('brand.logo_url') }}" alt="" class="w-11 h-11 rounded-full bg-white object-contain p-0.5" onerror="this.style.display='none'">
            <div class="leading-tight">
                <div class="text-white font-bold tracking-wide uppercase text-sm">{{ config('brand.name') }}</div>
                <div class="text-brand-300 text-xs font-semibold">Partner panel</div>
            </div>
        </a>
        <nav class="flex-1 overflow-y-auto py-4 px-3">
            @foreach ($nav as [$label, $route, $icon])
                @php $active = $route === 'reseller.dashboard' ? request()->routeIs('reseller.dashboard') : request()->routeIs($route.'*'); @endphp
                <a href="{{ route($route) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm mb-0.5 transition {{ $active ? 'bg-brand-600 text-white font-semibold shadow-lg shadow-brand-900/50' : 'hover:bg-white/5 hover:text-white' }}">
                    <x-icon :name="$icon" /><span class="flex-1">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('my-panel') }}" class="px-3 pt-3 border-t border-white/10">
            @csrf
            <button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold bg-emerald-500 text-white hover:bg-emerald-600"><x-icon name="whatsapp" /> My WhatsApp panel</button>
        </form>
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
                    <a href="{{ route('reseller.wallet') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full border {{ $me->isLowBalance() ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}"><x-icon name="card" class="w-4 h-4" /> {{ $me->balanceLabel() }}</a>
                    <span class="w-10 h-10 rounded-full bg-brand-600 text-white grid place-items-center font-semibold uppercase">{{ mb_substr($user->name, 0, 1) }}</span>
                    <span class="hidden md:block text-sm font-medium">{{ $user->name }}</span>
                </div>
            </div>
        </header>
        <main class="p-4 lg:p-8">
            @if (session('impersonator_id'))
                <form method="POST" action="{{ route('admin.impersonate.stop') }}" class="mb-5 rounded-xl bg-violet-50 border border-violet-200 text-violet-800 px-4 py-3 text-sm flex flex-wrap items-center gap-3">
                    @csrf
                    <x-icon name="crown" class="w-5 h-5" />
                    <span class="flex-1">Super Admin view: you are inside the partner panel of <b>{{ $me->name }}</b>.</span>
                    <button class="font-semibold underline">Back to Super Admin</button>
                </form>
            @endif
            @if ($me->isLowBalance())
                <div class="mb-5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">Low wallet balance ({{ $me->balanceLabel() }}). Auto renewals stop when the wallet is empty. <a href="{{ route('reseller.wallet') }}" class="font-semibold underline">Recharge</a></div>
            @endif
            @if (session('status'))
                <div class="mb-5 rounded-xl bg-brand-50 border border-brand-100 text-brand-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            @include('partials.credentials')
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
