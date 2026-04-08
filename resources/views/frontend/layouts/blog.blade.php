<!DOCTYPE html>
<html lang="en">

<head>
    @php
        $appUrl = config('app.url');
        $appName = config('app.name');
        $contactPhone = config('app.contact_phone');
        $contactEmail = config('app.contact_email');
        $logoUrl = asset('logo.png');
        $seoDescription = $seo['description'] ?? 'FluxFitness - A complete gym management system with membership tracking, workout plans, class scheduling, trainer management, and more.';
    @endphp
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Gym Management Software for Indian Gyms | FluxFitness' }}</title>
    <meta name="title" content="{{ $title ?? 'Gym Management Software for Indian Gyms | FluxFitness' }}">
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="{{ $seo['keywords'] ?? 'gym management, fitness software, membership management, workout tracking, class scheduling' }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ strtolower(url()->current()) }}">

    <!-- hreflang for international SEO -->
    <link rel="alternate" hreflang="en-in" href="{{ strtolower(url()->current()) }}">
    <link rel="alternate" hreflang="x-default" href="{{ strtolower(url()->current()) }}">

    <!-- Preload critical images for LCP -->
    <link rel="preload" as="image" href="{{ asset('images/banner.webp') }}" fetchpriority="high">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $ogTitle ?? $title ?? 'Gym Management Software for Indian Gyms | FluxFitness' }}">
    <meta property="og:description" content="{{ $ogDescription ?? $seoDescription }}">
    <meta property="og:image" content="{{ $ogImage ?? asset('images/banner.webp') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="600">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ strtolower(url()->current()) }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? 'Gym Management Software for Indian Gyms | FluxFitness' }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ asset('images/banner.webp') }}">
    <meta name="twitter:image:width" content="1200">
    <meta name="twitter:image:height" content="600">

    <!-- JSON-LD Organization Schema -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Organization",
        "name": "Fluxfitness",
        "url": "{{ $appUrl }}",
        "logo": "{{ $logoUrl }}",
        "description": "{{ $seoDescription }}",
        "contactPoint": {
            "@@type": "ContactPoint",
            "telephone": "{{ $contactPhone }}",
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
        "description": "All-in-one gym management software for Indian gyms with membership tracking, billing, attendance, WhatsApp notifications and CRM.",
        "offers": {
            "@@type": "Offer",
            "price": "0",
            "priceCurrency": "INR"
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
                "item": "{{ $appUrl }}"
            },
            {
                "@@type": "ListItem",
                "position": 2,
                "name": "Features",
                "item": "{{ $appUrl }}#features"
            },
            {
                "@@type": "ListItem",
                "position": 3,
                "name": "Pricing",
                "item": "{{ $appUrl }}#pricing"
            },
            {
                "@@type": "ListItem",
                "position": 4,
                "name": "Contact",
                "item": "{{ $appUrl }}#contact"
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
        "description": "Leading gym management software in India for fitness centers and gyms.",
        "url": "{{ $appUrl }}",
        "telephone": "{{ $contactPhone }}",
        "email": "{{ $contactEmail }}",
        "areaServed": {
            "@@type": "Country",
            "name": "India"
        },
        "serviceType": "Gym Management Software",
        "priceRange": "₹₹"
    }
    </script>

    <!-- Favicons -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('fluxfitness-icon.png') }}">

    <!-- Preload fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @stack('meta')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=Lexend:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    @stack('css')
</head>

<body class="bg-surface text-on-surface font-sans antialiased">
    <!-- Header -->
    <header x-data="{ mobileMenuOpen: false }" class="fixed top-0 left-0 right-0 z-50 bg-surface/80 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl-- container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    {{-- <img src="{{ $logoUrl }}" alt="FluxFitness" class="h-10">
                    <span class="text-xl font-bold text-white">FluxFitness</span> --}}
                    <div class="text-2xl font-black italic text-[#f3ffca] tracking-tighter font-headline flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary" data-icon="all_inclusive">all_inclusive</span>
                        FLUXFITNESS
                    </div>
                </a>

                <div class="hidden md:flex items-center gap-8 font-['Lexend'] font-semibold tracking-tight">
                    <!-- Nav Links -->
                    <a href="#" class="text-on-surface-variant hover:text-white transition-colors">Features</a>
                    <a href="#" class="text-on-surface-variant hover:text-white transition-colors">Pricing</a>
                    <a href="{{ route('blog') }}" class="text-primary-container font-medium">Blog</a>
                    <a href="#" class="text-on-surface-variant hover:text-white transition-colors">Support</a>
                    <a href="{{ route('login') }}" class="text-on-surface-variant hover:text-white transition-colors hidden sm:block">Login</a>
                    <!-- CTA Button -->
                    <a href="{{ route('gym.register') }}" class="bg-primary-container text-on-primary-container px-6 py-2.5 rounded-full font-bold hover:opacity-80 transition-all duration-300 scale-95 active:scale-90">
                        Start Free Trial
                    </a>
                </div>
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden text-primary">
                    <svg x-show="!mobileMenuOpen" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            x-cloak
            class="md:hidden bg-gray-900/95 backdrop-blur-xl border-t border-white/10 absolute w-full left-0 top-full shadow-2xl">
            <div class="px-6 py-6 space-y-4">
                <div class="flex flex-col space-y-4">
                    <a href="#" class="text-on-surface-variant hover:text-white transition-colors">Features</a>
                    <a href="#" class="text-on-surface-variant hover:text-white transition-colors">Pricing</a>
                    <a href="#" class="text-on-surface-variant hover:text-white transition-colors">Support</a>
                    <a href="{{ route('blog') }}" class="text-primary-container font-medium">Blog</a>
                </div>

                <div class="border-t border-white/10 pt-6 flex flex-col space-y-4">
                    @auth
                        <a href="{{ route('profile') }}" class="text-center cursor-pointer px-10 py-4 border-2 border-primary text-primary font-black uppercase tracking-widest text-sm rounded-lg hover:bg-primary hover:text-on-primary transition-all active:scale-95">
                            Profile
                        </a>
                    @endauth

                    @guest
                        <a href="{{ route('login') }}" class="text-center cursor-pointer px-10 py-4 border-2 border-primary text-primary font-black uppercase tracking-widest text-sm rounded-lg hover:bg-primary hover:text-on-primary transition-all active:scale-95">
                            Login
                        </a>
                        <a href="{{ route('gym.register') }}" class="text-center bg-primary text-on-primary-container px-10 py-4 rounded font-bold hover:opacity-80 transition-all duration-300 active:scale-95">
                            Start Free Trial
                        </a>
                    @endguest
                    <a href="{{ route('request-demo') }}" class="text-center bg-primary text-on-primary-container px-10 py-4 rounded font-bold hover:opacity-80 transition-all duration-300 active:scale-95">
                        Request a Demo
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-24 pb-16">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-surface-container-low border-t border-white/5 py-16">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
                <!-- Brand -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <a href="{{ route('home') }}" class="flex items-center gap-2">
                            <div class="text-2xl font-black italic text-[#f3ffca] tracking-tighter font-headline flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary" data-icon="all_inclusive">all_inclusive</span>
                                FLUXFITNESS
                            </div>
                        </a>
                    </div>
                    <p class="text-on-surface-variant text-sm">
                        Continuous Fitness Evolution. The ultimate operating system for modern fitness entrepreneurs.
                    </p>
                </div>

                <!-- Product -->
                <div>
                    <h4 class="font-semibold text-white mb-4">Product</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-on-surface-variant hover:text-white transition-colors text-sm">Features</a></li>
                        <li><a href="#" class="text-on-surface-variant hover:text-white transition-colors text-sm">Pricing</a></li>
                        <li><a href="{{ route('blog') }}" class="text-on-surface-variant hover:text-white transition-colors text-sm">Blog</a></li>
                        <li><a href="#" class="text-on-surface-variant hover:text-white transition-colors text-sm">Support</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="font-semibold text-white mb-4">Legal</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-on-surface-variant hover:text-white transition-colors text-sm">Privacy</a></li>
                        <li><a href="#" class="text-on-surface-variant hover:text-white transition-colors text-sm">Terms</a></li>
                        <li><a href="#" class="text-on-surface-variant hover:text-white transition-colors text-sm">Security</a></li>
                        <li><a href="#" class="text-on-surface-variant hover:text-white transition-colors text-sm">Cookies</a></li>
                    </ul>
                </div>

                <!-- Social -->
                <div>
                    <h4 class="font-semibold text-white mb-4">Connect</h4>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 bg-surface-container-high rounded-lg flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-surface-variant transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-surface-container-high rounded-lg flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-surface-variant transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-surface-container-high rounded-lg flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-surface-variant transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-surface-container-high rounded-lg flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-surface-variant transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Copyright -->
            <div class="text-center pt-8 border-t border-white/5">
                <p class="text-[#484847] text-sm">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. Continuous Fitness Evolution.
                </p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
