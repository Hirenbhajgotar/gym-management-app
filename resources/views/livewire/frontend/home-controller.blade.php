<div>
    <!-- Toast Messages -->
    @livewire('toast')
    
    <!-- Hero Section -->
    <section id="home" class="min-h-screen flex items-center pt-20">
        <div class="container mx-auto px-6">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="animate-slide-up">
                    <h1 class="text-5xl lg:text-7xl font-bold mb-6 leading-tight">
                        Complete
                        <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                            Gym Management
                        </span>
                        System
                    </h1>
                    <p class="text-xl text-gray-300 mb-8 leading-relaxed">
                        An all-in-one Gym Management Software solution designed specifically for gyms and fitness centres, offering comprehensive tools for efficient membership management, scheduling, billing, and performance tracking — all within a single integrated platform.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 mb-8">
                        <button wire:click="startFreeTrial" wire:loading.attr="disabled"
                            class="px-8 py-4 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-xl hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 transform hover:scale-105 text-lg font-semibold">
                            <span wire:loading.remove wire:target="startFreeTrial">Start a Free Trial</span>
                            <span wire:loading wire:target="startFreeTrial">Starting...</span>
                        </button>
                        <x-frontend.modal size="xl">
                            <x-slot:trigger>
                                <button
                                    class="px-8 py-4 border border-white/20 rounded-xl hover:bg-white/10 transition-all duration-300 text-lg font-semibold cursor-pointer">
                                    Watch Demo
                                </button>
                            </x-slot:trigger>

                            <div class="flex flex-col items-center justify-center space-y-4">
                                <div class="h-16 w-16 bg-gradient-to-br from-purple-500 to-cyan-500 rounded-full flex items-center justify-center shadow-lg shadow-purple-500/30 animate-pulse">
                                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                
                                <h3 class="text-xl font-bold bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                                    Coming Soon!
                                </h3>
                                
                                <p class="text-gray-300">
                                    Our interactive demo is currently being polished and will be available shortly.
                                </p>
                            </div>
                        </x-frontend.modal>
                    </div>
                    <div class="flex items-center space-x-8 text-sm text-gray-400">
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            No Credit Card Required
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            Cancel Anytime
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            Affordable Pricing
                        </div>
                    </div>
                </div>
                <div class="relative animate-float">
                    <div class="relative z-10">
                        <img src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                            alt="Modern Gym Interior" class="rounded-2xl shadow-2xl w-full h-96 object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-purple-900/50 to-transparent rounded-2xl">
                        </div>
                    </div>
                    <div
                        class="absolute -bottom-4 -right-4 w-24 h-24 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-full blur-xl opacity-70">
                    </div>
                    <div
                        class="absolute -top-4 -left-4 w-32 h-32 bg-gradient-to-r from-cyan-500 to-purple-500 rounded-full blur-xl opacity-50">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-16 relative overflow-hidden bg-gradient-to-b from-gray-950 via-gray-900 to-black">
        <!-- Animated Background Elements -->
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
            <!-- Section Header -->
            <div class="text-center mb-12 animate-fade-in">
                <h2 class="text-3xl lg:text-5xl font-bold mb-4">
                    Powerful Features for
                    <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                        Modern Gyms
                    </span>
                </h2>
                <p class="text-lg text-gray-400 max-w-2xl mx-auto">
                    Everything you need to manage your gym business efficiently, from lead generation to member management
                </p>
            </div>

            <!-- Feature 1: Smart CRM Integration -->
            <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-stretch mb-16 animate-slide-up">
                <div class="order-2 lg:order-1">
                    <div class="bg-gradient-to-br from-gray-800/40 to-gray-900/40 backdrop-blur-sm border border-gray-700/50 rounded-2xl p-6 lg:p-8 hover:border-purple-500/50 hover:shadow-lg hover:shadow-purple-500/10 transition-all duration-500 h-full flex flex-col justify-center">
                        <div class="flex items-center gap-4 mb-7">
                            <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-purple-500/30">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-bold">Smart CRM Integration</h3>
                        </div>
                        <p class="text-gray-300 mb-9 leading-relaxed">
                            Automatically capture and manage leads from Facebook, Instagram, and other social media platforms. Convert prospects into members seamlessly.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Social media lead capture with automated integration</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Automated follow-up sequences and nurturing campaigns</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Lead scoring and tracking with intelligent analytics</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Conversion analytics and performance reporting</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="relative group h-full">
                        <!-- Decorative elements -->
                        <div class="absolute -top-6 -right-6 w-32 h-32 bg-purple-500/40 rounded-full blur-3xl group-hover:bg-purple-500/60 transition-all duration-500"></div>
                        <div class="absolute -bottom-6 -left-6 w-36 h-36 bg-cyan-500/40 rounded-full blur-3xl group-hover:bg-cyan-500/60 transition-all duration-500"></div>

                        <div class="relative overflow-hidden rounded-2xl shadow-2xl h-full">
                            <img src="https://images.unsplash.com/photo-1600880292203-757bb62b4baf?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                                 alt="CRM Dashboard"
                                 class="w-full h-full min-h-[420px] object-cover transform group-hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-purple-900/60 via-purple-900/20 to-transparent"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature 2: Advanced User Management -->
            <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-stretch mb-16 animate-slide-up" style="animation-delay: 0.1s;">
                <div class="order-1">
                    <div class="relative group h-full">
                        <!-- Decorative elements -->
                        <div class="absolute -top-6 -left-6 w-32 h-32 bg-cyan-500/40 rounded-full blur-3xl group-hover:bg-cyan-500/60 transition-all duration-500"></div>
                        <div class="absolute -bottom-6 -right-6 w-36 h-36 bg-purple-500/40 rounded-full blur-3xl group-hover:bg-purple-500/60 transition-all duration-500"></div>

                        <div class="relative overflow-hidden rounded-2xl shadow-2xl h-full">
                            <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                                 alt="User Management"
                                 class="w-full h-full min-h-[420px] object-cover transform group-hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-cyan-900/60 via-cyan-900/20 to-transparent"></div>
                        </div>
                    </div>
                </div>
                <div class="order-2">
                    <div class="bg-gradient-to-br from-gray-800/40 to-gray-900/40 backdrop-blur-sm border border-gray-700/50 rounded-2xl p-6 lg:p-8 hover:border-cyan-500/50 hover:shadow-lg hover:shadow-cyan-500/10 transition-all duration-500 h-full flex flex-col justify-center">
                        <div class="flex items-center gap-4 mb-7">
                            <div class="w-14 h-14 bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-cyan-500/30">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-bold">Advanced User Management</h3>
                        </div>
                        <p class="text-gray-300 mb-9 leading-relaxed">
                            Comprehensive user management system with role-based access control for managers, receptionists, trainers, and gym members.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Role-based permissions for different staff levels</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Staff management with performance tracking</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Detailed member profiles with complete history</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Granular access control for security and compliance</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Feature 3: Smart Attendance Tracking -->
            <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-stretch mb-16 animate-slide-up" style="animation-delay: 0.2s;">
                <div class="order-2 lg:order-1">
                    <div class="bg-gradient-to-br from-gray-800/40 to-gray-900/40 backdrop-blur-sm border border-gray-700/50 rounded-2xl p-6 lg:p-8 hover:border-purple-500/50 hover:shadow-lg hover:shadow-purple-500/10 transition-all duration-500 h-full flex flex-col justify-center">
                        <div class="flex items-center gap-4 mb-7">
                            <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-purple-500/30">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-bold">Smart Attendance Tracking</h3>
                        </div>
                        <p class="text-gray-300 mb-9 leading-relaxed">
                            Monitor member attendance patterns, track peak hours, and generate detailed reports for better business insights.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Real-time check-ins with automated notifications</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Attendance analytics with visual insights</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Peak hour tracking for optimal resource allocation</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Member engagement metrics and retention analysis</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="relative group h-full">
                        <!-- Decorative elements -->
                        <div class="absolute -top-6 -right-6 w-32 h-32 bg-purple-500/40 rounded-full blur-3xl group-hover:bg-purple-500/60 transition-all duration-500"></div>
                        <div class="absolute -bottom-6 -left-6 w-36 h-36 bg-pink-500/40 rounded-full blur-3xl group-hover:bg-pink-500/60 transition-all duration-500"></div>

                        <div class="relative overflow-hidden rounded-2xl shadow-2xl h-full">
                            <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                                 alt="Attendance Analytics"
                                 class="w-full h-full min-h-[420px] object-cover transform group-hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-purple-900/60 via-purple-900/20 to-transparent"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature 4: Multi-Location Support & Analytics -->
            <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-stretch animate-slide-up" style="animation-delay: 0.3s;">
                <div class="order-1">
                    <div class="relative group h-full">
                        <!-- Decorative elements -->
                        <div class="absolute -top-6 -left-6 w-32 h-32 bg-cyan-500/40 rounded-full blur-3xl group-hover:bg-cyan-500/60 transition-all duration-500"></div>
                        <div class="absolute -bottom-6 -right-6 w-36 h-36 bg-blue-500/40 rounded-full blur-3xl group-hover:bg-blue-500/60 transition-all duration-500"></div>

                        <div class="relative overflow-hidden rounded-2xl shadow-2xl h-full">
                            <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                                 alt="Analytics Dashboard"
                                 class="w-full h-full min-h-[420px] object-cover transform group-hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-cyan-900/60 via-cyan-900/20 to-transparent"></div>
                        </div>
                    </div>
                </div>
                <div class="order-2">
                    <div class="bg-gradient-to-br from-gray-800/40 to-gray-900/40 backdrop-blur-sm border border-gray-700/50 rounded-2xl p-6 lg:p-8 hover:border-cyan-500/50 hover:shadow-lg hover:shadow-cyan-500/10 transition-all duration-500 h-full flex flex-col justify-center">
                        <div class="flex items-center gap-4 mb-7">
                            <div class="w-14 h-14 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-cyan-500/30">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl lg:text-3xl font-bold">Multi-Location & Analytics</h3>
                        </div>
                        <p class="text-gray-300 mb-9 leading-relaxed">
                            Manage multiple gym locations from a single dashboard with comprehensive analytics and customizable reports for data-driven decisions.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Centralized dashboard for all locations</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Revenue analytics and member retention metrics</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Cross-location reporting and performance dashboards</span>
                            </li>
                            <li class="flex items-start group">
                                <div class="w-5 h-5 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-3 h-3 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-gray-300 text-sm">Custom reports and scalable architecture for growth</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Software Solutions Section -->
    <section id="solutions" class="py-24 bg-gradient-to-b from-gray-900 to-black">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16 animate-fade-in">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6">
                    Why Choose Our
                    <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                        Software Solution
                    </span>
                </h2>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                    Transform your gym business with our comprehensive management platform designed to streamline
                    operations, increase revenue, and deliver exceptional member experiences
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Solution 1: Simplify Everything -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-purple-500/50 hover:shadow-2xl hover:shadow-purple-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.1s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-purple-400 transition-colors">Simplify
                        Everything</h3>
                    <p class="text-gray-300 leading-relaxed">
                        Replace scattered spreadsheets and manual processes with one intuitive platform. Automate
                        routine tasks and focus on what matters most - growing your business and serving your members.
                    </p>
                </div>

                <!-- Solution 2: Boost Revenue -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-cyan-500/50 hover:shadow-2xl hover:shadow-cyan-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.2s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-cyan-400 transition-colors">Boost Revenue</h3>
                    <p class="text-gray-300 leading-relaxed">
                        Maximize income with smart lead conversion tools, automated billing, membership upsells, and
                        data-driven insights that help you identify and capitalize on growth opportunities.
                    </p>
                </div>

                <!-- Solution 3: Empower Your Team -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-purple-500/50 hover:shadow-2xl hover:shadow-purple-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.3s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-purple-400 transition-colors">Empower Your Team
                    </h3>
                    <p class="text-gray-300 leading-relaxed">
                        Give your staff the tools they need to excel. From receptionists to trainers, everyone gets
                        role-based access to features that make their jobs easier and more productive.
                    </p>
                </div>

                <!-- Solution 4: Engage Your Members -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-cyan-500/50 hover:shadow-2xl hover:shadow-cyan-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.4s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-cyan-400 transition-colors">Engage Your Members
                    </h3>
                    <p class="text-gray-300 leading-relaxed">
                        Build stronger relationships with personalized communication, member portals, attendance
                        tracking, and engagement analytics that help you understand and serve your community better.
                    </p>
                </div>

                <!-- Solution 5: Scalability for Growth -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-purple-500/50 hover:shadow-2xl hover:shadow-purple-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.5s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-purple-600 to-indigo-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-purple-400 transition-colors">Scalability for
                        Growth</h3>
                    <p class="text-gray-300 leading-relaxed">
                        Start with one location and expand confidently. Our multi-gym support and enterprise-grade
                        infrastructure grow with you, whether you're managing 1 or 100 locations.
                    </p>
                </div>

                <!-- Solution 6: Customer Support -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-cyan-500/50 hover:shadow-2xl hover:shadow-cyan-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.6s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-cyan-600 to-teal-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-cyan-400 transition-colors">24/7 Customer
                        Support</h3>
                    <p class="text-gray-300 leading-relaxed">
                        Never feel alone on your journey. Our dedicated support team is available around the clock to
                        help you succeed, with comprehensive documentation and training resources.
                    </p>
                </div>

                <!-- Solution 7: Real-Time Insights -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-purple-500/50 hover:shadow-2xl hover:shadow-purple-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.7s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-purple-400 transition-colors">Real-Time Insights
                    </h3>
                    <p class="text-gray-300 leading-relaxed">
                        Make informed decisions with live dashboards and comprehensive analytics. Track KPIs, monitor
                        performance, and identify trends to stay ahead of the competition.
                    </p>
                </div>

                <!-- Solution 8: Secure & Compliant -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-cyan-500/50 hover:shadow-2xl hover:shadow-cyan-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.8s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-cyan-500 to-emerald-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-cyan-400 transition-colors">Secure & Compliant
                    </h3>
                    <p class="text-gray-300 leading-relaxed">
                        Protect your business and member data with enterprise-grade security, automatic backups, and
                        compliance with industry standards. Peace of mind is built-in.
                    </p>
                </div>

                <!-- Solution 9: Easy Integration -->
                <div
                    class="solution-card bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8 hover:border-purple-500/50 hover:shadow-2xl hover:shadow-purple-500/20 transition-all duration-500 group animate-slide-up"
                    style="animation-delay: 0.9s;">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-purple-600 to-fuchsia-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 group-hover:text-purple-400 transition-colors">Easy Integration
                    </h3>
                    <p class="text-gray-300 leading-relaxed">
                        Seamlessly connect with your favorite tools and platforms. From payment gateways to marketing
                        tools, our robust API ecosystem ensures smooth data flow across your business.
                    </p>
                </div>
            </div>

            <!-- CTA Banner -->
            <div class="mt-16 text-center animate-fade-in" style="animation-delay: 1s;">
                <div
                    class="max-w-4xl mx-auto bg-gradient-to-r from-purple-900/50 to-cyan-900/50 backdrop-blur-sm border border-purple-500/30 rounded-2xl p-8 md:p-12">
                    <h2 class="text-3xl font-bold mb-4">
                        Ready to Transform Your
                        <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                            Gym Business?
                        </span>
                    </h2>
                    <p class="text-gray-300 mb-8 text-lg">
                        Join hundreds of successful gym owners who have streamlined their operations and accelerated
                        their growth with our platform.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <button wire:click="startFreeTrial" wire:loading.attr="disabled"
                            class="px-8 py-4 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-full font-semibold hover:shadow-lg hover:shadow-purple-500/50 transition-all duration-300 hover:scale-105">
                                <span wire:loading.remove wire:target="startFreeTrial">Start Free Trial</span>
                                <span wire:loading wire:target="startFreeTrial">Starting...</span>
                        </button>
                        <a href="{{route('request-demo')}}"
                            class="px-8 py-4 bg-gray-800 border border-gray-700 rounded-full font-semibold hover:border-purple-500 transition-all duration-300 hover:scale-105">
                            Schedule a Demo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 relative overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-20 left-10 w-40 h-40 bg-purple-500/10 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-20 right-10 w-48 h-48 bg-cyan-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        </div>

        <div class="container mx-auto px-6 relative z-10">
            <div class="text-center mb-16 animate-fade-in">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6">
                    Simple,
                    <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                        Transparent Pricing
                    </span>
                </h2>
                <p class="text-xl text-gray-300">Choose the perfect plan for your gym business</p>
            </div>

            <div class="flex flex-wrap justify-center gap-8 max-w-7xl mx-auto">
                @foreach ($plans as $index => $plan)
                    <div
                        class="relative w-full md:w-[calc(50%-2rem)] lg:w-[calc(33.333%-2rem)] min-w-[300px] group bg-gradient-to-br from-gray-800/40 to-gray-900/40 backdrop-blur-sm border {{ !empty($plan->badge) ? 'border-purple-500/50' : 'border-gray-700/50' }} rounded-2xl p-8 hover:border-{{ !empty($plan->badge) ? 'purple' : 'cyan' }}-400 hover:shadow-2xl hover:shadow-{{ !empty($plan->badge) ? 'purple' : 'cyan' }}-500/20 hover:-translate-y-2 transition-all duration-500 flex flex-col">
                        @if (!empty($plan->badge))
                            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 z-10">
                                <span
                                    class="bg-gradient-to-r from-purple-500 to-cyan-500 px-4 py-1 rounded-full text-sm font-semibold shadow-lg shadow-purple-500/50 group-hover:shadow-xl group-hover:shadow-purple-500/60 transition-all duration-300">
                                    {{ $plan->badge }}
                                </span>
                            </div>
                        @endif

                        <h3 class="text-2xl font-bold mb-4 group-hover:text-transparent group-hover:bg-gradient-to-r group-hover:from-purple-400 group-hover:to-cyan-400 group-hover:bg-clip-text transition-all duration-300">{{ $plan->name }}</h3>

                        @if ($plan->isFree())
                            <div class="mb-6">
                                <span class="text-4xl font-bold group-hover:text-transparent group-hover:bg-gradient-to-r group-hover:from-purple-400 group-hover:to-cyan-400 group-hover:bg-clip-text transition-all duration-300">Free</span>
                            </div>
                        @else
                            <div class="mb-6">
                                <span class="text-4xl font-bold group-hover:text-transparent group-hover:bg-gradient-to-r group-hover:from-purple-400 group-hover:to-cyan-400 group-hover:bg-clip-text transition-all duration-300">
                                    {{ $plan->currency->getSymbol() }}{{ number_format($plan->price_yearly) }}
                                </span>
                                <span class="text-gray-400 group-hover:text-gray-300 transition-colors">/Year</span>
                            </div>
                        @endif

                        @if ($plan->hasTrial())
                            <div class="mb-4 text-sm text-green-400 font-semibold group-hover:text-green-300 transition-colors">
                                {{ $plan->trial_days }} days free trial
                            </div>
                        @endif

                        <div class="mb-8 flex-grow">
                            <p class="text-gray-300 mb-4 group-hover:text-gray-200 transition-colors">{{ $plan->brief_description }}</p>
                            <ul class="space-y-3 text-sm">
                                @foreach ($plan->features as $feature)
                                    <li class="flex items-start group/item">
                                        <span class="w-5 h-5 rounded-full bg-green-500/20 flex items-center justify-center mr-3 mt-0.5 flex-shrink-0 group-hover/item:bg-green-500/30 transition-colors">
                                            <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span class="text-gray-300 group-hover:text-gray-200 transition-colors">{!! $feature->brief_description !!}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="mt-auto">
                            <button wire:click="selectPlan({{ $plan->id }})"
                                class="w-full py-3 {{ !empty($plan->badge) ? 'bg-gradient-to-r from-purple-500 to-cyan-500 hover:from-purple-600 hover:to-cyan-600 shadow-lg shadow-purple-500/30 hover:shadow-xl hover:shadow-purple-500/50' : 'border-2 border-gray-600 hover:border-cyan-400 hover:bg-cyan-400/10 text-white' }} rounded-xl font-semibold transition-all duration-300 transform hover:scale-105">
                                <span wire:loading.remove wire:target="selectPlan({{ $plan->id }})">
                                    {{ $plan->hasTrial() ? 'Start Free Trial' : ($plan->isFree() ? 'Get Started' : 'Choose Plan') }}
                                </span>
                                <x-loading-icon target="selectPlan({{ $plan->id }})" variant="text" name="Loading" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Enterprise Pricing Section -->
            @if(isset($enterprisePlans) && $enterprisePlans->count() > 0)
                <div class="mt-16 lg:mt-24 animate-fade-in px-4">
                    @foreach($enterprisePlans as $enterprisePlan)
                        <div class="relative max-w-7xl mx-auto rounded-[2.5rem] p-[2px] bg-gradient-to-r from-purple-500/50 via-cyan-500/50 to-purple-500/50 group hover:scale-[1.01] transition-all duration-500 shadow-2xl shadow-purple-900/20">
                            <div class="absolute inset-0 bg-gradient-to-r from-purple-500/20 via-cyan-500/20 to-purple-500/20 blur-xl opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                            
                            <div class="relative bg-gray-900/95 backdrop-blur-xl rounded-[2.4rem] overflow-hidden">
                                <!-- Background Ambient Glow -->
                                <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-purple-500/10 rounded-full blur-[100px] pointer-events-none"></div>
                                <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 bg-cyan-500/10 rounded-full blur-[100px] pointer-events-none"></div>
                                <div class="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 brightness-100 contrast-150 mix-blend-overlay"></div>

                                <div class="relative z-10 flex flex-col lg:flex-row items-stretch lg:items-center gap-10 lg:gap-16 p-8 md:p-12 lg:p-16">
                                    
                                    <!-- Left Content -->
                                    <div class="flex-1 flex flex-col justify-center text-center lg:text-left">
                                        <div class="inline-flex items-center gap-3 mb-6 justify-center lg:justify-start">
                                            <span class="px-4 py-1.5 rounded-full text-xs font-bold tracking-widest uppercase bg-gradient-to-r from-purple-500/20 to-cyan-500/20 border border-purple-500/30 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-cyan-400 shadow-sm">
                                                {{ $enterprisePlan->badge ?? 'ENTERPRISE' }}
                                            </span>
                                        </div>

                                        <h3 class="text-4xl md:text-5xl font-bold text-white mb-6 tracking-tight">
                                            {{ $enterprisePlan->name }}
                                        </h3>
                                        
                                        <div class="prose prose-lg prose-invert text-gray-400 mb-10 max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                                            {!! $enterprisePlan->description ?? $enterprisePlan->brief_description !!}
                                        </div>
                                        
                                        @if($enterprisePlan->features->count() > 0)
                                            <div class="grid sm:grid-cols-2 gap-x-8 gap-y-4 text-left">
                                                @foreach($enterprisePlan->features as $feature)
                                                    <div class="flex items-center gap-3 group/feature p-2 rounded-lg hover:bg-white/5 transition-colors duration-300">
                                                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gradient-to-br from-purple-500 to-cyan-500 p-[1px]">
                                                            <div class="w-full h-full rounded-full bg-gray-900 flex items-center justify-center">
                                                                <svg class="w-3.5 h-3.5 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" stroke="url(#gradient-{{ $loop->parent->index }}-{{ $loop->index }})" />
                                                                    <defs>
                                                                        <linearGradient id="gradient-{{ $loop->parent->index }}-{{ $loop->index }}" x1="0%" y1="0%" x2="100%" y2="0%">
                                                                            <stop offset="0%" style="stop-color:#a855f7" />
                                                                            <stop offset="100%" style="stop-color:#06b6d4" />
                                                                        </linearGradient>
                                                                    </defs>
                                                                </svg>
                                                            </div>
                                                        </div>
                                                        <span class="text-gray-300 font-medium group-hover/feature:text-white transition-colors">{!! $feature->brief_description !!}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- Right CTA Card -->
                                    <div class="flex-shrink-0 w-full lg:w-[26rem]">
                                        <div class="h-full bg-gradient-to-b from-gray-800/80 to-gray-900/80 backdrop-blur-md rounded-3xl border border-gray-700/50 p-8 flex flex-col items-center justify-center text-center shadow-xl relative overflow-hidden group/card">
                                            <!-- Hover effect for card -->
                                            <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-cyan-500/5 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>

                                            <p class="text-gray-400 font-medium mb-3 relative z-10">Tailored for large chains</p>
                                            <div class="text-4xl font-bold text-white mb-8 tracking-tight relative z-10">Custom Pricing</div>
                                            
                                            <a href="#contact" class="relative w-full group/btn overflow-hidden rounded-xl">
                                                <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-purple-600 to-cyan-600 transition-all duration-300 group-hover/btn:scale-105 group-hover/btn:brightness-110"></div>
                                                <div class="relative px-8 py-4 flex items-center justify-center gap-3 text-white font-bold text-lg">
                                                    <span>{{ $enterprisePlan->badge == 'Call Us' ? 'Call Now' : 'Contact Sales' }}</span>
                                                    <svg class="w-5 h-5 transition-transform duration-300 group-hover/btn:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                    </svg>
                                                </div>
                                            </a>
                                            
                                            <div class="mt-6 flex items-center justify-center gap-6 text-sm text-gray-500 relative z-10">
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Priority Support
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                    </svg>
                                                    Custom Setup
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-12 lg:py-24 relative overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-10 lg:top-20 left-5 lg:left-10 w-32 lg:w-40 h-32 lg:h-40 bg-purple-500/10 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-10 lg:bottom-20 right-5 lg:right-10 w-40 lg:w-48 h-40 lg:h-48 bg-cyan-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgb(148 163 184 / 0.05) 1px, transparent 0); background-size: 40px 40px;"></div>
        </div>

        <div class="container mx-auto px-4 lg:px-6 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center max-w-7xl mx-auto">
                <!-- Left Side: Why Choose Us -->
                <div class="animate-slide-up">
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-6 text-center lg:text-left">
                        Why Choose
                        <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                            {{ config('app.name') }}?
                        </span>
                    </h2>
                    <p class="text-lg text-gray-300 mb-8 leading-relaxed text-center lg:text-left">
                        Choose the leading gym management software to <span class="text-purple-400 font-semibold">MANAGE</span>,
                        <span class="text-cyan-400 font-semibold">STREAMLINE</span> &
                        <span class="text-purple-400 font-semibold">SUCCEED</span>.
                    </p>

                    <div class="space-y-4 mb-8">
                        <div class="flex items-start group bg-gray-800/30 p-4 rounded-xl backdrop-blur-sm lg:bg-transparent lg:p-0">
                            <div class="w-6 h-6 rounded-full bg-purple-500/20 flex items-center justify-center mr-4 mt-1 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold mb-1 group-hover:text-purple-400 transition-colors">Quick Data Migration & Easy to Use</h3>
                                <p class="text-gray-400 text-sm">Seamlessly transition from your current system with our expert migration support</p>
                            </div>
                        </div>

                        <div class="flex items-start group bg-gray-800/30 p-4 rounded-xl backdrop-blur-sm lg:bg-transparent lg:p-0">
                            <div class="w-6 h-6 rounded-full bg-cyan-500/20 flex items-center justify-center mr-4 mt-1 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold mb-1 group-hover:text-cyan-400 transition-colors">Data Security with Zero Downtime</h3>
                                <p class="text-gray-400 text-sm">Enterprise-grade security with 99.9% uptime guarantee for your peace of mind</p>
                            </div>
                        </div>

                        <div class="flex items-start group bg-gray-800/30 p-4 rounded-xl backdrop-blur-sm lg:bg-transparent lg:p-0">
                            <div class="w-6 h-6 rounded-full bg-purple-500/20 flex items-center justify-center mr-4 mt-1 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold mb-1 group-hover:text-purple-400 transition-colors">Reliable 24/7 Customer Support</h3>
                                <p class="text-gray-400 text-sm">Our dedicated team is always here to help you succeed with training and ongoing assistance</p>
                            </div>
                        </div>
                    </div>

                    <p class="text-gray-300 text-sm italic text-center lg:text-left">
                        "Take the first step towards maximizing your operations!"
                    </p>
                </div>

                <!-- Right Side: Demo & Contact -->
                <div class="animate-slide-up" style="animation-delay: 0.2s;">
                    <div class="bg-gradient-to-br from-gray-800/60 to-gray-900/60 backdrop-blur-sm border border-gray-700/50 rounded-2xl p-6 lg:p-10 hover:border-purple-500/50 hover:shadow-2xl hover:shadow-purple-500/20 transition-all duration-500">
                        <h3 class="text-2xl lg:text-3xl font-bold mb-4 text-center">
                            Book Your <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">Personalized Demo</span> Today!
                        </h3>
                        <p class="text-gray-300 text-center mb-8">
                            Curious about how our software can transform your studio?
                        </p>

                        <!-- Action Buttons -->
                        <div class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5 items-center mx-auto">
                            <button wire:click="startFreeTrial" wire:loading.attr="disabled"
                                class="w-full px-6 py-3 lg:px-8 lg:py-4 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-xl hover:from-purple-600 hover:to-cyan-600 shadow-lg shadow-purple-500/30 hover:shadow-xl hover:shadow-purple-500/50 transition-all duration-300 transform hover:scale-105 text-base lg:text-lg font-semibold">
                                <span wire:loading.remove wire:target="startFreeTrial">Try Us Out for Free!</span>
                                <span wire:loading wire:target="startFreeTrial">Starting...</span>
                            </button>
                            <a href="{{ route('request-demo')}}"
                                class="w-full px-6 py-3 lg:px-8 lg:py-4 border-2 border-cyan-400 hover:bg-cyan-400/10 rounded-xl transition-all duration-300 transform hover:scale-105 text-base lg:text-lg font-semibold text-center">
                                Request Your Demo
                            </a>
                        </div>

                        <!-- Contact Information -->
                        <div class="border-t border-gray-700 pt-6 space-y-4">
                            <div class="flex items-center group">
                                <div class="w-10 h-10 rounded-full bg-purple-500/20 flex items-center justify-center mr-3 flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <div class="text-left">
                                    <p class="text-xs text-gray-400">Call us</p>
                                    <a href="tel:{{config('app.contact_phone')}}" class="text-lg font-semibold text-white hover:text-purple-400 transition-colors">
                                        {{config('app.contact_phone')}}
                                    </a>
                                </div>
                            </div>

                            <div class="flex items-center group">
                                <div class="w-10 h-10 rounded-full bg-cyan-500/20 flex items-center justify-center mr-3 flex-shrink-0 group-hover:bg-cyan-500/30 transition-colors">
                                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="text-left overflow-hidden">
                                    <p class="text-xs text-gray-400">Email us</p>
                                    <a href="mailto:{{config('app.contact_email')}}" class="text-lg font-semibold text-white hover:text-cyan-400 transition-colors block truncate md:overflow-visible md:whitespace-normal">
                                        {{config('app.contact_email')}}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Info -->
                        <div class="mt-6 text-center">
                            <p class="text-sm text-gray-400">
                                <svg class="w-4 h-4 inline mr-1 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                                No Credit Card Required
                                <span class="mx-2">•</span>
                                <svg class="w-4 h-4 inline mr-1 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                                Cancel Anytime
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
