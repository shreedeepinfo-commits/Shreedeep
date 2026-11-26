<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>{{ $title ?? config('brand.product') }} · {{ config('brand.name') }}</title>
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600 text-slate-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <a href="/" class="flex items-center justify-center gap-3 mb-6">
            <img src="{{ config('brand.logo_url') }}" alt="" class="w-12 h-12 rounded-full bg-white object-contain p-0.5" onerror="this.style.display='none'">
            <span class="text-white leading-tight">
                <span class="block font-bold uppercase tracking-wide">{{ config('brand.name') }}</span>
                <span class="block text-brand-200 text-sm">{{ config('brand.product') }}</span>
            </span>
        </a>
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            @yield('content')
        </div>
    </div>
</body>
</html>
