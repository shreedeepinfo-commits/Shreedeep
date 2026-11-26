<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('brand.product') }} · {{ config('brand.name') }}</title>
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
@php
    $nav = [
        'Main' => [
            ['Dashboard', 'dashboard', 'home'],
            ['Campaigns', 'campaigns.index', 'megaphone'],
            ['Templates', 'templates.index', 'document'],
        ],
        'Engage' => [
            ['Contacts & Groups', 'contacts.index', 'users'],
            ['Live Chat', 'inbox.index', 'chat'],
            ['WhatsApp Numbers', 'whatsapp.index', 'whatsapp'],
        ],
        'Automate' => [
            ['Flow Builder', ['soon', 'flows'], 'flow'],
            ['Drip Campaign', ['soon', 'drip'], 'clock'],
            ['Bot Responder', ['soon', 'bots'], 'bot'],
        ],
        'Account' => [
            ['User Management', ['soon', 'team'], 'team'],
            ['My Profile', ['soon', 'profile'], 'user'],
        ],
    ];
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('');
@endphp
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-brand-950 text-brand-100 flex flex-col -translate-x-full lg:translate-x-0 transition-transform">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-5 h-20 border-b border-white/10">
            <img src="{{ config('brand.logo_url') }}" alt="" class="w-11 h-11 rounded-full bg-white object-contain p-0.5" onerror="this.style.display='none'">
            <div class="leading-tight">
                <div class="text-white font-bold tracking-wide uppercase text-sm">{{ config('brand.name') }}</div>
                <div class="text-brand-300 text-xs">{{ config('brand.product') }}</div>
            </div>
        </a>
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-6">
            @foreach ($nav as $section => $items)
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-widest text-brand-400">{{ $section }}</div>
                    @foreach ($items as [$label, $route, $icon])
                        @php
                            $soon = is_array($route);
                            $url = $soon ? route($route[0], $route[1]) : route($route);
                            $active = $soon ? request()->is('soon/'.$route[1]) : request()->routeIs(Str::before($route, '.').'*');
                        @endphp
                        <a href="{{ $url }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm mb-0.5 transition
                            {{ $active ? 'bg-brand-600 text-white font-semibold shadow-lg shadow-brand-900/50' : 'hover:bg-white/5 hover:text-white' }}">
                            <x-icon :name="$icon" />
                            <span class="flex-1">{{ $label }}</span>
                            @if ($soon)<span class="text-[10px] px-1.5 py-0.5 rounded bg-white/10 text-brand-200">Soon</span>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="p-3 border-t border-white/10">
            @csrf
            <button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm hover:bg-white/5 hover:text-white"><x-icon name="logout" /> Log out</button>
        </form>
    </aside>

    <div class="flex-1 lg:ml-64 min-w-0">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-slate-200">
            <div class="flex items-center gap-3 px-4 lg:px-8 h-20">
                <button class="lg:hidden p-2 rounded hover:bg-slate-100" onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')"><x-icon name="menu" /></button>
                <div class="min-w-0">
                    <div class="text-lg font-semibold text-slate-900 truncate">{{ $title ?? 'Dashboard' }}</div>
                    <div class="text-xs text-slate-500">{{ now()->format('l, d M Y') }}</div>
                </div>

                <div class="ml-auto hidden xl:flex items-center gap-2">
                    @foreach ([['Marketing', $topBar['marketing'] ?? 0, 'send', 'text-brand-600'], ['Utility', $topBar['utility'] ?? 0, 'chat', 'text-sky-600'], ['Service', $topBar['service'] ?? 0, 'chat', 'text-emerald-600']] as [$label, $count, $icon, $color])
                        <div class="px-3 py-1.5 rounded-xl border border-slate-200" title="{{ $label }} messages sent this month">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</div>
                            <div class="flex items-center gap-1 font-semibold {{ $color }}"><x-icon :name="$icon" class="w-4 h-4" /> {{ number_format($count) }}</div>
                        </div>
                    @endforeach
                    <a href="{{ route('whatsapp.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 hover:border-brand-300">
                        <span class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 grid place-items-center"><x-icon name="whatsapp" class="w-4 h-4" /></span>
                        <span>
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">WA Number</span>
                            <span class="block font-semibold text-sm">{{ $topBar['account']?->display_phone_number ?? 'Not connected' }}</span>
                        </span>
                    </a>
                </div>

                <div class="ml-auto xl:ml-2 flex items-center gap-2">
                    @if (($topBar['workspaces'] ?? collect())->count() > 1)
                        <form method="POST" action="{{ route('workspaces.switch') }}">
                            @csrf
                            <select name="workspace_id" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-xl px-3 py-2 max-w-[12rem]">
                                @foreach ($topBar['workspaces'] as $w)<option value="{{ $w->id }}" @selected($w->id === $currentWorkspace?->id)>{{ $w->name }}</option>@endforeach
                            </select>
                        </form>
                    @else
                        <div class="hidden md:block px-3 py-1.5 rounded-xl border border-slate-200">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Business</div>
                            <div class="font-semibold text-sm truncate max-w-[12rem]">{{ $currentWorkspace?->name }}</div>
                        </div>
                    @endif
                    <div class="flex items-center gap-2 pl-2">
                        <span class="w-10 h-10 rounded-full bg-brand-600 text-white grid place-items-center font-semibold uppercase">{{ $initials }}</span>
                        <span class="hidden md:block text-sm font-medium">{{ Str::before($user->name, ' ') }}</span>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-4 lg:p-8">
            @if (session('status'))
                <div class="mb-5 rounded-xl bg-brand-50 border border-brand-100 text-brand-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-xl bg-red-50 border border-red-100 text-red-800 px-4 py-3 text-sm">
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
