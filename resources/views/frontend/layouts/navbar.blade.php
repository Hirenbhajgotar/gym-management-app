<nav x-data="{ mobileMenuOpen: false }" class="fixed top-0 w-full z-50 bg-black/20 backdrop-blur-md border-b border-white/10">
    <div class="container mx-auto px-6 py-4">
        <div class="flex justify-between items-center">
            <!-- Logo -->
            <div class="text-2xl font-bold bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                <a href="{{ route('home') }}">
                    <x-app-logo-icon variant="full" />
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex space-x-8">
                <a href="{{ route('home') }}" class="text-gray-300 hover:text-purple-400 transition-colors duration-300">Home</a>
                <a href="{{ route('home') }}#features" class="text-gray-300 hover:text-purple-400 transition-colors duration-300">Features</a>
                <a href="{{ route('home') }}#pricing" class="text-gray-300 hover:text-purple-400 transition-colors duration-300">Pricing</a>
                <a href="{{ route('home') }}#contact" class="text-gray-300 hover:text-purple-400 transition-colors duration-300">Contact</a>
            </div>

            <!-- Desktop Right Side (Auth Buttons) -->
            <div class="hidden md:flex space-x-4 items-center">
                @auth
                    <div class="flex space-x-3 items-center">
                        <a href="{{ route('profile') }}"
                            class="px-4 py-2 text-purple-400 border border-purple-400 rounded-lg hover:bg-purple-400 hover:text-white transition-all duration-300 text-sm font-semibold">
                            Profile
                        </a>
                    </div>
                @endauth
                @guest
                    <a href="{{ route('login') }}"
                        class="px-4 py-2 text-purple-400 border border-purple-400 rounded-lg hover:bg-purple-400 hover:text-white transition-all duration-300 text-sm font-semibold">
                        Login
                    </a>
                    <a href="{{ route('gym.register') }}"
                        class="px-6 py-2 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 transform hover:scale-105 text-white font-semibold shadow-lg shadow-purple-500/30">
                        Start Free Trial
                    </a>
                @endguest
                <a href="{{ route('request-demo') }}"
                    class="px-6 py-2 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 transform hover:scale-105 text-white font-semibold shadow-lg shadow-purple-500/30">
                        Request a Demo
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden flex items-center">
                <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="text-gray-300 hover:text-white focus:outline-none p-2 rounded-md transition-colors" aria-label="Toggle menu">
                    <svg x-show="!mobileMenuOpen" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
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
                <a href="{{ route('home') }}" class="text-lg text-gray-300 hover:text-purple-400 transition-colors font-medium">Home</a>
                <a href="{{ route('home') }}#features" class="text-lg text-gray-300 hover:text-purple-400 transition-colors font-medium">Features</a>
                <a href="{{ route('home') }}#pricing" class="text-lg text-gray-300 hover:text-purple-400 transition-colors font-medium">Pricing</a>
                <a href="{{ route('home') }}#contact" class="text-lg text-gray-300 hover:text-purple-400 transition-colors font-medium">Contact</a>
            </div>

            <div class="border-t border-white/10 pt-6 flex flex-col space-y-4">
                @auth
                    <a href="{{ route('profile') }}" class="block w-full text-center px-4 py-3 text-purple-400 border border-purple-400 rounded-xl hover:bg-purple-400 hover:text-white transition-all font-semibold">
                        Profile
                    </a>
                @endauth

                @guest
                    <a href="{{ route('login') }}" class="block w-full text-center px-4 py-3 text-purple-400 border border-purple-400 rounded-xl hover:bg-purple-400 hover:text-white transition-all font-semibold">
                        Login
                    </a>
                    <a href="{{ route('gym.register') }}" class="block w-full text-center px-6 py-3 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-xl hover:from-purple-600 hover:to-cyan-600 transition-all text-white font-semibold shadow-lg shadow-purple-500/30">
                        Start Free Trial
                    </a>
                @endguest
                <a href="{{ route('request-demo') }}" class="block w-full text-center px-6 py-3 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-xl hover:from-purple-600 hover:to-cyan-600 transition-all text-white font-semibold shadow-lg shadow-purple-500/30">
                    Request a Demo
                </a>
            </div>
        </div>
    </div>
</nav>
