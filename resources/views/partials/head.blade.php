<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ config('brand.logo_url') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
    // Shree Deep Infotech brand: primary #0B4E99 (from shreedeepinfotech.com), blue and white.
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: { sans: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                colors: {
                    brand: {
                        50: '#eef5fc', 100: '#d6e6f7', 200: '#aecdef', 300: '#7aaee3', 400: '#3f86d1',
                        500: '#1a69bd', 600: '#0B4E99', 700: '#0a437f', 800: '#0b3866', 900: '#0a2a4d', 950: '#061a31',
                    },
                },
            },
        },
    };
</script>
