<nav x-data="{ mobileMenuOpen: false, scrolled: false }" 
    @scroll.window.passive="scrolled = window.scrollY > 20"
    @keydown.escape.window="mobileMenuOpen = false"
    :class="scrolled ? 'bg-surface/90 backdrop-blur-xl border-b border-outline-variant/30 shadow-2xl py-3.5' : 'bg-surface/40 backdrop-blur-md border-b border-white/5 py-4 lg:py-5'"
    class="fixed top-0 w-full z-50 transition-all duration-300">
    <div class="container mx-auto px-4 sm:px-6 max-w-7xl">
        <div class="flex justify-between items-center">
            <!-- Brand Logo -->
            <a href="{{ route('home') }}" class="group flex items-center gap-2">
                <div class="flex text-xl sm:text-2xl lg:text-3xl font-black italic text-primary tracking-tighter font-headline items-center gap-1.5 sm:gap-2 uppercase">
                    <span class="material-symbols-outlined filled text-2xl sm:text-3xl lg:text-4xl text-primary group-hover:rotate-12 transition-transform duration-300">all_inclusive</span>
                    <span class="text-on-surface">Flux<span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-primary-container">Fitness</span></span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex items-center space-x-1 lg:space-x-2 bg-surface-container-low/60 border border-outline-variant/20 rounded-full px-5 py-1.5 backdrop-blur-md">
                <a href="{{ route('home') }}" class="px-4 py-1.5 rounded-full text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200">Home</a>
                <a href="{{ route('home') }}#features" class="px-4 py-1.5 rounded-full text-sm font-medium text-on-surface-variant hover:text-on-surface hover:text-primary transition-colors duration-200">Features</a>
                <a href="{{ route('home') }}#solutions" class="px-4 py-1.5 rounded-full text-sm font-medium text-on-surface-variant hover:text-on-surface hover:text-primary transition-colors duration-200">Solutions</a>
                <a href="{{ route('home') }}#pricing" class="px-4 py-1.5 rounded-full text-sm font-medium text-on-surface-variant hover:text-on-surface hover:text-primary transition-colors duration-200">Pricing</a>
                <a href="{{ route('home') }}#faq" class="px-4 py-1.5 rounded-full text-sm font-medium text-on-surface-variant hover:text-on-surface hover:text-primary transition-colors duration-200">FAQ</a>
                <a href="{{ route('home') }}#contact" class="px-4 py-1.5 rounded-full text-sm font-medium text-on-surface-variant hover:text-on-surface hover:text-primary transition-colors duration-200">Contact</a>
            </div>

            <!-- Desktop Right Side (Auth Buttons) -->
            <div class="hidden md:flex items-center space-x-3">
                @auth
                    <a href="{{ route('profile') }}"
                        class="px-4 py-2 bg-surface-container-high hover:bg-surface-container-highest text-primary border border-outline-variant/40 rounded-xl transition-all duration-300 text-sm font-semibold flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">account_circle</span>
                        Profile
                    </a>
                @endauth
                @guest
                    <a href="{{ route('login') }}"
                        class="px-4 py-2 text-on-surface hover:text-primary text-sm font-semibold tracking-wide transition-colors">
                        Log In
                    </a>
                    <a href="{{ route('gym.register') }}"
                        class="px-5 py-2.5 bg-primary-container text-on-primary-fixed font-headline font-bold text-sm rounded-xl shadow-lg shadow-primary/10 hover:brightness-110 active:scale-[0.98] transition-all uppercase tracking-wide cursor-pointer flex items-center gap-2">
                        <span>Free Trial</span>
                        <span class="material-symbols-outlined text-base">arrow_forward</span>
                    </a>
                    <a href="{{ route('request-demo') }}"
                        class="px-4 py-2.5 bg-surface-container-high text-on-surface border border-outline-variant/40 font-headline font-semibold text-sm rounded-xl hover:bg-surface-container-highest hover:border-primary/50 transition-all">
                        Demo
                    </a>
                @endguest
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden flex items-center">
                <button @click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="String(mobileMenuOpen)" type="button" class="text-on-surface hover:text-primary focus:outline-none p-2 rounded-xl bg-surface-container-low border border-outline-variant/30 transition-colors" aria-label="Toggle menu">
                    <span class="material-symbols-outlined text-2xl" x-show="!mobileMenuOpen">menu</span>
                    <span class="material-symbols-outlined text-2xl" x-show="mobileMenuOpen" x-cloak>close</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Dropdown -->
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         x-cloak
         class="md:hidden bg-surface-container-low/95 backdrop-blur-2xl border-b border-outline-variant/30 absolute w-full left-0 top-full shadow-2xl">
        <div class="px-6 py-6 space-y-4">
            <div class="flex flex-col space-y-3">
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}" class="text-base text-on-surface hover:text-primary transition-colors font-medium py-1">Home</a>
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}#features" class="text-base text-on-surface-variant hover:text-primary transition-colors font-medium py-1">Features</a>
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}#solutions" class="text-base text-on-surface-variant hover:text-primary transition-colors font-medium py-1">Solutions</a>
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}#pricing" class="text-base text-on-surface-variant hover:text-primary transition-colors font-medium py-1">Pricing</a>
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}#faq" class="text-base text-on-surface-variant hover:text-primary transition-colors font-medium py-1">FAQ</a>
                <a @click="mobileMenuOpen = false" href="{{ route('home') }}#contact" class="text-base text-on-surface-variant hover:text-primary transition-colors font-medium py-1">Contact</a>
            </div>

            <div class="border-t border-outline-variant/20 pt-5 flex flex-col space-y-3">
                @auth
                    <a href="{{ route('profile') }}" class="block w-full text-center px-4 py-3 bg-surface-container-high text-primary border border-primary/30 rounded-xl font-semibold">
                        Profile
                    </a>
                @endauth

                @guest
                    <a href="{{ route('login') }}" class="block w-full text-center px-4 py-3 bg-surface-container-high text-on-surface border border-outline-variant/40 rounded-xl font-semibold hover:border-primary transition-colors">
                        Log In
                    </a>
                    <a href="{{ route('gym.register') }}" class="block w-full text-center px-6 py-3.5 bg-primary-container text-on-primary-fixed font-headline font-bold text-base rounded-xl shadow-lg shadow-primary/20 uppercase tracking-wide">
                        Start Free Trial
                    </a>
                    <a href="{{ route('request-demo') }}" class="block w-full text-center px-6 py-3 bg-surface-container-highest text-on-surface border border-outline-variant/40 rounded-xl font-semibold">
                        Request a Demo
                    </a>
                @endguest
            </div>
        </div>
    </div>
</nav>
