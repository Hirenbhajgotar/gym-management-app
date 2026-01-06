<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicons -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('fluxfitness-icon.png') }}">

    <title>{{ $title ?? 'Page Title' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Styles -->
    @livewireStyles
    @filamentStyles
    @vite(['resources/css/app.css'])

    <style>
        :root {
            --font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
        }

        [x-cloak] {
            display: none !important;
        }

        body {
            font-family: var(--font-family);
        }

        .text-4xl {
            font-size: var(--text-4xl);
            line-height: var(--tw-leading, var(--text-4xl--line-height));
        }

        .text-2xl {
            font-size: var(--text-2xl);
            line-height: var(--tw-leading, var(--text-2xl--line-height));
        }

        .text-sm {
            font-size: var(--text-sm);
            line-height: var(--tw-leading, var(--text-sm--line-height));
        }

        .text-lg {
            font-size: var(--text-lg);
            line-height: var(--tw-leading, var(--text-lg--line-height));
        }
    </style>
</head>

<body class="antialiased">
    <main class="min-h-screen bg-gray-50">
        {{ $slot }}
    </main>

    @livewireScripts
    @filamentScripts
</body>

</html>
