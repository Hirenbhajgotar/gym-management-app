<div>
    <!-- Toast Messages -->
    @livewire('toast')

    <!-- Request Demo Section with Features-style Background -->
    <section class="min-h-screen py-16 relative overflow-hidden bg-gradient-to-b from-gray-950 via-gray-900 to-black pt-24">
        <!-- Animated Background Elements (same as features section) -->
        <div class="absolute inset-0 overflow-hidden">
            <!-- Rotating circles -->
            <div class="absolute top-20 right-10 w-40 h-40 bg-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-40 left-10 w-48 h-48 bg-cyan-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
            <div class="absolute top-1/2 left-1/4 w-32 h-32 bg-purple-600/15 rounded-full blur-2xl animate-float"></div>
            <div class="absolute top-1/3 right-1/4 w-44 h-44 bg-cyan-600/15 rounded-full blur-2xl animate-float" style="animation-delay: 2s;"></div>

            <!-- Grid pattern overlay -->
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgb(148 163 184 / 0.08) 1px, transparent 0); background-size: 40px 40px;"></div>
        </div>


        <div class="container mx-auto px-6 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-start">
                <!-- Left Side - Information -->
                <div class="animate-slide-up">
                    <!-- Breadcrumb -->
                    <div class="flex items-center space-x-2 text-sm text-gray-400 mb-6">
                        <a href="{{ route('home') }}" wire:navigate class="hover:text-cyan-400 transition-colors">Home</a>
                        <span>/</span>
                        <span class="text-gray-300">Request Demo</span>
                    </div>

                    <h1 class="text-4xl lg:text-6xl font-bold mb-6 leading-tight">
                        See
                        <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                            FitnessFlow test
                        </span>
                        <br>in Action
                    </h1>

                    <p class="text-xl text-gray-300 mb-8 leading-relaxed">
                        Schedule a personalized demo with our team and discover how FitnessFlow can transform your gym management experience.
                    </p>

                    <!-- Features Grid -->
                    <div class="grid sm:grid-cols-2 gap-6 mb-8">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center shadow-lg shadow-purple-500/30">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-1">Personalized Demo</h3>
                                <p class="text-gray-400 text-sm">Tailored to your gym's specific needs</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-lg flex items-center justify-center shadow-lg shadow-cyan-500/30">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-1">30-Minute Session</h3>
                                <p class="text-gray-400 text-sm">Quick but comprehensive overview</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-pink-500 to-pink-600 rounded-lg flex items-center justify-center shadow-lg shadow-pink-500/30">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-1">No Commitment</h3>
                                <p class="text-gray-400 text-sm">Absolutely no obligation to purchase</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-lg shadow-indigo-500/30">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-1">Expert Guidance</h3>
                                <p class="text-gray-400 text-sm">Questions answered by our team</p>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="bg-gradient-to-br from-gray-800/40 to-gray-900/40 backdrop-blur-sm border border-gray-700/50 rounded-xl p-6 hover:border-purple-500/50 transition-all duration-500">
                        <div class="grid grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-transparent bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text mb-1">500+</div>
                                <div class="text-sm text-gray-400">Happy Clients</div>
                            </div>
                            <div class="text-center border-x border-gray-700/50">
                                <div class="text-3xl font-bold text-transparent bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text mb-1">98%</div>
                                <div class="text-sm text-gray-400">Satisfaction</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-transparent bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text mb-1">24/7</div>
                                <div class="text-sm text-gray-400">Support</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Form -->
                <div class="animate-slide-up animation-delay-200">
                    <div class="bg-gradient-to-br from-gray-800/40 to-gray-900/40 backdrop-blur-sm border border-gray-700/50 rounded-2xl shadow-2xl overflow-hidden hover:border-purple-500/50 transition-all duration-500">
                        <!-- Form Header -->
                        <div class="bg-gradient-to-r from-purple-600 to-cyan-600 p-6">
                            <h2 class="text-2xl font-bold text-white mb-2">Request Your Demo</h2>
                            <p class="text-purple-100">Fill out the form below and we'll be in touch shortly</p>
                        </div>

                        <!-- Form Body -->
                        <form wire:submit.prevent="submitRequest" class="p-8 space-y-6">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-200 mb-2">
                                    Full Name <span class="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="name"
                                    wire:model="name"
                                    class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700/50 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                    placeholder="John Doe"
                                    required
                                >
                                @error('name')
                                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email and Phone Grid -->
                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-200 mb-2">
                                        Email Address <span class="text-red-400">*</span>
                                    </label>
                                    <input
                                        type="email"
                                        id="email"
                                        wire:model="email"
                                        class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700/50 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                        placeholder="john@example.com"

                                    >
                                    @error('email')
                                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-200 mb-2">
                                        Phone Number <span class="text-red-400">*</span>
                                    </label>
                                    <input
                                        type="tel"
                                        id="phone"
                                        wire:model="phone"
                                        class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700/50 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                        placeholder="+1 (555) 123-4567"
                                        required
                                    >
                                    @error('phone')
                                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Company Name and Job Title Grid -->
                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Company Name -->
                                <div>
                                    <label for="company_name" class="block text-sm font-medium text-gray-200 mb-2">
                                        Company/Gym Name
                                    </label>
                                    <input
                                        type="text"
                                        id="company_name"
                                        wire:model="company_name"
                                        class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700/50 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                        placeholder="FitZone Gym"
                                    >
                                    @error('company_name')
                                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Gym Size -->
                                <div>
                                    <label for="gym_size" class="block text-sm font-medium text-gray-200 mb-2">
                                        Number of Members
                                    </label>
                                    <input
                                        type="number"
                                        id="gym_size"
                                        wire:model="gym_size"
                                        class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700/50 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                        placeholder="100"
                                        min="1"
                                    >
                                    @error('gym_size')
                                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Message -->
                            <div>
                                <label for="message" class="block text-sm font-medium text-gray-200 mb-2">
                                    Additional Information
                                </label>
                                <textarea
                                    id="message"
                                    wire:model="message"
                                    rows="4"
                                    class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700/50 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all resize-none"
                                    placeholder="Tell us about your gym and what features you're most interested in..."
                                    maxlength="1000"
                                ></textarea>
                                <div class="flex justify-between items-center mt-2">
                                    @error('message')
                                        <p class="text-sm text-red-400">{{ $message }}</p>
                                    @else
                                        <span class="text-sm text-gray-500"></span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Privacy Notice -->
                            <div class="bg-cyan-500/10 border border-cyan-500/20 rounded-lg p-4">
                                <div class="flex items-start space-x-3">
                                    <svg class="w-5 h-5 text-cyan-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                    <p class="text-sm text-gray-300">
                                        Your information is secure and will only be used to contact you about your demo request. We respect your privacy.
                                    </p>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="submitRequest"
                                class="w-full px-8 py-4 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-xl hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 transform hover:scale-105 text-lg font-semibold shadow-lg shadow-purple-500/50 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                            >
                                <span wire:loading.remove wire:target="submitRequest">
                                    Request Demo
                                </span>
                                <span wire:loading.flex wire:target="submitRequest" class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Submitting...
                                </span>
                            </button>
                        </form>
                    </div>

                    <!-- Additional Info -->
                    <div class="mt-6 text-center">
                        <p class="text-gray-400 text-sm mb-2">
                            Need immediate assistance?
                        </p>
                        <div class="flex items-center justify-center space-x-6">
                            <a href="mailto:{{ config('app.contact_email') }}" class="flex items-center space-x-2 text-cyan-400 hover:text-cyan-300 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                <span class="text-sm">Email Us</span>
                            </a>
                            <a href="tel:{{ config('app.contact_phone') }}" class="flex items-center space-x-2 text-cyan-400 hover:text-cyan-300 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <span class="text-sm">Call Us</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Custom Styles for Animations (matching home page) -->
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes slide-up {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-up {
            animation: slide-up 0.6s ease-out;
        }

        .animation-delay-200 {
            animation-delay: 0.2s;
            opacity: 0;
            animation-fill-mode: forwards;
        }
    </style>
</div>
