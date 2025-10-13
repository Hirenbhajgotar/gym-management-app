<nav class="fixed top-0 w-full z-50 bg-black/20 backdrop-blur-md border-b border-white/10">
    <div class="container mx-auto px-6 py-4">
        <div class="flex justify-between items-center">
            <div class="text-2xl font-bold bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                <a href="{{ route('home') }}">
                    GymFlow
                </a>
            </div>
            <div class="hidden md:flex space-x-8">
                <a href="#home" class="hover:text-purple-400 transition-colors duration-300">Home</a>
                <a href="#features" class="hover:text-purple-400 transition-colors duration-300">Features</a>
                <a href="#pricing" class="hover:text-purple-400 transition-colors duration-300">Pricing</a>
                <a href="#contact" class="hover:text-purple-400 transition-colors duration-300">Contact</a>
            </div>
            <div class="flex space-x-4">
                @auth
                    @php
                        $userGyms = auth()->user()->gyms()->wherePivot('active', true)->get();
                        $defaultGym = $userGyms->first();
                    @endphp

                    <div class="flex space-x-3">
                        @auth
                            <a href="{{ route('profile') }}"
                                class="px-4 py-2 text-purple-400 border border-purple-400 rounded-lg hover:bg-purple-400 hover:text-white transition-all duration-300">
                                Profile
                            </a>
                        @endauth

                        {{-- @if (auth()->user()->hasRole('gym_owner') && $defaultGym)
                             <a href="{{ route('dashboard') }}"
                                 class="px-6 py-2 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 transform hover:scale-105">
                                 Dashboard
                             </a>
                         @elseif(auth()->user()->hasRole(['manager', 'receptionist']) && $defaultGym)
                             <a href="{{ route('filament.staff.pages.dashboard', ['tenant' => $defaultGym->slug]) }}"
                                 class="px-6 py-2 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 transform hover:scale-105">
                                 Staff Dashboard
                             </a>
                         @else
                             <a href="{{ route('dashboard') }}"
                                 class="px-6 py-2 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 transform hover:scale-105">
                                 Dashboard
                             </a>
                         @endif --}}
                    </div>
                @endauth
                @guest
                    {{-- <a href="{{ route('filament.admin.auth.login') }}" --}}
                    <a href="{{ route('login') }}"
                        class="px-4 py-2 text-purple-400 border border-purple-400 rounded-lg hover:bg-purple-400 hover:text-white transition-all duration-300">
                        Login
                    </a>
                    <a href="{{ route('gym.register') }}"
                        class="px-6 py-2 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 transform hover:scale-105">
                        Start Free Trial
                    </a>
                @endguest

            </div>
        </div>
    </div>
</nav>
