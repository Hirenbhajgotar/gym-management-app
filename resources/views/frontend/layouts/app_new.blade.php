<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Fluxfitness | Gym Software | CRM, Billing, Member Tracking' }}</title>
    <meta name="description" content="{{ $seo['description'] ?? 'Fluxfitness - A complete gym management system with membership tracking, workout plans, class scheduling, trainer management, and more.' }}">
    <meta name="keywords" content="{{ $seo['keywords'] ?? 'gym management, fitness software, membership management, workout tracking, class scheduling' }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- hreflang for international SEO -->
    <link rel="alternate" hreflang="en-in" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">

    <!-- Preload critical images for LCP -->
    <link rel="preload" as="image" href="{{ asset('images/banner.webp') }}" fetchpriority="high">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $title ?? 'Fluxfitness | Gym Software | CRM, Billing, Member Tracking' }}">
    <meta property="og:description" content="{{ $seo['description'] ?? 'FluxFitness - A complete gym management system with membership tracking, workout plans, class scheduling, trainer management, and more.' }}">
    <meta property="og:image" content="{{ asset('images/banner.webp') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="600">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? 'Fluxfitness | Gym Software | CRM, Billing, Member Tracking' }}">
    <meta name="twitter:description" content="{{ $seo['description'] ?? 'FluxFitness - A complete gym management system with membership tracking, workout plans, class scheduling, trainer management, and more.' }}">
    <meta name="twitter:image" content="{{ asset('images/banner.webp') }}">
    <meta name="twitter:image:width" content="1200">
    <meta name="twitter:image:height" content="600">

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

    <!-- JSON-LD SoftwareApplication Schema -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SoftwareApplication",
        "name": "Fluxfitness",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "description": "All-in-one gym management software with membership tracking, billing, attendance, and CRM.",
        "offers": {
            "@@type": "Offer",
            "price": "0",
            "priceCurrency": "INR"
        },
        "aggregateRating": {
            "@@type": "AggregateRating",
            "ratingValue": "4.8",
            "ratingCount": "256"
        }
    }
    </script>

    <!-- JSON-LD BreadcrumbList Schema -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "{{ config('app.url') }}"
            },
            {
                "@@type": "ListItem",
                "position": 2,
                "name": "Features",
                "item": "{{ config('app.url') }}#features"
            },
            {
                "@@type": "ListItem",
                "position": 3,
                "name": "Pricing",
                "item": "{{ config('app.url') }}#pricing"
            },
            {
                "@@type": "ListItem",
                "position": 4,
                "name": "Contact",
                "item": "{{ config('app.url') }}#contact"
            }
        ]
    }
    </script>

    <!-- JSON-LD LocalBusiness Schema for India -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "LocalBusiness",
        "name": "Fluxfitness",
        "description": "Leading gym management software in India for fitness centers, gyms, and fitness entrepreneurs.",
        "url": "{{ config('app.url') }}",
        "telephone": "{{ config('app.contact_phone') }}",
        "email": "{{ config('app.contact_email') }}",
        "areaServed": {
            "@@type": "Country",
            "name": "India"
        },
        "serviceType": "Gym Management Software",
        "priceRange": "$$"
    }
    </script>

    @stack('meta')

    <!-- Favicons -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('fluxfitness-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>

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
