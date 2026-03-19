<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'All-in-one Gym Management Software | Fluxfitness' }}</title>
    <meta name="description" content="{{ $seo['description'] ?? 'Fluxfitness - A complete gym management system with membership tracking, workout plans, class scheduling, trainer management, and more.' }}">
    <meta name="keywords" content="{{ $seo['keywords'] ?? 'gym management, fitness software, membership management, workout tracking, class scheduling' }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $title ?? 'All-in-one Gym Management Software | Fluxfitness' }}">
    <meta property="og:description" content="{{ $seo['description'] ?? 'FluxFitness - A complete gym management system with membership tracking, workout plans, class scheduling, trainer management, and more.' }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? 'All-in-one Gym Management Software | Fluxfitness' }}">
    <meta name="twitter:description" content="{{ $seo['description'] ?? 'FluxFitness - A complete gym management system with membership tracking, workout plans, class scheduling, trainer management, and more.' }}">
    <meta name="twitter:image" content="{{ asset('images/og-home.jpg') }}">

    <!-- JSON-LD Organization Schema -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Organization",
        "name": "Fluxfitness",
        "url": "{{ config('app.url') }}",
        "logo": "{{ asset('images/logo.png') }}",
        "description": "{{ $seo['description'] ?? 'FluxFitness - A complete gym management system with membership tracking, workout plans, class scheduling, trainer management, and more.' }}",
        "contactPoint": {
            "@@type": "ContactPoint",
            "telephone": "{{ config('app.contact_phone') }}",
            "contactType": "customer service"
        }
    }
    </script>

    @stack('meta')

    <!-- Favicons -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('fluxfitness-icon.png') }}">

    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'fade-in': 'fadeIn 1s ease-out',
                        'scale-in': 'scaleIn 0.6s ease-out',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .gradient-bg {
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 25%, #2d1b4e 50%, #1a1a3e 75%, #0f0f23 100%);
        }

        .feature-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .scroll-smooth {
            scroll-behavior: smooth;
        }
    </style>
</head>

<body class="gradient-bg text-white scroll-smooth">
    <!-- Navigation -->
    @include('frontend.layouts.navbar')

    {{ $slot }}

    <!-- Footer -->
    @include('frontend.layouts.footer')
</body>

</html>
