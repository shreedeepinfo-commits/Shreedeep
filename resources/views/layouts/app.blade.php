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
        '' => [
            ['Dashboard', 'dashboard', 'home'],
            ['Live Chat', 'inbox.index', 'chat'],
        ],
        'Messaging' => [
            ['Campaigns', 'campaigns.index', 'megaphone'],
            ['Templates', 'templates.index', 'document'],
            ['Contacts & CRM', 'contacts.index', 'users'],
        ],
        'Automation' => [
            ['Chatbot Builder', 'flows.index', 'bot'],
            ['Automations', 'automations.index', 'bolt'],
            ['WhatsApp Flows', ['soon', 'whatsapp-flows'], 'form'],
        ],
        'Developers' => [
            ['Integrations', 'integrations.index', 'plug'],
            ['API & Webhooks', 'developers.index', 'code'],
        ],
        'Business' => [
            ['Team Management', 'team.index', 'team'],
            ['Analytics', 'analytics.index', 'chart'],
            ['Subscription', 'subscription.index', 'card'],
            ['WhatsApp Settings', 'whatsapp.index', 'cog'],
            ['Help & Support', 'support.index', 'help'],
        ],
    ];
    $metaConnected = (bool) ($topBar['account'] ?? null);
    if (! ($isManager ?? false)) {
        // Agents: chat-focused menu.
        $agentRoutes = ['dashboard', 'inbox.index', 'contacts.index', 'support.index'];
        $nav = collect($nav)->map(fn ($items) => array_values(array_filter($items, fn ($i) => in_array($i[1], $agentRoutes, true))))->filter()->all();
    }
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
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-5">
            @foreach ($nav as $section => $items)
                <div>
                    @if ($section !== '')<div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-widest text-brand-400">{{ $section }}</div>@endif
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
                            @if ($route === 'inbox.index' && ($topBar['unread'] ?? 0) > 0)<span class="text-[11px] min-w-5 px-1.5 py-0.5 rounded-full bg-emerald-500 text-white font-semibold text-center">{{ $topBar['unread'] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <a href="{{ ($isManager ?? false) ? route('whatsapp.index') : route('inbox.index') }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 hover:bg-white/10">
            <span class="relative flex w-2.5 h-2.5">
                @if ($metaConnected)<span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60 animate-ping"></span>@endif
                <span class="relative inline-flex w-2.5 h-2.5 rounded-full {{ $metaConnected ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
            </span>
            <span class="text-xs leading-tight">
                <span class="block font-semibold text-white">{{ $metaConnected ? 'Meta Connected' : 'Meta not connected' }}</span>
                <span class="block text-brand-300">{{ $metaConnected ? $topBar['account']->display_phone_number : 'Connect your number' }}</span>
            </span>
        </a>
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
                    <a href="{{ ($isManager ?? false) ? route('whatsapp.index') : '#' }}" class="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 hover:border-brand-300">
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
                    <a href="{{ route('profile.edit') }}" title="My Profile" class="flex items-center gap-2 pl-2">
                        <span class="w-10 h-10 rounded-full bg-brand-600 text-white grid place-items-center font-semibold uppercase">{{ $initials }}</span>
                        <span class="hidden md:block text-sm font-medium">{{ Str::before($user->name, ' ') }}</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="p-4 lg:p-8">
            @if (session('impersonator_id'))
                <form method="POST" action="{{ route('admin.impersonate.stop') }}" class="mb-5 rounded-xl bg-violet-50 border border-violet-200 text-violet-800 px-4 py-3 text-sm flex flex-wrap items-center gap-3">
                    @csrf
                    <x-icon name="crown" class="w-5 h-5" />
                    <span class="flex-1">{{ session('impersonator_label', 'Super Admin') }} view: you are inside <b>{{ $currentWorkspace?->name }}</b>, signed in as its owner {{ auth()->user()->name }}.</span>
                    <button class="font-semibold underline">Back to {{ session('impersonator_label', 'Super Admin') }}</button>
                </form>
            @endif
            @if ($currentWorkspace ?? null)
                @php $daysLeft = $currentWorkspace->daysLeft(); @endphp
                @if ($currentWorkspace->isExpired())
                    <div class="mb-5 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm flex flex-wrap items-center gap-3">
                        <x-icon name="card" class="w-5 h-5" />
                        <span class="flex-1"><b>Your {{ $currentWorkspace->plan_ends_at ? 'plan' : 'free trial' }} has ended.</b> You can still see everything, but sending and changes are paused until you renew.</span>
                        @if ($isManager ?? false)<a href="{{ route('subscription.index') }}" class="font-semibold underline">Renew now</a>@endif
                    </div>
                @elseif ($daysLeft <= 7 && ! request()->routeIs('subscription.*'))
                    <div class="mb-5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm flex flex-wrap items-center gap-3">
                        <x-icon name="clock" class="w-5 h-5" />
                        <span class="flex-1">{{ $currentWorkspace->planName() }} ends in <b>{{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }}</b> ({{ $currentWorkspace->accessEndsAt()->format('d M Y') }}).</span>
                        @if ($isManager ?? false)<a href="{{ route('subscription.index') }}" class="font-semibold underline">{{ $currentWorkspace->onTrial() ? 'Choose a plan' : 'Renew' }}</a>@endif
                    </div>
                @endif
            @endif
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
