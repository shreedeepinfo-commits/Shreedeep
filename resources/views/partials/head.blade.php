<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@if (config('brand.logo_url'))<link rel="icon" href="{{ config('brand.logo_url') }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
    // Brand colours: Shree Deep blue (#0B4E99) by default, or the reseller's colour on white-label panels.
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: { sans: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                colors: {
                    brand: @json(config('brand.palette', \App\Support\Branding::DEFAULT_PALETTE)),
                },
            },
        },
    };
</script>
