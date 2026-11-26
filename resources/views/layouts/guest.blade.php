<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <a href="/" class="block text-center text-2xl font-bold text-emerald-700 mb-6">{{ config('app.name') }}</a>
        <div class="bg-white rounded-xl shadow p-8">
            @yield('content')
        </div>
    </div>
</body>
</html>
