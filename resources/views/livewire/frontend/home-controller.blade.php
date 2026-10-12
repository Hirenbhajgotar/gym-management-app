<div>
    <!-- Toast Messages -->
    @livewire('toast')
    
    <!-- Hero Section with Motion Elements & Authentic Gym Copy -->
    <section id="home" class="relative min-h-0 lg:min-h-[92vh] flex items-center pt-20 sm:pt-28 lg:pt-36 pb-12 sm:pb-16 lg:pb-24 overflow-hidden bg-[#07090e]">
        <!-- Blueprint Grid & Ambient Kinetic Glow Background -->
        <div class="absolute inset-0 pointer-events-none z-0">
            <!-- Grid Lines -->
            <div class="absolute inset-0" style="background-image: linear-gradient(to right, rgba(255, 255, 255, 0.035) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, 0.035) 1px, transparent 1px); background-size: 36px 36px;"></div>
            
            <!-- Ambient Kinetic Glowing Color Spots -->
            <div class="absolute top-1/4 left-1/10 w-[280px] h-[280px] sm:w-[500px] sm:h-[500px] bg-cyan-500/10 blur-[100px] sm:blur-[140px] rounded-full animate-pulse pointer-events-none"></div>
            <div class="absolute top-1/3 right-1/10 w-[300px] h-[300px] sm:w-[600px] sm:h-[600px] bg-emerald-500/10 blur-[100px] sm:blur-[150px] rounded-full animate-float pointer-events-none"></div>
            <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-full max-w-[800px] h-[350px] bg-gradient-to-b from-cyan-900/20 via-emerald-900/10 to-transparent blur-[110px] pointer-events-none"></div>
        </div>

        <div class="container mx-auto px-4 sm:px-6 max-w-7xl relative z-10">
            <div class="grid lg:grid-cols-12 gap-10 sm:gap-12 lg:gap-8 items-center">
                <!-- Left Column: High-Impact Hero Value & Authentic Metrics -->
                <div class="lg:col-span-6 space-y-6 sm:space-y-8 animate-slide-up text-center lg:text-left">
                    <!-- Status Pill Badge with Motion Pulse -->
                    <div class="inline-flex items-center gap-2 sm:gap-2.5 px-3 py-1.5 sm:px-3.5 rounded-full bg-[#0d1520]/90 border border-emerald-500/40 text-[10px] sm:text-xs font-bold uppercase tracking-wider sm:tracking-widest text-emerald-400 shadow-[0_0_15px_rgba(16,185,129,0.15)] mx-auto lg:mx-0 max-w-full">
                        <span class="relative flex h-2 w-2 flex-shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
                        </span>
                        <span class="truncate sm:whitespace-normal">ALL-IN-ONE GYM SOFTWARE • BUILT FOR INDIAN GYMS</span>
                    </div>

                    <!-- Authentic High-Converting Headline -->
                    <div class="space-y-2 sm:space-y-3">
                        <h1 class="font-headline font-black text-3xl xs:text-4xl sm:text-5xl lg:text-6xl xl:text-7xl leading-[1.08] sm:leading-[0.92] tracking-tight sm:tracking-tighter text-white uppercase drop-shadow-lg">
                            SUPERCHARGE YOUR <br class="hidden sm:inline" />
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-emerald-300 to-[#cafd00] drop-shadow-[0_0_30px_rgba(6,182,212,0.4)]">
                                FITNESS EMPIRE
                            </span>
                        </h1>
                        <p class="text-gray-400 font-medium text-sm sm:text-base lg:text-lg max-w-xl mx-auto lg:mx-0 pt-2 sm:pt-4 leading-relaxed">
                            Stop losing revenue to expired memberships and manual registers. Automate WhatsApp fee chasers, biometric QR attendance, GST billing, and Meta lead pipelines in one unified platform.
                        </p>
                    </div>

                    <!-- Call To Action Buttons with Interactive Motion Hover -->
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center lg:justify-start pt-1 sm:pt-2">
                        <!-- Primary Solid Neon Green Button with Shimmer -->
                        <button wire:click="startFreeTrial" wire:loading.attr="disabled"
                            class="w-full sm:w-auto group relative overflow-hidden cursor-pointer inline-flex items-center justify-center gap-2.5 bg-[#22c55e] hover:bg-[#16a34a] text-black font-headline font-black text-sm sm:text-base px-6 sm:px-8 py-3.5 sm:py-4 rounded-xl shadow-[0_0_25px_rgba(34,197,94,0.4)] hover:shadow-[0_0_35px_rgba(34,197,94,0.7)] active:scale-[0.98] transition-all uppercase tracking-wider disabled:opacity-75 disabled:cursor-not-allowed">
                            <span class="absolute inset-0 w-1/2 h-full bg-white/30 transform -skew-x-12 -translate-x-full group-hover:translate-x-[300%] transition-transform duration-1000 ease-out"></span>
                            <span wire:loading.remove wire:target="startFreeTrial" class="relative z-10 flex items-center gap-2">
                                <span>START 14-DAY FREE TRIAL</span>
                                <span class="material-symbols-outlined text-xl font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </span>
                            <span wire:loading.flex wire:target="startFreeTrial" class="relative z-10 flex items-center gap-2">
                                <svg class="w-5 h-5 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>STARTING FREE TRIAL...</span>
                            </span>
                        </button>

                        <!-- Secondary Cyan Outlined Button with Glowing Interactive Modal -->
                        <x-frontend.modal size="xl" triggerClass="w-full sm:w-auto inline-block">
                            <x-slot:trigger>
                                <button type="button"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 sm:px-8 py-3.5 sm:py-4 bg-[#0a0f18]/80 border-2 border-cyan-400/80 rounded-xl hover:bg-cyan-400/10 hover:border-cyan-300 shadow-[0_0_15px_rgba(6,182,212,0.2)] hover:shadow-[0_0_25px_rgba(6,182,212,0.5)] transition-all text-sm sm:text-base font-headline font-bold text-white uppercase tracking-wider cursor-pointer group">
                                    <span class="material-symbols-outlined text-cyan-400 text-xl group-hover:scale-110 transition-transform">play_circle</span>
                                    <span>WATCH LIVE TOUR</span>
                                </button>
                            </x-slot:trigger>

                            <div class="flex flex-col items-center justify-center space-y-5 py-4">
                                <div class="h-20 w-20 bg-cyan-500/20 rounded-full flex items-center justify-center border border-cyan-400/40 shadow-[0_0_30px_rgba(6,182,212,0.3)] animate-pulse">
                                    <span class="material-symbols-outlined text-cyan-400 text-4xl" style="font-variation-settings: 'FILL' 1;">smart_display</span>
                                </div>
                                
                                <div class="space-y-2 text-center">
                                    <h3 class="font-headline font-bold text-2xl text-white">
                                        FluxFitness Interactive Product Walkthrough
                                    </h3>
                                    <p class="text-gray-400 text-sm max-w-md mx-auto">
                                        See how automated WhatsApp reminders, biometric check-ins, and member due collections work in real time.
                                    </p>
                                </div>

                                <div class="w-full aspect-video rounded-xl bg-black border border-white/10 flex items-center justify-center relative overflow-hidden group">
                                    <div class="absolute inset-0 bg-gradient-to-tr from-black to-cyan-950/40"></div>
                                    <a href="{{ route('request-demo') }}" class="relative z-10 inline-flex items-center gap-2 bg-[#22c55e] text-black px-6 py-3 rounded-lg font-headline font-black uppercase tracking-wider text-sm shadow-[0_0_20px_rgba(34,197,94,0.4)] hover:brightness-110 transition-all">
                                        <span class="material-symbols-outlined text-lg font-bold">calendar_month</span>
                                        Schedule Live 1-on-1 Walkthrough
                                    </a>
                                </div>
                            </div>
                        </x-frontend.modal>
                    </div>

                    <!-- Bottom Metrics Row (Authentic Gym SaaS Proof) -->
                    <div class="grid grid-cols-3 gap-2 sm:gap-8 lg:gap-10 pt-4 sm:pt-6 border-t border-white/10 text-center sm:text-left">
                        <div class="group">
                            <div class="text-xl sm:text-2xl lg:text-3xl font-headline font-black text-white tracking-tight group-hover:text-cyan-400 transition-colors">
                                {{ config('app.platform_state_badges.members_managed.value', '2,400+') }}
                            </div>
                            <div class="text-[9px] sm:text-[11px] font-bold uppercase tracking-wider sm:tracking-widest text-gray-400 mt-0.5 truncate">
                                ACTIVE MEMBERS
                            </div>
                        </div>
                        <div class="group">
                            <div class="text-xl sm:text-2xl lg:text-3xl font-headline font-black text-white tracking-tight flex items-center gap-1 justify-center sm:justify-start">
                                <span class="text-cyan-400 text-lg sm:text-2xl drop-shadow-[0_0_10px_rgba(6,182,212,0.6)]">★</span>
                                <span>4.9</span>
                            </div>
                            <div class="text-[9px] sm:text-[11px] font-bold uppercase tracking-wider sm:tracking-widest text-gray-400 mt-0.5 truncate">
                                OWNER RATING
                            </div>
                        </div>
                        <div class="group">
                            <div class="text-xl sm:text-2xl lg:text-3xl font-headline font-black text-white tracking-tight text-emerald-400 drop-shadow-[0_0_8px_rgba(16,185,129,0.4)]">
                                99.9%
                            </div>
                            <div class="text-[9px] sm:text-[11px] font-bold uppercase tracking-wider sm:tracking-widest text-gray-400 mt-0.5 truncate">
                                WHATSAPP DELIVERY
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Hero Media Frame with Interactive Parallax & Animated Badges -->
                <div class="lg:col-span-6 relative mt-4 sm:mt-8 lg:mt-0 px-1 sm:px-0">
                    <div class="relative w-full max-w-xl mx-auto group">
                        <!-- Ambient Radial Edge Glow -->
                        <div class="absolute -inset-2 bg-gradient-to-r from-cyan-500/20 via-emerald-500/15 to-cyan-500/20 rounded-3xl blur-2xl opacity-60 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>

                        <!-- Main Screen Media Frame with Clean Gym Dashboard Visual -->
                        <div class="relative rounded-2xl overflow-hidden border border-white/20 bg-[#080d16] shadow-[0_0_60px_rgba(0,0,0,0.9)] transform transition-transform duration-700 group-hover:scale-[1.01] group-hover:-translate-y-1">
                            <!-- Background Base Gym Photo with Dark Glass Gradient Filter -->
                            <div class="absolute inset-0 z-0 pointer-events-none">
                                <picture>
                                    <source srcset="{{ asset('images/banner.webp') }}" type="image/webp">
                                    <img src="{{ asset('images/banner.jpg') }}" 
                                        alt="FluxFitness Gym Operations Dashboard" 
                                        class="w-full h-full object-cover opacity-25 brightness-50 contrast-125 scale-105 filter blur-[1px]" 
                                        width="1200" height="600"
                                        fetchpriority="high">
                                </picture>
                                <div class="absolute inset-0 bg-gradient-to-b from-[#080d16]/95 via-[#080d16]/85 to-[#04070d]/95"></div>
                                <div class="absolute inset-0" style="background-image: linear-gradient(to right, rgba(34, 197, 94, 0.04) 1px, transparent 1px), linear-gradient(to bottom, rgba(34, 197, 94, 0.04) 1px, transparent 1px); background-size: 24px 24px;"></div>
                            </div>

                            <!-- Dashboard Container (pb-12 on mobile provides comfortable vertical clearance for the bottom floating badge) -->
                            <div class="relative z-10 p-3 sm:p-5 space-y-3 sm:space-y-3.5 pb-12 sm:pb-5">
                                <!-- Top Window Title Bar -->
                                <div class="flex items-center justify-between pb-2.5 sm:pb-3 border-b border-white/10 text-xs">
                                    <div class="flex items-center gap-2">
                                        <div class="flex gap-1.5">
                                            <span class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-rose-500/80"></span>
                                            <span class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-amber-500/80"></span>
                                            <span class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-emerald-500/80"></span>
                                        </div>
                                        <span class="text-gray-400 font-mono text-[10px] sm:text-[11px] ml-1 hidden xs:inline sm:inline">IRONCORE FITNESS • LIVE OWNER DASHBOARD</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-[10px] sm:text-[11px] font-mono">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                                            REAL-TIME SYNC
                                        </span>
                                    </div>
                                </div>

                                <!-- Metric Cards Grid with SVG Wave Chart -->
                                <div class="grid grid-cols-12 gap-2.5 sm:gap-3">
                                    <!-- Card 1: Monthly Fee Inflow with SVG Sparkline -->
                                    <div class="col-span-12 sm:col-span-7 bg-[#0f1726]/90 border border-emerald-500/30 rounded-xl p-3 sm:p-3.5 space-y-1.5 sm:space-y-2 shadow-lg relative overflow-hidden">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider sm:tracking-widest text-gray-400">Monthly Revenue Inflow</div>
                                                <div class="text-lg sm:text-2xl font-headline font-black text-white flex items-baseline gap-2 mt-0.5">
                                                    <span>₹4,82,500</span>
                                                    <span class="text-[10px] sm:text-[11px] font-mono font-bold text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded">+28.4%</span>
                                                </div>
                                            </div>
                                            <span class="material-symbols-outlined text-emerald-400 text-base sm:text-lg">trending_up</span>
                                        </div>

                                        <!-- SVG Glowing Sparkline Wave Chart -->
                                        <div class="h-11 sm:h-14 w-full pt-1 relative">
                                            <svg class="w-full h-full overflow-visible" viewBox="0 0 300 60" fill="none" preserveAspectRatio="none">
                                                <defs>
                                                    <linearGradient id="heroGreenGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                                        <stop offset="0%" stop-color="#22c55e" stop-opacity="0.35" />
                                                        <stop offset="100%" stop-color="#22c55e" stop-opacity="0.0" />
                                                    </linearGradient>
                                                </defs>
                                                <!-- Filled Gradient Area -->
                                                <path d="M0,48 Q40,32 75,38 T150,22 T225,28 T300,8 L300,60 L0,60 Z" fill="url(#heroGreenGrad)" />
                                                <!-- Glowing Curve Line -->
                                                <path d="M0,48 Q40,32 75,38 T150,22 T225,28 T300,8" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" />
                                                <!-- Active Pulsing Target Point -->
                                                <circle cx="300" cy="8" r="4" fill="#cafd00" class="animate-pulse" />
                                                <circle cx="300" cy="8" r="8" stroke="#cafd00" stroke-width="1.5" opacity="0.6" class="animate-ping" />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Card 2: Live Gym Floor Capacity -->
                                    <div class="col-span-12 sm:col-span-5 bg-[#0f1726]/90 border border-cyan-500/30 rounded-xl p-3 sm:p-3.5 space-y-2 sm:space-y-2.5 shadow-lg flex flex-col justify-between">
                                        <div class="flex justify-between items-center text-[9px] sm:text-[10px] font-bold uppercase tracking-wider sm:tracking-widest text-gray-400">
                                            <span>Floor Density</span>
                                            <span class="text-cyan-400 font-mono">68/80</span>
                                        </div>
                                        <div>
                                            <div class="text-base sm:text-lg font-headline font-black text-cyan-400">85% Capacity</div>
                                            <div class="text-[9px] sm:text-[10px] text-gray-400">Peak Morning Rush Hour</div>
                                        </div>
                                        <!-- Capacity Segmented Bar -->
                                        <div class="w-full bg-surface-container-high rounded-full h-1.5 sm:h-2 overflow-hidden">
                                            <div class="bg-gradient-to-r from-cyan-400 via-emerald-400 to-[#cafd00] h-full rounded-full w-[85%]"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Live Operations Micro-Stream (Biometric Scan + WhatsApp Chaser) -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-2.5 text-xs font-body">
                                    <!-- Biometric Scan Toast -->
                                    <div class="bg-[#0b121e]/95 border border-cyan-500/30 rounded-xl p-2 sm:p-2.5 flex items-center gap-2 sm:gap-2.5 shadow-sm">
                                        <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center flex-shrink-0">
                                            <span class="material-symbols-outlined text-sm sm:text-base">fingerprint</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex justify-between items-center text-[10px] sm:text-[11px]">
                                                <span class="font-bold text-white truncate">Rohan Mehta</span>
                                                <span class="text-emerald-400 text-[9px] sm:text-[10px] font-mono font-bold">0.2s PASS</span>
                                            </div>
                                            <div class="text-[9px] sm:text-[10px] text-gray-400 truncate">Turnstile #1 • Gold Annual</div>
                                        </div>
                                    </div>

                                    <!-- WhatsApp Auto-Invoice Toast -->
                                    <div class="bg-[#0b121e]/95 border border-emerald-500/30 rounded-xl p-2 sm:p-2.5 flex items-center gap-2 sm:gap-2.5 shadow-sm">
                                        <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0">
                                            <span class="material-symbols-outlined text-sm sm:text-base">mark_chat_read</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex justify-between items-center text-[10px] sm:text-[11px]">
                                                <span class="font-bold text-white truncate">₹2,499 Collected</span>
                                                <span class="text-cyan-400 text-[9px] sm:text-[10px] font-mono">UPI QR</span>
                                            </div>
                                            <div class="text-[9px] sm:text-[10px] text-gray-400 truncate">WhatsApp GST Invoice Sent ✓✓</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom Live Simulation Status Bar -->
                                <div class="glass-card rounded-xl p-2 sm:p-2.5 flex items-center justify-between text-xs backdrop-blur-md border border-white/10">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-ping flex-shrink-0"></div>
                                        <span class="text-white font-bold text-[10px] sm:text-[11px] truncate">Biometric Gate Sync: <span class="text-emerald-400 font-medium">All Devices Online</span></span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 font-mono hidden sm:inline flex-shrink-0">0.2s Local Gateway Sync</span>
                                </div>
                            </div>
                        </div>

                        <!-- Top-Right Floating Badge: AUTOMATION READY (positioned safely on mobile without negative overflow) -->
                        <div class="absolute -top-3.5 right-1 sm:-top-5 sm:-right-5 bg-[#0c1322]/95 backdrop-blur-md border border-cyan-500/40 rounded-xl px-2.5 py-1.5 sm:px-4 sm:py-2.5 shadow-[0_0_20px_rgba(6,182,212,0.25)] transform rotate-[1.5deg] sm:rotate-[3deg] group-hover:rotate-0 group-hover:scale-105 transition-all duration-500 animate-float z-20 max-w-[85vw] sm:max-w-none">
                            <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider sm:tracking-widest text-gray-400">
                                AUTOMATION READY
                            </div>
                            <div class="text-xs sm:text-sm font-headline font-black text-cyan-400 tracking-wide drop-shadow-[0_0_8px_rgba(6,182,212,0.6)] flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm sm:text-base">chat</span>
                                <span class="truncate">WhatsApp & Social CRM</span>
                            </div>
                        </div>

                        <!-- Bottom-Left Floating Badge: LIVE CHECK-INS (positioned safely on mobile without negative overflow or covering sync text) -->
                        <div class="absolute -bottom-3.5 left-2 sm:-bottom-6 sm:-left-5 bg-[#0c1322]/95 backdrop-blur-md border border-emerald-500/40 rounded-xl px-2.5 py-1.5 sm:px-4 sm:py-2.5 shadow-[0_0_20px_rgba(16,185,129,0.25)] transform rotate-[-1.5deg] sm:rotate-[-3deg] group-hover:rotate-0 group-hover:scale-105 transition-all duration-500 animate-float z-20 max-w-[85vw] sm:max-w-none" style="animation-delay: 1.5s;">
                            <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider sm:tracking-widest text-gray-400">
                                LIVE CHECK-INS TODAY
                            </div>
                            <div class="text-xs sm:text-lg font-headline font-black text-emerald-400 flex items-center gap-1.5 drop-shadow-[0_0_8px_rgba(16,185,129,0.6)]">
                                <span class="w-1.5 sm:w-2 h-1.5 sm:h-2 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_8px_#34d399] flex-shrink-0"></span>
                                <span>1,240+ CHECKED IN</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Kinetic Capability Ticker Strip -->
    <section class="border-y border-outline-variant/20 bg-surface-container-lowest/80 backdrop-blur-md py-3.5 sm:py-4 overflow-hidden relative z-10">
        <div class="container mx-auto px-4 sm:px-6 max-w-8xl">
            <div class="flex flex-wrap items-center justify-center sm:justify-between gap-3 sm:gap-6 lg:gap-8 text-xs sm:text-sm font-semibold uppercase tracking-wider text-on-surface-variant">
                <div class="flex items-center gap-2 text-on-surface hover:text-emerald-400 transition-colors">
                    <span class="material-symbols-outlined text-emerald-400 text-base">chat</span>
                    <span>Automated WhatsApp Reminders</span>
                </div>
                <div class="flex items-center gap-2 text-on-surface hover:text-cyan-400 transition-colors">
                    <span class="material-symbols-outlined text-cyan-400 text-base">fingerprint</span>
                    <span>Biometric & QR Attendance</span>
                </div>
                <div class="flex items-center gap-2 text-on-surface hover:text-emerald-400 transition-colors">
                    <span class="material-symbols-outlined text-emerald-400 text-base">receipt_long</span>
                    <span>1-Click Invoicing & GST</span>
                </div>
                <div class="flex items-center gap-2 text-on-surface hover:text-cyan-400 transition-colors">
                    <span class="material-symbols-outlined text-cyan-400 text-base">hub</span>
                    <span>Social Media Lead CRM</span>
                </div>
                <div class="flex items-center gap-2 text-on-surface hover:text-emerald-400 transition-colors">
                    <span class="material-symbols-outlined text-emerald-400 text-base">domain</span>
                    <span>Multi-Branch Central Sync</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Interactive Live Simulation / Feature Showcase Tabs -->
    <section id="features" class="py-14 sm:py-20 relative overflow-hidden bg-surface-container-lowest" x-data="{ activeTab: 'whatsapp' }">
        <div class="container mx-auto px-4 sm:px-6 max-w-7xl relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-12 space-y-3">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-surface-container-high border border-outline-variant/30 text-xs font-bold uppercase tracking-widest text-primary">
                    <span>Interactive Live Simulation</span>
                </div>
                <h2 class="font-headline font-black text-2xl sm:text-4xl lg:text-5xl tracking-tight text-white uppercase leading-tight">
                    SEE HOW FLUXFITNESS RUNS <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-emerald-300 to-[#cafd00]">ON AUTOPILOT</span>
                </h2>
            </div>

            <!-- Tab Switcher Navigation -->
            <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-8 sm:mb-10 max-w-3xl mx-auto">
                <button @click="activeTab = 'whatsapp'" :class="activeTab === 'whatsapp' ? 'bg-[#22c55e] text-black shadow-[0_0_20px_rgba(34,197,94,0.4)]' : 'bg-surface-container-high text-gray-300 hover:text-white border border-white/10'" class="px-3.5 py-2 sm:px-5 sm:py-2.5 rounded-xl font-headline font-bold text-xs sm:text-sm uppercase tracking-wider transition-all cursor-pointer flex items-center gap-1.5 sm:gap-2">
                    <span class="material-symbols-outlined text-base">mark_chat_read</span>
                    <span>WhatsApp Due Chasers</span>
                </button>
                <button @click="activeTab = 'attendance'" :class="activeTab === 'attendance' ? 'bg-[#22c55e] text-black shadow-[0_0_20px_rgba(34,197,94,0.4)]' : 'bg-surface-container-high text-gray-300 hover:text-white border border-white/10'" class="px-3.5 py-2 sm:px-5 sm:py-2.5 rounded-xl font-headline font-bold text-xs sm:text-sm uppercase tracking-wider transition-all cursor-pointer flex items-center gap-1.5 sm:gap-2">
                    <span class="material-symbols-outlined text-base">fingerprint</span>
                    <span>Biometric Attendance</span>
                </button>
                <button @click="activeTab = 'crm'" :class="activeTab === 'crm' ? 'bg-[#22c55e] text-black shadow-[0_0_20px_rgba(34,197,94,0.4)]' : 'bg-surface-container-high text-gray-300 hover:text-white border border-white/10'" class="px-3.5 py-2 sm:px-5 sm:py-2.5 rounded-xl font-headline font-bold text-xs sm:text-sm uppercase tracking-wider transition-all cursor-pointer flex items-center gap-1.5 sm:gap-2">
                    <span class="material-symbols-outlined text-base">hub</span>
                    <span>Instagram Lead CRM</span>
                </button>
            </div>

            <!-- Tab 1: WhatsApp Automation -->
            <div x-show="activeTab === 'whatsapp'" x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-98" x-transition:enter-end="opacity-100 scale-100" class="glass-card rounded-2xl p-6 sm:p-10 border border-outline-variant/30 max-w-4xl mx-auto shadow-2xl">
                <div class="grid md:grid-cols-2 gap-8 items-center">
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-400">
                            <span class="material-symbols-outlined text-base">verified</span>
                            <span>Zero Manual Calls</span>
                        </div>
                        <h3 class="font-headline font-black text-2xl sm:text-3xl text-white">Automated Expiry Alerts & UPI Payment Links</h3>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            FluxFitness automatically tracks membership renewal dates, alerts members 3 days prior via official WhatsApp, and provides instant QR payment options.
                        </p>
                        <div class="pt-2 flex items-center gap-3 text-xs font-semibold text-white">
                            <span class="px-3 py-1 bg-emerald-500/20 border border-emerald-500/40 rounded-md text-emerald-400">92% Due Recovery Rate</span>
                            <span class="px-3 py-1 bg-cyan-500/20 border border-cyan-500/40 rounded-md text-cyan-400">Instant GST Invoices</span>
                        </div>
                    </div>

                    <!-- WhatsApp Live Preview UI -->
                    <div class="bg-[#0b141b] rounded-2xl p-4 border border-emerald-500/20 shadow-xl space-y-3 font-body">
                        <div class="flex items-center justify-between pb-2 border-b border-white/10 text-xs">
                            <div class="flex items-center gap-2 text-white font-bold">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                                <span>FluxFitness WhatsApp Bot</span>
                            </div>
                            <span class="text-emerald-400 font-bold text-[10px]">OFFICIAL VERIFIED</span>
                        </div>
                        <div class="bg-[#202c33] rounded-xl p-3.5 text-xs text-gray-200 space-y-2 border border-white/5">
                            <p class="text-xs">
                                "Hi <strong>Sameer</strong>! 👋 Your Gold Gym Membership at IronCore Fitness expires in <strong>3 days (14th Sep)</strong>."
                            </p>
                            <div class="bg-[#111b21] rounded-lg p-2.5 text-[11px] text-gray-300 border border-white/5 flex justify-between items-center">
                                <span>Plan Renewal: ₹2,499</span>
                                <span class="text-emerald-400 font-bold">Pay via UPI / GPay →</span>
                            </div>
                            <div class="text-[10px] text-gray-400 text-right">Delivered 10:45 AM • Read ✓✓</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Attendance Tracking -->
            <div x-show="activeTab === 'attendance'" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-98" x-transition:enter-end="opacity-100 scale-100" class="glass-card rounded-2xl p-6 sm:p-10 border border-outline-variant/30 max-w-4xl mx-auto shadow-2xl">
                <div class="grid md:grid-cols-2 gap-8 items-center">
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-cyan-400">
                            <span class="material-symbols-outlined text-base">sensors</span>
                            <span>Contactless Sync</span>
                        </div>
                        <h3 class="font-headline font-black text-2xl sm:text-3xl text-white">Biometric Fingerprint, Face & Dynamic QR Check-ins</h3>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            Eliminate register books and overcrowding. Real-time member verification stops unpaid members at the turnstile gate and tracks gym rush hours.
                        </p>
                        <div class="pt-2 flex items-center gap-3 text-xs font-semibold text-white">
                            <span class="px-3 py-1 bg-cyan-500/20 border border-cyan-500/40 rounded-md text-cyan-400">0.4s Check-in Speed</span>
                            <span class="px-3 py-1 bg-emerald-500/20 border border-emerald-500/40 rounded-md text-emerald-400">Auto Inactivity Nudges</span>
                        </div>
                    </div>

                    <!-- Attendance Heatmap Preview -->
                    <div class="bg-[#0c1322] rounded-2xl p-5 border border-cyan-500/20 shadow-xl space-y-4">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-white font-bold uppercase tracking-wider">Floor Density Analytics</span>
                            <span class="text-emerald-400 font-bold font-mono">LIVE PREVIEW</span>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between text-xs text-gray-400">
                                <span>Peak Morning Rush (6:30 AM - 9:30 AM)</span>
                                <span class="text-cyan-400 font-bold">85% Full</span>
                            </div>
                            <div class="w-full bg-surface-container-high rounded-full h-2.5">
                                <div class="bg-gradient-to-r from-cyan-400 to-emerald-400 h-full rounded-full w-[85%]"></div>
                            </div>
                        </div>
                        <div class="p-3 bg-surface-container-high rounded-xl text-xs flex justify-between items-center text-gray-300">
                            <span>Turnstile & QR Gate Check-ins</span>
                            <span class="text-emerald-400 font-bold font-mono">0.2s SYNC</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Meta Lead CRM -->
            <div x-show="activeTab === 'crm'" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-98" x-transition:enter-end="opacity-100 scale-100" class="glass-card rounded-2xl p-6 sm:p-10 border border-outline-variant/30 max-w-4xl mx-auto shadow-2xl">
                <div class="grid md:grid-cols-2 gap-8 items-center">
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-400">
                            <span class="material-symbols-outlined text-base">bolt</span>
                            <span>Instant Lead Capture</span>
                        </div>
                        <h3 class="font-headline font-black text-2xl sm:text-3xl text-white">Instagram & Facebook Lead Ads to WhatsApp Conversion</h3>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            Convert ad clicks into gym walk-ins within seconds. Leads from Meta automatically receive your gym price list and free pass before your competitors even respond.
                        </p>
                        <div class="pt-2 flex items-center gap-3 text-xs font-semibold text-white">
                            <span class="px-3 py-1 bg-emerald-500/20 border border-emerald-500/40 rounded-md text-emerald-400">4x Trial Show-up Rate</span>
                            <span class="px-3 py-1 bg-cyan-500/20 border border-cyan-500/40 rounded-md text-cyan-400">Auto Receptionist Alerts</span>
                        </div>
                    </div>

                    <!-- Lead Flow Preview -->
                    <div class="bg-[#0c1322] rounded-2xl p-5 border border-emerald-500/20 shadow-xl space-y-3">
                        <div class="flex justify-between items-center text-xs pb-2 border-b border-white/10">
                            <span class="text-white font-bold uppercase">Meta Lead Pipeline</span>
                            <span class="text-emerald-400 font-bold">LIVE AD CAMPAIGN</span>
                        </div>
                        <div class="p-3 bg-surface-container-high rounded-xl text-xs space-y-1">
                            <div class="flex justify-between font-bold text-white">
                                <span>Rohit Verma</span>
                                <span class="text-emerald-400">New Instagram Lead</span>
                            </div>
                            <div class="text-[11px] text-gray-400">Goal: Weight Loss • WhatsApp pass sent automatically</div>
                        </div>
                        <div class="p-3 bg-surface-container-high rounded-xl text-xs flex justify-between items-center text-gray-300">
                            <span>Status: <strong>Trial Session Scheduled Today</strong></span>
                            <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 font-bold rounded text-[10px]">High Intent</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Features Section -->
    <section id="core-features" class="py-16 sm:py-24 relative overflow-hidden bg-surface-container-low/40">
        <div class="container mx-auto px-4 sm:px-6 max-w-7xl relative z-10">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-20 space-y-3 sm:space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-surface-container-high border border-outline-variant/30 text-xs font-bold uppercase tracking-widest text-primary">
                    <span>Core System Modules</span>
                </div>
                <h2 class="font-headline font-extrabold text-2xl sm:text-4xl lg:text-5xl tracking-tight text-on-surface leading-tight">
                    BUILT SPECIFICALLY FOR <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-primary-container">INDIAN GYM OPERATIONS</span>
                </h2>
                <p class="text-on-surface-variant text-sm sm:text-base lg:text-lg">
                    Everything you need to run, scale, and automate your fitness center from day one.
                </p>
            </div>

            <!-- Features Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Feature 1: Social Media CRM -->
                <div id="crm-integration" class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group flex flex-col justify-between relative overflow-hidden">
                    <div class="space-y-6">
                        <div class="w-14 h-14 rounded-xl bg-surface-container-high border border-outline-variant/40 flex items-center justify-center text-primary group-hover:bg-primary-container group-hover:text-on-primary-fixed group-hover:scale-110 transition-all duration-300">
                            <span class="material-symbols-outlined text-3xl">filter_alt</span>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-headline font-bold text-2xl text-on-surface group-hover:text-primary transition-colors">
                                Meta & Social Lead CRM
                            </h3>
                            <p class="text-on-surface-variant text-sm leading-relaxed">
                                Automatically capture and pipe leads from Meta (Instagram & Facebook Lead Ads) into your sales pipeline without manual data entry.
                            </p>
                        </div>
                        <ul class="space-y-2.5 text-xs text-on-surface-variant pt-2">
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Instant Meta Ads Lead Sync</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Automated WhatsApp Welcome Message</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Follow-up Reminders & Trial Booking</span>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-4 border-t border-white/5 flex items-center justify-between text-xs font-semibold text-primary">
                        <span>Capture & Convert</span>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </div>

                <!-- Feature 2: Multi-Role Access -->
                <div id="user-management" class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group flex flex-col justify-between relative overflow-hidden">
                    <div class="space-y-6">
                        <div class="w-14 h-14 rounded-xl bg-surface-container-high border border-outline-variant/40 flex items-center justify-center text-primary group-hover:bg-primary-container group-hover:text-on-primary-fixed group-hover:scale-110 transition-all duration-300">
                            <span class="material-symbols-outlined text-3xl">badge</span>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-headline font-bold text-2xl text-on-surface group-hover:text-primary transition-colors">
                                Role-Based Staff Access
                            </h3>
                            <p class="text-on-surface-variant text-sm leading-relaxed">
                                Dedicated dashboards and strict permission layers for Gym Owners, Branch Managers, Personal Trainers, and Front Desk Receptionists.
                            </p>
                        </div>
                        <ul class="space-y-2.5 text-xs text-on-surface-variant pt-2">
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Granular role & branch permissions</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Staff attendance & shift logs</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Trainer client assignment & commission</span>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-4 border-t border-white/5 flex items-center justify-between text-xs font-semibold text-primary">
                        <span>Role Hierarchy</span>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </div>

                <!-- Feature 3: Smart Attendance -->
                <div id="attendance-tracking" class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group flex flex-col justify-between relative overflow-hidden">
                    <div class="space-y-6">
                        <div class="w-14 h-14 rounded-xl bg-surface-container-high border border-outline-variant/40 flex items-center justify-center text-primary group-hover:bg-primary-container group-hover:text-on-primary-fixed group-hover:scale-110 transition-all duration-300">
                            <span class="material-symbols-outlined text-3xl">fingerprint</span>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-headline font-bold text-2xl text-on-surface group-hover:text-primary transition-colors">
                                Biometric & QR Attendance
                            </h3>
                            <p class="text-on-surface-variant text-sm leading-relaxed">
                                Fast contactless check-ins via Biometric devices or dynamic QR codes. Track peak floor hours and detect inactive members automatically.
                            </p>
                        </div>
                        <ul class="space-y-2.5 text-xs text-on-surface-variant pt-2">
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Instant check-in notifications</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Peak hour heatmap & capacity stats</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Absenteeism alert triggers</span>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-4 border-t border-white/5 flex items-center justify-between text-xs font-semibold text-primary">
                        <span>Attendance & Biometric Sync</span>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </div>

                <!-- Feature 4: WhatsApp Billing & Automated Invoicing -->
                <div class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group flex flex-col justify-between relative overflow-hidden">
                    <div class="space-y-6">
                        <div class="w-14 h-14 rounded-xl bg-surface-container-high border border-outline-variant/40 flex items-center justify-center text-primary group-hover:bg-primary-container group-hover:text-on-primary-fixed group-hover:scale-110 transition-all duration-300">
                            <span class="material-symbols-outlined text-3xl">mark_chat_read</span>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-headline font-bold text-2xl text-on-surface group-hover:text-primary transition-colors">
                                WhatsApp Due Reminders & Billing
                            </h3>
                            <p class="text-on-surface-variant text-sm leading-relaxed">
                                Eliminate awkward payment collection calls. Send automated payment receipts, membership expiry warnings, and UPI payment links via official WhatsApp.
                            </p>
                        </div>
                        <ul class="space-y-2.5 text-xs text-on-surface-variant pt-2">
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>1-Click PDF Invoices with GST</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Automated 3-Day & 1-Day Expiry Alerts</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Direct UPI / Razorpay payment links</span>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-4 border-t border-white/5 flex items-center justify-between text-xs font-semibold text-primary">
                        <span>Payment Automation</span>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </div>

                <!-- Feature 5: Multi-Location Chains -->
                <div id="multi-location" class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group flex flex-col justify-between relative overflow-hidden">
                    <div class="space-y-6">
                        <div class="w-14 h-14 rounded-xl bg-surface-container-high border border-outline-variant/40 flex items-center justify-center text-primary group-hover:bg-primary-container group-hover:text-on-primary-fixed group-hover:scale-110 transition-all duration-300">
                            <span class="material-symbols-outlined text-3xl">store</span>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-headline font-bold text-2xl text-on-surface group-hover:text-primary transition-colors">
                                Multi-Branch Central Control
                            </h3>
                            <p class="text-on-surface-variant text-sm leading-relaxed">
                                Manage 1 gym or 50 gym branches from a unified owner dashboard. Compare branch profitability, staff performance, and cross-branch memberships.
                            </p>
                        </div>
                        <ul class="space-y-2.5 text-xs text-on-surface-variant pt-2">
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Consolidated multi-gym financial P&L</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Inter-branch roaming member passes</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Centralized plan & pricing catalogs</span>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-4 border-t border-white/5 flex items-center justify-between text-xs font-semibold text-primary">
                        <span>Scale Confidently</span>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </div>

                <!-- Feature 6: Real-Time Analytics -->
                <div class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group flex flex-col justify-between relative overflow-hidden">
                    <div class="space-y-6">
                        <div class="w-14 h-14 rounded-xl bg-surface-container-high border border-outline-variant/40 flex items-center justify-center text-primary group-hover:bg-primary-container group-hover:text-on-primary-fixed group-hover:scale-110 transition-all duration-300">
                            <span class="material-symbols-outlined text-3xl">query_stats</span>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-headline font-bold text-2xl text-on-surface group-hover:text-primary transition-colors">
                                Financial & Member Analytics
                            </h3>
                            <p class="text-on-surface-variant text-sm leading-relaxed">
                                Clear insights on member retention, monthly collections, trainer performance, and gym profitability.
                            </p>
                        </div>
                        <ul class="space-y-2.5 text-xs text-on-surface-variant pt-2">
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Member retention & drop-off heatmaps</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Expense & profitability tracking</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">check</span>
                                <span>Exportable Excel reports with 1 click</span>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-4 border-t border-white/5 flex items-center justify-between text-xs font-semibold text-primary">
                        <span>Business Reports</span>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Solutions Section (Why Choose Us & Interactive Impact Simulator) -->
    <section id="solutions" class="py-16 sm:py-24 bg-surface-container-lowest relative z-10 border-t border-outline-variant/20 overflow-hidden" x-data="{ 
        comparisonMode: 'flux',
        gymMembers: 350,
        activeStep: 1,
        autoPlay: true,
        selectedPreset: 350,
        timerId: null,
        
        setPreset(val) {
            this.selectedPreset = val;
            this.gymMembers = val;
        },
        get extraRevenue() { return '₹' + (this.gymMembers * 140).toLocaleString('en-IN') },
        get annualRevenue() { return '₹' + (this.gymMembers * 140 * 12).toLocaleString('en-IN') },
        get hoursSaved() { return Math.round(this.gymMembers * 0.08) + ' hrs' },
        get retentionBoost() { return '+18%' },
        get callsEliminated() { return (this.gymMembers * 3) + ' calls' }
    }" x-init="
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            timerId = setInterval(() => {
                if (autoPlay) {
                    activeStep = activeStep >= 4 ? 1 : activeStep + 1;
                }
            }, 4500);
        }
        $cleanup(() => {
            if (timerId) clearInterval(timerId);
        });
    ">
        <!-- Ambient Animated Kinetic Mesh & Glow Orbs -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[900px] h-[550px] bg-gradient-to-tr from-emerald-500/10 via-cyan-500/5 to-[#cafd00]/10 blur-[170px] rounded-full pointer-events-none animate-pulse"></div>
        <div class="absolute -bottom-20 -left-20 w-96 h-96 bg-primary-container/10 blur-[130px] rounded-full pointer-events-none"></div>

        <div class="container mx-auto px-4 sm:px-6 max-w-7xl relative z-10 space-y-16 sm:space-y-24">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto space-y-3 sm:space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-surface-container-high border border-primary/30 text-xs font-bold uppercase tracking-widest text-primary shadow-[0_0_15px_rgba(34,197,94,0.15)]">
                    <span class="w-2 h-2 rounded-full bg-primary animate-ping"></span>
                    <span>The Gym Management Solution</span>
                </div>
                <h2 class="font-headline font-extrabold text-2xl sm:text-4xl lg:text-5xl tracking-tight text-on-surface leading-tight">
                    WHY 500+ INDIAN GYMS <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-emerald-300 to-cyan-400">UPGRADE TO FLUXFITNESS</span>
                </h2>
                <p class="text-on-surface-variant text-sm sm:text-base lg:text-lg">
                    See how modern fitness clubs eliminate operational friction, stop revenue leaks, and automate member retention.
                </p>
            </div>

            <!-- INTERACTIVE PIPELINE: The 4-Stage Member Lifecycle -->
            <div class="glass-card rounded-3xl p-4 sm:p-8 lg:p-10 border border-primary/30 relative overflow-hidden shadow-2xl bg-gradient-to-b from-surface-container-high/60 via-surface-container-low/40 to-surface-container-high/60">
                <!-- Header & Controls -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-8 border-b border-white/10">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-primary">
                            <span class="material-symbols-outlined text-sm">cycle</span>
                            <span>Automated Member Journey</span>
                        </div>
                        <h3 class="font-headline font-black text-2xl sm:text-3xl text-white mt-1">
                            How A Member Moves On Autopilot
                        </h3>
                    </div>

                    <!-- Autoplay Toggle & Step Indicators -->
                    <div class="flex items-center gap-3">
                        <button type="button" @click="autoPlay = !autoPlay" :class="autoPlay ? 'bg-primary/20 text-primary border-primary/40' : 'bg-surface-container-high text-gray-400 border-white/10'" class="px-3.5 py-1.5 rounded-xl border text-xs font-bold uppercase tracking-wider flex items-center gap-1.5 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-base" x-text="autoPlay ? 'pause' : 'play_arrow'"></span>
                            <span x-text="autoPlay ? 'Autoplay ON' : 'Paused'"></span>
                        </button>
                    </div>
                </div>

                <!-- 4 Step Tabs / Stepper Progress Bar -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 my-8">
                    <!-- Step 1 Button -->
                    <button type="button" @click="activeStep = 1; autoPlay = false" :class="activeStep === 1 ? 'border-primary bg-primary/10 shadow-[0_0_20px_rgba(34,197,94,0.25)] text-white' : 'border-white/10 bg-surface-container-high/40 text-gray-400 hover:text-gray-200 hover:border-white/20'" class="p-4 rounded-2xl border text-left transition-all cursor-pointer relative group">
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black font-mono" :class="activeStep === 1 ? 'bg-primary text-black' : 'bg-surface-container-highest text-gray-400'">01</span>
                            <span class="material-symbols-outlined text-lg" :class="activeStep === 1 ? 'text-primary' : 'text-gray-500'">ads_click</span>
                        </div>
                        <div class="font-bold text-sm text-white">Lead Capture</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">Meta & WhatsApp sync</div>
                        <div x-show="activeStep === 1" class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-primary to-emerald-400 rounded-b-2xl"></div>
                    </button>

                    <!-- Step 2 Button -->
                    <button type="button" @click="activeStep = 2; autoPlay = false" :class="activeStep === 2 ? 'border-cyan-400 bg-cyan-400/10 shadow-[0_0_20px_rgba(34,211,238,0.25)] text-white' : 'border-white/10 bg-surface-container-high/40 text-gray-400 hover:text-gray-200 hover:border-white/20'" class="p-4 rounded-2xl border text-left transition-all cursor-pointer relative group">
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black font-mono" :class="activeStep === 2 ? 'bg-cyan-400 text-black' : 'bg-surface-container-highest text-gray-400'">02</span>
                            <span class="material-symbols-outlined text-lg" :class="activeStep === 2 ? 'text-cyan-400' : 'text-gray-500'">fingerprint</span>
                        </div>
                        <div class="font-bold text-sm text-white">Biometric Gate</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">Instant turnstile check</div>
                        <div x-show="activeStep === 2" class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-400 to-emerald-400 rounded-b-2xl"></div>
                    </button>

                    <!-- Step 3 Button -->
                    <button type="button" @click="activeStep = 3; autoPlay = false" :class="activeStep === 3 ? 'border-amber-400 bg-amber-400/10 shadow-[0_0_20px_rgba(251,191,36,0.25)] text-white' : 'border-white/10 bg-surface-container-high/40 text-gray-400 hover:text-gray-200 hover:border-white/20'" class="p-4 rounded-2xl border text-left transition-all cursor-pointer relative group">
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black font-mono" :class="activeStep === 3 ? 'bg-amber-400 text-black' : 'bg-surface-container-highest text-gray-400'">03</span>
                            <span class="material-symbols-outlined text-lg" :class="activeStep === 3 ? 'text-amber-400' : 'text-gray-500'">notifications_active</span>
                        </div>
                        <div class="font-bold text-sm text-white">Due Reminders</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">3-day pre-expiry alert</div>
                        <div x-show="activeStep === 3" class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 to-emerald-400 rounded-b-2xl"></div>
                    </button>

                    <!-- Step 4 Button -->
                    <button type="button" @click="activeStep = 4; autoPlay = false" :class="activeStep === 4 ? 'border-[#cafd00] bg-[#cafd00]/10 shadow-[0_0_20px_rgba(202,253,0,0.25)] text-white' : 'border-white/10 bg-surface-container-high/40 text-gray-400 hover:text-gray-200 hover:border-white/20'" class="p-4 rounded-2xl border text-left transition-all cursor-pointer relative group">
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black font-mono" :class="activeStep === 4 ? 'bg-[#cafd00] text-black' : 'bg-surface-container-highest text-gray-400'">04</span>
                            <span class="material-symbols-outlined text-lg" :class="activeStep === 4 ? 'text-[#cafd00]' : 'text-gray-500'">receipt_long</span>
                        </div>
                        <div class="font-bold text-sm text-white">UPI & GST Invoice</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">Instant renewal & tax bill</div>
                        <div x-show="activeStep === 4" class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-[#cafd00] to-primary rounded-b-2xl"></div>
                    </button>
                </div>

                <!-- Dynamic Step Content Stage -->
                <div class="bg-surface-container-lowest/80 rounded-2xl p-6 sm:p-8 border border-white/10 min-h-[300px] flex items-center">
                    <!-- Stage 1 Content -->
                    <div x-show="activeStep === 1" x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full grid md:grid-cols-12 gap-8 items-center">
                        <div class="md:col-span-7 space-y-4">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/30 text-xs font-bold text-primary">
                                <span class="material-symbols-outlined text-sm">bolt</span>
                                <span>Instant Lead Capture</span>
                            </div>
                            <h4 class="font-headline font-black text-2xl sm:text-3xl text-white">
                                Lead Clicks Ad → WhatsApp Day Pass Dispatched Instantly
                            </h4>
                            <p class="text-gray-300 text-sm leading-relaxed">
                                When a prospect fills out a Meta Lead Ad on Instagram, FluxFitness immediately creates their CRM lead profile and sends an official WhatsApp greeting with a 1-day free trial QR code.
                            </p>
                            <div class="flex flex-wrap items-center gap-3 pt-2 text-xs">
                                <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">schedule</span> 0.8s Response Time
                                </span>
                                <span class="px-3 py-1 bg-cyan-500/10 border border-cyan-500/30 rounded-lg text-cyan-400 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">qr_code_scanner</span> Dynamic Trial QR
                                </span>
                            </div>
                        </div>

                        <div class="md:col-span-5 bg-[#0b141b] rounded-2xl p-4 border border-emerald-500/30 shadow-2xl space-y-3 font-body">
                            <div class="flex items-center justify-between pb-2 border-b border-white/10 text-xs">
                                <div class="flex items-center gap-2 text-white font-bold">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span>Meta Lead Bot</span>
                                </div>
                                <span class="text-emerald-400 font-bold text-[10px]">VERIFIED LEAD</span>
                            </div>
                            <div class="bg-[#202c33] rounded-xl p-3 text-xs text-gray-200 space-y-2 border border-white/5">
                                <p class="text-xs">"Welcome <strong>Aditya</strong>! 🏋️ Thanks for inquiring on Instagram. Here is your <strong>Free 1-Day Trial Pass</strong> for IronCore Gym!"</p>
                                <div class="p-2.5 bg-[#111b21] rounded-lg border border-emerald-500/20 text-[11px] text-emerald-300 flex items-center justify-between">
                                    <span>Pass #IC-9924</span>
                                    <span class="font-bold">Valid for 48 Hours</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stage 2 Content -->
                    <div x-show="activeStep === 2" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full grid md:grid-cols-12 gap-8 items-center">
                        <div class="md:col-span-7 space-y-4">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-400/10 border border-cyan-400/30 text-xs font-bold text-cyan-400">
                                <span class="material-symbols-outlined text-sm">sensors</span>
                                <span>Hardware Synced</span>
                            </div>
                            <h4 class="font-headline font-black text-2xl sm:text-3xl text-white">
                                Contactless Biometric & Turnstile Gate Sync
                            </h4>
                            <p class="text-gray-300 text-sm leading-relaxed">
                                Unpaid or expired members are automatically stopped at the turnstile gate with an audio chime and on-screen renewal prompt. No awkward confrontations by your front-desk staff.
                            </p>
                            <div class="flex flex-wrap items-center gap-3 pt-2 text-xs">
                                <span class="px-3 py-1 bg-cyan-500/10 border border-cyan-500/30 rounded-lg text-cyan-400 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">speed</span> 0.3s Verification
                                </span>
                                <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">block</span> Auto Expired Deflection
                                </span>
                            </div>
                        </div>

                        <div class="md:col-span-5 bg-[#0a1118] rounded-2xl p-5 border border-cyan-500/30 shadow-2xl space-y-3 font-body">
                            <div class="flex items-center justify-between text-xs pb-2 border-b border-white/10">
                                <span class="text-white font-bold">BIOMETRIC & GATE ACCESS</span>
                                <span class="text-emerald-400 font-mono text-[10px]">STATUS: SYNCED</span>
                            </div>
                            <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-xs space-y-1">
                                <div class="flex justify-between font-bold text-emerald-400">
                                    <span>Access Granted: Turnstile #1</span>
                                    <span>06:42 AM</span>
                                </div>
                                <div class="text-gray-300 text-[11px]">Member: <strong>Rohan Mehta</strong> (Platinum 1-Year)</div>
                            </div>
                            <div class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-xs space-y-1">
                                <div class="flex justify-between font-bold text-rose-400">
                                    <span>Gate Locked: Expired Member</span>
                                    <span>06:45 AM</span>
                                </div>
                                <div class="text-gray-300 text-[11px]">Prompt: WhatsApp Renewal QR sent to phone</div>
                            </div>
                        </div>
                    </div>

                    <!-- Stage 3 Content -->
                    <div x-show="activeStep === 3" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full grid md:grid-cols-12 gap-8 items-center">
                        <div class="md:col-span-7 space-y-4">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-400/10 border border-amber-400/30 text-xs font-bold text-amber-400">
                                <span class="material-symbols-outlined text-sm">mark_chat_unread</span>
                                <span>Zero Awkward Calling</span>
                            </div>
                            <h4 class="font-headline font-black text-2xl sm:text-3xl text-white">
                                Proactive 3-Day WhatsApp Renewal Due Chaser
                            </h4>
                            <p class="text-gray-300 text-sm leading-relaxed">
                                FluxFitness automatically calculates membership expiration and sends polite, branded WhatsApp notices with a 1-tap UPI QR code, boosting fee recovery by 92%.
                            </p>
                            <div class="flex flex-wrap items-center gap-3 pt-2 text-xs">
                                <span class="px-3 py-1 bg-amber-500/10 border border-amber-500/30 rounded-lg text-amber-400 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">trending_up</span> 92% Recovery Rate
                                </span>
                                <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">qr_code</span> Dynamic UPI Payment
                                </span>
                            </div>
                        </div>

                        <div class="md:col-span-5 bg-[#0b141b] rounded-2xl p-4 border border-amber-500/30 shadow-2xl space-y-3 font-body">
                            <div class="flex items-center justify-between pb-2 border-b border-white/10 text-xs">
                                <span class="text-white font-bold">WHATSAPP BILLING & RENEWALS</span>
                                <span class="text-amber-400 font-bold text-[10px]">T-3 DAYS ALERT</span>
                            </div>
                            <div class="bg-[#202c33] rounded-xl p-3 text-xs text-gray-200 space-y-2 border border-white/5">
                                <p class="text-xs">"Hi <strong>Pooja</strong>! 🧘‍♀️ Your Gym Membership expires on <strong>Sunday</strong>. Renew now with 10% Early Bird Discount!"</p>
                                <div class="bg-[#111b21] rounded-lg p-2.5 text-[11px] text-gray-300 flex justify-between items-center border border-white/10">
                                    <span>Pay ₹1,999 via UPI</span>
                                    <span class="text-emerald-400 font-bold">Click to Pay →</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stage 4 Content -->
                    <div x-show="activeStep === 4" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full grid md:grid-cols-12 gap-8 items-center">
                        <div class="md:col-span-7 space-y-4">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#cafd00]/10 border border-[#cafd00]/30 text-xs font-bold text-[#cafd00]">
                                <span class="material-symbols-outlined text-sm">verified</span>
                                <span>Real-Time Settlement</span>
                            </div>
                            <h4 class="font-headline font-black text-2xl sm:text-3xl text-white">
                                Instant UPI Receipt & GST Tax Invoice Auto-Dispatched
                            </h4>
                            <p class="text-gray-300 text-sm leading-relaxed">
                                As soon as the payment lands in your Razorpay/UPI merchant account, member validity is automatically extended, and a formal GST invoice PDF is delivered to their WhatsApp instantly.
                            </p>
                            <div class="flex flex-wrap items-center gap-3 pt-2 text-xs">
                                <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">task_alt</span> Auto-Renew Extended
                                </span>
                                <span class="px-3 py-1 bg-cyan-500/10 border border-cyan-500/30 rounded-lg text-cyan-400 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">picture_as_pdf</span> 100% Compliant GST PDF
                                </span>
                            </div>
                        </div>

                        <div class="md:col-span-5 bg-[#0a141b] rounded-2xl p-4 border border-[#cafd00]/30 shadow-2xl space-y-3 font-body">
                            <div class="flex items-center justify-between pb-2 border-b border-white/10 text-xs">
                                <span class="text-white font-bold">GST INVOICE & UPI SETTLEMENT</span>
                                <span class="text-[#cafd00] font-mono text-[10px]">PAID & CLOSED</span>
                            </div>
                            <div class="p-3 bg-surface-container-high rounded-xl text-xs space-y-2 border border-white/5">
                                <div class="flex justify-between items-center text-white font-bold">
                                    <span>Invoice #INV-2026-883</span>
                                    <span class="text-emerald-400">₹1,999.00</span>
                                </div>
                                <div class="flex justify-between text-[11px] text-gray-400">
                                    <span>GSTIN: 27AAAAA0000A1Z5</span>
                                    <span>Auto-Sent via WhatsApp ✓✓</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comparison Switcher: Old Way vs FluxFitness Way -->
            <div class="max-w-5xl mx-auto glass-card rounded-3xl p-6 sm:p-10 border border-outline-variant/30 shadow-2xl relative overflow-hidden">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pb-6 border-b border-white/5">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest text-primary">Comparison</span>
                        <h3 class="font-headline font-bold text-xl sm:text-2xl text-white">Manual Register Chaos vs. FluxFitness</h3>
                    </div>

                    <!-- Interactive Mode Toggle -->
                    <div class="flex items-center bg-surface-container-high p-1 rounded-xl border border-outline-variant/40">
                        <button type="button" @click="comparisonMode = 'old'" :class="comparisonMode === 'old' ? 'bg-error/20 text-error border border-error/40 shadow-sm' : 'text-gray-400 hover:text-white'" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">warning</span>
                            <span>The Old Way</span>
                        </button>
                        <button type="button" @click="comparisonMode = 'flux'" :class="comparisonMode === 'flux' ? 'bg-[#22c55e] text-black font-black shadow-[0_0_15px_rgba(34,197,94,0.4)]' : 'text-gray-400 hover:text-white'" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">bolt</span>
                            <span>FluxFitness Way</span>
                        </button>
                    </div>
                </div>

                <!-- Old Way Content -->
                <div x-show="comparisonMode === 'old'" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="pt-6 grid sm:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl bg-error/5 border border-error/20 flex items-start gap-3 hover:bg-error/10 transition-colors">
                        <span class="material-symbols-outlined text-error text-xl mt-0.5">close</span>
                        <div>
                            <h4 class="font-bold text-white text-sm">Lost Cash & Missed Renewals</h4>
                            <p class="text-gray-400 text-xs mt-0.5">Members workout for weeks after membership expires because receptionists forget to check registers.</p>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-error/5 border border-error/20 flex items-start gap-3 hover:bg-error/10 transition-colors">
                        <span class="material-symbols-outlined text-error text-xl mt-0.5">close</span>
                        <div>
                            <h4 class="font-bold text-white text-sm">Awkward Payment Reminder Calls</h4>
                            <p class="text-gray-400 text-xs mt-0.5">Staff spends 3-4 hours daily manually calling members for overdue fees, causing friction.</p>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-error/5 border border-error/20 flex items-start gap-3 hover:bg-error/10 transition-colors">
                        <span class="material-symbols-outlined text-error text-xl mt-0.5">close</span>
                        <div>
                            <h4 class="font-bold text-white text-sm">Data Theft by Ex-Staff</h4>
                            <p class="text-gray-400 text-xs mt-0.5">Trainers and desk staff easily export your entire member contact directory when leaving.</p>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-error/5 border border-error/20 flex items-start gap-3 hover:bg-error/10 transition-colors">
                        <span class="material-symbols-outlined text-error text-xl mt-0.5">close</span>
                        <div>
                            <h4 class="font-bold text-white text-sm">Blind Floor Management</h4>
                            <p class="text-gray-400 text-xs mt-0.5">No real-time data on peak hours, member drop-offs, or trainer conversion rates.</p>
                        </div>
                    </div>
                </div>

                <!-- FluxFitness Way Content -->
                <div x-show="comparisonMode === 'flux'" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="pt-6 grid sm:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3 hover:bg-emerald-500/20 transition-all">
                        <span class="material-symbols-outlined text-emerald-400 text-xl mt-0.5">check_circle</span>
                        <div>
                            <h4 class="font-bold text-white text-sm">100% Automated WhatsApp Due Reminders</h4>
                            <p class="text-gray-300 text-xs mt-0.5">WhatsApp bot alerts members 3 days prior with instant UPI QR links. Zero manual calls needed.</p>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3 hover:bg-emerald-500/20 transition-all">
                        <span class="material-symbols-outlined text-emerald-400 text-xl mt-0.5">check_circle</span>
                        <div>
                            <h4 class="font-bold text-white text-sm">Biometric Gate Lock Integration</h4>
                            <p class="text-gray-300 text-xs mt-0.5">Contactless biometric and dynamic QR codes block expired members at the entry gate automatically.</p>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3 hover:bg-emerald-500/20 transition-all">
                        <span class="material-symbols-outlined text-emerald-400 text-xl mt-0.5">check_circle</span>
                        <div>
                            <h4 class="font-bold text-white text-sm">Role-Based Privacy & Contact Protection</h4>
                            <p class="text-gray-300 text-xs mt-0.5">Member phone numbers and revenue reports are masked and protected from rogue staff.</p>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3 hover:bg-emerald-500/20 transition-all">
                        <span class="material-symbols-outlined text-emerald-400 text-xl mt-0.5">check_circle</span>
                        <div>
                            <h4 class="font-bold text-white text-sm">Real-Time Floor Traffic & Heatmaps</h4>
                            <p class="text-gray-300 text-xs mt-0.5">Live headcount monitoring and automated retention alerts for members missing 7+ days.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interactive Gym Revenue & Time Recovery Calculator with Presets -->
            <div class="max-w-5xl mx-auto glass-card rounded-3xl p-8 sm:p-12 border border-primary/30 relative overflow-hidden shadow-2xl bg-gradient-to-br from-surface-container-high/40 via-surface-container-low/60 to-surface-container-high/40">
                <div class="absolute -right-20 -top-20 w-80 h-80 bg-primary-container/10 rounded-full blur-[100px] pointer-events-none"></div>

                <div class="relative z-10 grid lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                    <!-- Left: Interactive Slider Control & Quick Size Presets -->
                    <div class="lg:col-span-6 space-y-6">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-container/10 border border-primary/30 text-xs font-bold uppercase tracking-widest text-primary">
                            <span class="material-symbols-outlined text-base">calculate</span>
                            <span>Gym Growth & Revenue Calculator</span>
                        </div>
                        <h3 class="font-headline font-black text-2xl sm:text-3xl text-white">
                            Calculate Your Gym's Monthly Growth
                        </h3>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            Select your gym scale or drag the slider to simulate exactly how much revenue and staff hours FluxFitness will unlock for your facility.
                        </p>

                        <!-- Scale Quick Presets -->
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">Quick Gym Presets:</label>
                            <div class="grid grid-cols-4 gap-2">
                                <button type="button" @click="setPreset(150)" :class="selectedPreset === 150 ? 'bg-primary text-black font-bold border-primary' : 'bg-surface-container-high text-gray-300 border-white/10 hover:border-white/20'" class="p-2 rounded-xl text-xs border transition-all cursor-pointer text-center">
                                    150 <span class="text-[10px] block opacity-80">Studio</span>
                                </button>
                                <button type="button" @click="setPreset(350)" :class="selectedPreset === 350 ? 'bg-primary text-black font-bold border-primary' : 'bg-surface-container-high text-gray-300 border-white/10 hover:border-white/20'" class="p-2 rounded-xl text-xs border transition-all cursor-pointer text-center">
                                    350 <span class="text-[10px] block opacity-80">Medium</span>
                                </button>
                                <button type="button" @click="setPreset(750)" :class="selectedPreset === 750 ? 'bg-primary text-black font-bold border-primary' : 'bg-surface-container-high text-gray-300 border-white/10 hover:border-white/20'" class="p-2 rounded-xl text-xs border transition-all cursor-pointer text-center">
                                    750 <span class="text-[10px] block opacity-80">Large</span>
                                </button>
                                <button type="button" @click="setPreset(1200)" :class="selectedPreset === 1200 ? 'bg-primary text-black font-bold border-primary' : 'bg-surface-container-high text-gray-300 border-white/10 hover:border-white/20'" class="p-2 rounded-xl text-xs border transition-all cursor-pointer text-center">
                                    1200+ <span class="text-[10px] block opacity-80">Chain</span>
                                </button>
                            </div>
                        </div>

                        <!-- Interactive Range Slider -->
                        <div class="space-y-3 pt-2">
                            <div class="flex justify-between items-center text-sm font-bold">
                                <span class="text-gray-300">Active Member Count:</span>
                                <span class="text-2xl font-headline font-black text-primary" x-text="gymMembers + ' Members'"></span>
                            </div>
                            <input type="range" min="50" max="1500" step="25" x-model="gymMembers" @input="selectedPreset = null" class="w-full h-3 bg-surface-container-highest rounded-lg appearance-none cursor-pointer accent-[#22c55e]">
                            <div class="flex justify-between text-[11px] text-gray-500 font-mono">
                                <span>50 Members</span>
                                <span>500 Members</span>
                                <span>1500+ Members</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Live Animated Metric Cards -->
                    <div class="lg:col-span-6 grid grid-cols-2 gap-4">
                        <div class="col-span-2 p-6 rounded-2xl bg-[#0c1322] border border-emerald-500/40 space-y-2 shadow-2xl relative overflow-hidden group">
                            <div class="flex items-center justify-between text-xs font-bold uppercase tracking-widest text-gray-400">
                                <span>Recovered Dues Per Month</span>
                                <span class="material-symbols-outlined text-emerald-400 text-xl animate-bounce">trending_up</span>
                            </div>
                            <div class="text-3xl sm:text-4xl lg:text-5xl font-headline font-black text-emerald-400" x-text="extraRevenue"></div>
                            <div class="flex items-center justify-between text-xs text-gray-300 pt-1 border-t border-white/5">
                                <span>Annual Projected Gain:</span>
                                <span class="font-bold text-[#cafd00]" x-text="annualRevenue + ' / year'"></span>
                            </div>
                        </div>

                        <div class="p-4 rounded-2xl bg-surface-container-high/70 border border-white/10 space-y-1 hover:border-cyan-400/30 transition-all">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Staff Time Saved</div>
                            <div class="text-2xl font-headline font-black text-cyan-400" x-text="hoursSaved + '/mo'"></div>
                            <p class="text-[10px] text-gray-400">Zero manual register entry.</p>
                        </div>

                        <div class="p-4 rounded-2xl bg-surface-container-high/70 border border-white/10 space-y-1 hover:border-primary/30 transition-all">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Manual Calls Cut</div>
                            <div class="text-2xl font-headline font-black text-primary" x-text="callsEliminated"></div>
                            <p class="text-[10px] text-gray-400">Automated WhatsApp delivery.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6 Interactive Motion Solution Cards with Live Micro-Interactions -->
            <div id="solutions" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Sol 1: Simplify Everything -->
                <div class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group space-y-5 relative overflow-hidden hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(34,197,94,0.15)]" x-data="{ pinged: false }">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-primary-container/20 text-primary flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <span class="material-symbols-outlined text-2xl">auto_fix_high</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-high text-[10px] font-mono text-primary border border-primary/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                            Unified OS
                        </span>
                    </div>
                    <div>
                        <h3 class="font-headline font-bold text-xl text-on-surface group-hover:text-primary transition-colors">Simplify Everything</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed mt-2">
                            Consolidate member registrations, staff biometric attendance, fee collections, and lead pipelines into a single sleek app.
                        </p>
                    </div>
                    <!-- Live Micro-Widget with Interactive Action -->
                    <div class="p-3 bg-surface-container-high/60 rounded-xl text-xs flex justify-between items-center text-gray-300 border border-white/5">
                        <span>All 8 Modules Active</span>
                        <button type="button" @click="pinged = true; setTimeout(() => pinged = false, 2000)" class="text-emerald-400 font-bold hover:underline cursor-pointer">
                            <span x-text="pinged ? '✓ Synced 100%' : 'Check Sync Status'"></span>
                        </button>
                    </div>
                </div>

                <!-- Sol 2: Boost Monthly Cashflow -->
                <div class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group space-y-5 relative overflow-hidden hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(34,197,94,0.15)]" x-data="{ simulated: false }">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <span class="material-symbols-outlined text-2xl">payments</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-[10px] font-mono text-emerald-400 border border-emerald-500/20">
                            UPI QR Live
                        </span>
                    </div>
                    <div>
                        <h3 class="font-headline font-bold text-xl text-on-surface group-hover:text-emerald-400 transition-colors">Boost Monthly Cashflow</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed mt-2">
                            Recover unpaid membership dues with automated WhatsApp chasers that collect via instant UPI QR codes.
                        </p>
                    </div>
                    <!-- Live Micro-Widget -->
                    <div class="p-3 bg-emerald-500/10 rounded-xl text-xs flex justify-between items-center text-emerald-300 border border-emerald-500/20">
                        <span>Auto-Chaser: <strong>₹2,499 Due</strong></span>
                        <button type="button" @click="simulated = true; setTimeout(() => simulated = false, 2500)" class="text-emerald-400 font-bold hover:underline cursor-pointer">
                            <span x-text="simulated ? '✓ Paid via GPay' : 'Simulate 1-Tap Pay'"></span>
                        </button>
                    </div>
                </div>

                <!-- Sol 3: Zero Data Leakage -->
                <div class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group space-y-5 relative overflow-hidden hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(34,197,94,0.15)]" x-data="{ masked: true }">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <span class="material-symbols-outlined text-2xl">security</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-cyan-500/10 text-[10px] font-mono text-cyan-400 border border-cyan-500/20">
                            AES-256
                        </span>
                    </div>
                    <div>
                        <h3 class="font-headline font-bold text-xl text-on-surface group-hover:text-cyan-400 transition-colors">Zero Data Leakage</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed mt-2">
                            Protect member phone numbers and gym revenue data from staff theft with strict role permissions and encryption.
                        </p>
                    </div>
                    <!-- Live Micro-Widget -->
                    <div class="p-3 bg-surface-container-high/60 rounded-xl text-xs flex justify-between items-center text-gray-300 border border-white/5">
                        <span x-text="masked ? '+91 ••••• ••492' : '+91 98200 12492'"></span>
                        <button type="button" @click="masked = !masked" class="text-cyan-400 font-bold hover:underline cursor-pointer">
                            <span x-text="masked ? 'Owner Unlock 🔓' : 'Mask Contact 🔒'"></span>
                        </button>
                    </div>
                </div>

                <!-- Sol 4: Built for Multi-Branch Growth -->
                <div class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group space-y-5 relative overflow-hidden hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(34,197,94,0.15)]" x-data="{ branch: 'Bandra' }">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-primary-container/20 text-primary flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <span class="material-symbols-outlined text-2xl">moving</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-high text-[10px] font-mono text-primary border border-primary/20">
                            Multi-Gym
                        </span>
                    </div>
                    <div>
                        <h3 class="font-headline font-bold text-xl text-on-surface group-hover:text-primary transition-colors">Built for Growth</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed mt-2">
                            Whether you operate a 150-member studio or a 10-branch fitness chain, FluxFitness scales seamlessly with your business.
                        </p>
                    </div>
                    <!-- Live Micro-Widget -->
                    <div class="p-3 bg-surface-container-high/60 rounded-xl text-xs flex justify-between items-center text-gray-300 border border-white/5">
                        <span>Current Branch: <strong class="text-white" x-text="branch"></strong></span>
                        <button type="button" @click="branch = branch === 'Bandra' ? 'Andheri West' : 'Bandra'" class="text-primary font-bold hover:underline cursor-pointer">
                            Switch Branch ⇄
                        </button>
                    </div>
                </div>

                <!-- Sol 5: Dedicated Support -->
                <div class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group space-y-5 relative overflow-hidden hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(34,197,94,0.15)]">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <span class="material-symbols-outlined text-2xl">support_agent</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-[10px] font-mono text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Online
                        </span>
                    </div>
                    <div>
                        <h3 class="font-headline font-bold text-xl text-on-surface group-hover:text-emerald-400 transition-colors">Dedicated Phone Support</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed mt-2">
                            Our Indian support engineers are just a WhatsApp message or phone call away, with free staff training and onboarding assistance.
                        </p>
                    </div>
                    <!-- Live Micro-Widget -->
                    <div class="p-3 bg-emerald-500/10 rounded-xl text-xs flex justify-between items-center text-emerald-300 border border-emerald-500/20">
                        <span>Avg WhatsApp Reply:</span>
                        <span class="text-emerald-400 font-bold">< 2 Minutes</span>
                    </div>
                </div>

                <!-- Sol 6: Instant 24h Migration -->
                <div class="glass-card rounded-2xl p-8 hover:border-primary/50 transition-all duration-500 group space-y-5 relative overflow-hidden hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(34,197,94,0.15)]" x-data="{ imported: false }">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <span class="material-symbols-outlined text-2xl">flash_on</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-cyan-500/10 text-[10px] font-mono text-cyan-400 border border-cyan-500/20">
                            Free Migration
                        </span>
                    </div>
                    <div>
                        <h3 class="font-headline font-bold text-xl text-on-surface group-hover:text-cyan-400 transition-colors">Instant 24h Migration</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed mt-2">
                            Switching from another software? Send us your Excel sheet and our team will import all your members within 24 hours.
                        </p>
                    </div>
                    <!-- Live Micro-Widget -->
                    <div class="p-3 bg-surface-container-high/60 rounded-xl text-xs flex justify-between items-center text-gray-300 border border-white/5">
                        <span x-text="imported ? '✓ 450 Members Imported' : 'Excel / CSV Ready'"></span>
                        <button type="button" @click="imported = true; setTimeout(() => imported = false, 2500)" class="text-cyan-400 font-bold hover:underline cursor-pointer">
                            <span x-text="imported ? 'Done!' : 'Test Import'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-16 sm:py-24 relative overflow-hidden bg-surface">
        <div class="container mx-auto px-4 sm:px-6 max-w-7xl relative z-10">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-16 space-y-3 sm:space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-surface-container-high border border-outline-variant/30 text-xs font-bold uppercase tracking-widest text-primary">
                    <span>Transparent Pricing</span>
                </div>
                <h2 class="font-headline font-extrabold text-2xl sm:text-4xl lg:text-5xl tracking-tight text-on-surface leading-tight">
                    SIMPLE, PREDICTABLE <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-primary-container">PLANS FOR YOUR GYM</span>
                </h2>
                <p class="text-on-surface-variant text-sm sm:text-base lg:text-lg">
                    No hidden setup fees. No long-term lock-in. Cancel anytime.
                </p>
            </div>

            <!-- Standard Plans Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto items-stretch">
                @foreach ($this->plans as $index => $plan)
                    @php
                        $isFeatured = !empty($plan->badge);
                    @endphp
                    <div class="relative flex flex-col justify-between rounded-2xl p-8 transition-all duration-500 {{ $isFeatured ? 'glass-card border-2 border-primary shadow-[0_0_40px_rgba(202,253,0,0.15)] lg:-translate-y-2' : 'bg-surface-container-low border border-outline-variant/30 hover:border-outline-variant/60' }} group">
                        
                        @if ($isFeatured)
                            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 z-10">
                                <span class="bg-primary-container text-on-primary-fixed px-4 py-1 rounded-full text-xs font-headline font-black uppercase tracking-widest shadow-lg shadow-primary/30">
                                    {{ $plan->badge }}
                                </span>
                            </div>
                        @endif

                        <div class="space-y-6">
                            <!-- Plan Title & Description -->
                            <div>
                                <h3 class="font-headline font-bold text-2xl text-on-surface mb-2">{{ $plan->name }}</h3>
                                <p class="text-on-surface-variant text-xs sm:text-sm leading-relaxed min-h-[40px]">{{ $plan->brief_description }}</p>
                            </div>

                            <!-- Pricing Display -->
                            <div class="pb-6 border-b border-white/5">
                                @if ($plan->isFree())
                                    <div class="flex items-baseline gap-1">
                                        <span class="font-headline font-black text-4xl sm:text-5xl text-on-surface">Free</span>
                                        <span class="text-xs text-on-surface-variant font-medium">/Forever</span>
                                    </div>
                                @else
                                    <div class="flex items-baseline gap-1">
                                        <span class="font-headline font-black text-4xl sm:text-5xl text-on-surface">
                                            {{ $plan->currency->getSymbol() }}{{ number_format($plan->price_yearly) }}
                                        </span>
                                        <span class="text-xs text-on-surface-variant font-medium">/Year</span>
                                    </div>
                                @endif

                                @if ($plan->hasTrial())
                                    <div class="mt-2 text-xs text-primary font-bold inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">verified</span>
                                        <span>{{ $plan->trial_days }} Days Risk-Free Trial Included</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Feature List -->
                            <div class="space-y-3.5">
                                <div class="text-xs font-bold uppercase tracking-widest text-on-surface">Included Features:</div>
                                <ul class="space-y-3 text-xs sm:text-sm text-on-surface-variant">
                                    @foreach ($plan->features as $feature)
                                        <li class="flex items-start gap-2.5">
                                            <span class="material-symbols-outlined text-primary text-base flex-shrink-0 mt-0.5">check_circle</span>
                                            <span class="text-on-surface leading-snug">{!! $feature->brief_description !!}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <!-- CTA Action Button -->
                        <div class="pt-8 mt-8 border-t border-white/5">
                            <button wire:click="selectPlan({{ $plan->id }})"
                                class="cursor-pointer w-full py-4 rounded-xl font-headline font-bold text-sm sm:text-base uppercase tracking-wider transition-all duration-300 {{ $isFeatured ? 'bg-primary-container text-on-primary-fixed shadow-lg shadow-primary/20 hover:brightness-110 active:scale-[0.98]' : 'bg-surface-container-high text-on-surface border border-outline-variant/40 hover:bg-surface-container-highest hover:border-primary/50' }}">
                                <span wire:loading.remove wire:target="selectPlan({{ $plan->id }})">
                                    {{ $plan->hasTrial() ? 'Start Free Trial' : ($plan->isFree() ? 'Get Started Free' : 'Choose ' . $plan->name) }}
                                </span>
                                <x-loading-icon target="selectPlan({{ $plan->id }})" variant="text" name="Loading" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Enterprise Bespoke Card -->
            @if($this->enterprisePlans->count() > 0)
                <div class="mt-16 max-w-5xl mx-auto">
                    @foreach($this->enterprisePlans as $enterprisePlan)
                        <div class="glass-card rounded-3xl p-8 sm:p-12 border border-primary/30 relative overflow-hidden group">
                            <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-primary-container/10 rounded-full blur-[100px] pointer-events-none"></div>
                            
                            <div class="relative z-10 grid lg:grid-cols-12 gap-8 items-center">
                                <div class="lg:col-span-8 space-y-4">
                                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-container/10 border border-primary/30 text-xs font-bold uppercase tracking-widest text-primary">
                                        <span>{{ $enterprisePlan->badge ?? 'ENTERPRISE & FRANCHISE' }}</span>
                                    </div>
                                    <h3 class="font-headline font-extrabold text-3xl sm:text-4xl text-on-surface">
                                        {{ $enterprisePlan->name }}
                                    </h3>
                                    <div class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                                        {!! $enterprisePlan->description ?? $enterprisePlan->brief_description !!}
                                    </div>
                                    
                                    @if($enterprisePlan->features->count() > 0)
                                        <div class="grid sm:grid-cols-2 gap-3 pt-3">
                                            @foreach($enterprisePlan->features as $feature)
                                                <div class="flex items-center gap-2 text-xs sm:text-sm text-on-surface">
                                                    <span class="material-symbols-outlined text-primary text-base">verified</span>
                                                    <span>{!! $feature->brief_description !!}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="lg:col-span-4 flex flex-col items-center lg:items-end justify-center text-center lg:text-right space-y-4 pt-4 lg:pt-0">
                                    <div class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Custom Multi-Gym Setup</div>
                                    <div class="font-headline font-bold text-3xl text-on-surface">Tailored Quote</div>
                                    <a href="#contact"
                                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-primary-container text-on-primary-fixed px-8 py-4 rounded-xl font-headline font-bold text-base uppercase tracking-wider shadow-lg shadow-primary/20 hover:brightness-110 active:scale-[0.98] transition-all">
                                        <span>Talk to Sales</span>
                                        <span class="material-symbols-outlined text-lg">call</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- FAQ Section (Interactive Search & Categories) -->
    <section id="faq" class="py-16 sm:py-24 bg-surface-container-low/30 relative z-10 border-t border-outline-variant/20 overflow-hidden" x-data="{ 
        activeAccordion: 1,
        searchQuery: '',
        activeCategory: 'all',
        allOpen: false,

        toggleAll() {
            this.allOpen = !this.allOpen;
            this.activeAccordion = this.allOpen ? 'all' : null;
        },

        isOpen(id) {
            return this.activeAccordion === 'all' || this.activeAccordion === id;
        },

        toggle(id) {
            if (this.activeAccordion === 'all') {
                this.activeAccordion = id;
            } else {
                this.activeAccordion = this.activeAccordion === id ? null : id;
            }
            this.allOpen = (this.activeAccordion === 'all');
        },

        matches(itemCategory, title, body) {
            const matchesCategory = this.activeCategory === 'all' || this.activeCategory === itemCategory;
            const query = this.searchQuery.trim().toLowerCase();
            const matchesQuery = query === '' || title.toLowerCase().includes(query) || body.toLowerCase().includes(query);
            return matchesCategory && matchesQuery;
        }
    }">
        <!-- Ambient Kinetic Glow -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[400px] bg-primary/5 blur-[150px] rounded-full pointer-events-none"></div>

        <div class="container mx-auto px-4 sm:px-6 max-w-5xl relative z-10 space-y-8 sm:space-y-12">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto space-y-3 sm:space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-surface-container-high border border-primary/30 text-xs font-bold uppercase tracking-widest text-primary shadow-[0_0_15px_rgba(34,197,94,0.15)]">
                    <span class="material-symbols-outlined text-sm">help</span>
                    <span>Frequently Asked Questions</span>
                </div>
                <h2 class="font-headline font-extrabold text-2xl sm:text-4xl lg:text-5xl tracking-tight text-on-surface leading-tight">
                    FREQUENTLY ASKED <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-emerald-300 to-cyan-400">QUESTIONS</span>
                </h2>
                <p class="text-on-surface-variant text-sm sm:text-base lg:text-lg">
                    Everything you need to know about setting up, automating, and scaling your gym with FluxFitness.
                </p>
            </div>

            <!-- Interactive Search & Filter Controls -->
            <div class="glass-card rounded-2xl p-4 sm:p-6 border border-white/10 shadow-xl space-y-4 max-w-4xl mx-auto">
                <!-- Search Input Bar -->
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xl pointer-events-none">search</span>
                    <input 
                        type="text" 
                        x-model="searchQuery" 
                        placeholder="Search any question (e.g. WhatsApp, Biometric, Excel import, Pricing, Invoices)..." 
                        class="w-full pl-12 pr-10 py-3.5 bg-surface-container-high/80 border border-white/10 focus:border-primary rounded-xl text-sm text-white placeholder-gray-400 outline-none transition-all focus:ring-2 focus:ring-primary/20"
                    >
                    <button 
                        type="button" 
                        x-show="searchQuery.length > 0" 
                        @click="searchQuery = ''" 
                        class="material-symbols-outlined absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-lg cursor-pointer"
                    >
                        cancel
                    </button>
                </div>

                <!-- Category Filter Pills & Toggle All -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-white/5">
                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <button type="button" @click="activeCategory = 'all'" :class="activeCategory === 'all' ? 'bg-primary text-black font-bold shadow-[0_0_12px_rgba(34,197,94,0.3)]' : 'bg-surface-container-high text-gray-300 hover:text-white border border-white/5'" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold tracking-wider transition-all cursor-pointer">
                            All Questions
                        </button>
                        <button type="button" @click="activeCategory = 'whatsapp'" :class="activeCategory === 'whatsapp' ? 'bg-emerald-400 text-black font-bold shadow-[0_0_12px_rgba(52,211,153,0.3)]' : 'bg-surface-container-high text-gray-300 hover:text-white border border-white/5'" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold tracking-wider transition-all cursor-pointer flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">mark_chat_read</span> WhatsApp & Billing
                        </button>
                        <button type="button" @click="activeCategory = 'hardware'" :class="activeCategory === 'hardware' ? 'bg-cyan-400 text-black font-bold shadow-[0_0_12px_rgba(34,211,238,0.3)]' : 'bg-surface-container-high text-gray-300 hover:text-white border border-white/5'" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold tracking-wider transition-all cursor-pointer flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">fingerprint</span> Biometric Hardware
                        </button>
                        <button type="button" @click="activeCategory = 'migration'" :class="activeCategory === 'migration' ? 'bg-amber-400 text-black font-bold shadow-[0_0_12px_rgba(251,191,36,0.3)]' : 'bg-surface-container-high text-gray-300 hover:text-white border border-white/5'" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold tracking-wider transition-all cursor-pointer flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">upload_file</span> Excel & Security
                        </button>
                        <button type="button" @click="activeCategory = 'multigym'" :class="activeCategory === 'multigym' ? 'bg-purple-400 text-black font-bold shadow-[0_0_12px_rgba(192,132,252,0.3)]' : 'bg-surface-container-high text-gray-300 hover:text-white border border-white/5'" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold tracking-wider transition-all cursor-pointer flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">domain</span> Multi-Branch
                        </button>
                    </div>

                    <!-- Expand / Collapse All -->
                    <button type="button" @click="toggleAll" class="text-xs text-gray-400 hover:text-primary transition-colors cursor-pointer flex items-center gap-1 font-semibold">
                        <span class="material-symbols-outlined text-base" x-text="allOpen ? 'unfold_less' : 'unfold_more'"></span>
                        <span x-text="allOpen ? 'Collapse All' : 'Expand All'"></span>
                    </button>
                </div>
            </div>

            <!-- FAQ Items Accordion -->
            <div class="space-y-4 max-w-4xl mx-auto">
                <!-- FAQ 1: Free Trial -->
                <div x-show="matches('trial', 'How does the 14-day free trial work?', 'You get unrestricted access to all features including member tracking, biometric check-ins, and automated billing for 14 full days. No credit card required to sign up.')" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="glass-card rounded-2xl border transition-all duration-300 overflow-hidden" 
                    :class="isOpen(1) ? 'border-primary/50 bg-surface-container-high/60 shadow-[0_0_25px_rgba(34,197,94,0.1)]' : 'border-outline-variant/30 hover:border-outline-variant/60'"
                    x-data="{ feedback: null }"
                >
                    <button @click="toggle(1)" :aria-expanded="String(isOpen(1))" aria-controls="faq-answer-1" type="button" class="w-full p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center text-sm font-bold flex-shrink-0">Q1</span>
                            <span class="font-headline font-bold text-base sm:text-lg text-on-surface">How does the 14-day free trial work?</span>
                        </div>
                        <span class="material-symbols-outlined text-primary text-2xl transition-transform duration-300" :class="isOpen(1) ? 'rotate-180 text-primary' : 'text-gray-400'">expand_more</span>
                    </button>
                    <div x-show="isOpen(1)" id="faq-answer-1" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 pb-6 pt-2 text-sm text-on-surface-variant leading-relaxed border-t border-white/5 space-y-4">
                        <p>
                            You get <strong>100% unrestricted access</strong> to every feature (WhatsApp due chasers, biometric check-ins, multi-trainer commissions, and GST invoicing) for 14 full days. There is <strong>no credit card or upfront payment required</strong>.
                        </p>
                        <div class="flex items-center justify-between pt-3 text-xs border-t border-white/5 text-gray-400">
                            <span class="inline-flex items-center gap-1.5 text-emerald-400 font-semibold">
                                <span class="material-symbols-outlined text-sm">verified</span> Risk-Free Onboarding
                            </span>
                            <div class="flex items-center gap-2">
                                <span>Was this helpful?</span>
                                <button type="button" @click="feedback = 'yes'" :class="feedback === 'yes' ? 'text-emerald-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👍 Yes</button>
                                <span>•</span>
                                <button type="button" @click="feedback = 'no'" :class="feedback === 'no' ? 'text-rose-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👎 No</button>
                                <span x-show="feedback" class="text-primary text-[11px] font-mono ml-1">Thanks!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 2: WhatsApp Automation -->
                <div x-show="matches('whatsapp', 'How does WhatsApp automated payment reminders and UPI collection work?', 'FluxFitness connects with official WhatsApp Business API to automatically notify members 3 days and 1 day before plan expiry with instant UPI QR payment links.')" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="glass-card rounded-2xl border transition-all duration-300 overflow-hidden" 
                    :class="isOpen(2) ? 'border-emerald-500/50 bg-surface-container-high/60 shadow-[0_0_25px_rgba(16,185,129,0.1)]' : 'border-outline-variant/30 hover:border-outline-variant/60'"
                    x-data="{ feedback: null }"
                >
                    <button @click="toggle(2)" :aria-expanded="String(isOpen(2))" aria-controls="faq-answer-2" type="button" class="w-full p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-sm font-bold flex-shrink-0">Q2</span>
                            <span class="font-headline font-bold text-base sm:text-lg text-on-surface">How does WhatsApp automated payment reminders and UPI collection work?</span>
                        </div>
                        <span class="material-symbols-outlined text-emerald-400 text-2xl transition-transform duration-300" :class="isOpen(2) ? 'rotate-180 text-emerald-400' : 'text-gray-400'">expand_more</span>
                    </button>
                    <div x-show="isOpen(2)" id="faq-answer-2" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 pb-6 pt-2 text-sm text-on-surface-variant leading-relaxed border-t border-white/5 space-y-4">
                        <p>
                            FluxFitness connects directly with verified WhatsApp messaging. The system tracks member expiration dates and automatically dispatches polite reminders <strong>3 days and 1 day prior</strong>, embedded with a dynamic UPI QR code (supporting GPay, PhonePe, and Paytm). When members pay, their validity is renewed in real-time and a GST receipt is pushed back instantly.
                        </p>
                        <div class="flex items-center justify-between pt-3 text-xs border-t border-white/5 text-gray-400">
                            <span class="inline-flex items-center gap-1.5 text-emerald-400 font-semibold">
                                <span class="material-symbols-outlined text-sm">mark_chat_read</span> 92% Average Fee Recovery
                            </span>
                            <div class="flex items-center gap-2">
                                <span>Was this helpful?</span>
                                <button type="button" @click="feedback = 'yes'" :class="feedback === 'yes' ? 'text-emerald-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👍 Yes</button>
                                <span>•</span>
                                <button type="button" @click="feedback = 'no'" :class="feedback === 'no' ? 'text-rose-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👎 No</button>
                                <span x-show="feedback" class="text-primary text-[11px] font-mono ml-1">Thanks!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 3: Biometric Hardware -->
                <div x-show="matches('hardware', 'Does FluxFitness support Biometric fingerprint, face recognition & turnstile gates?', 'Yes, FluxFitness connects with standard biometric fingerprint, RFID, facial recognition machines, and dynamic QR scanners.')" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="glass-card rounded-2xl border transition-all duration-300 overflow-hidden" 
                    :class="isOpen(3) ? 'border-cyan-500/50 bg-surface-container-high/60 shadow-[0_0_25px_rgba(6,182,212,0.1)]' : 'border-outline-variant/30 hover:border-outline-variant/60'"
                    x-data="{ feedback: null }"
                >
                    <button @click="toggle(3)" :aria-expanded="String(isOpen(3))" aria-controls="faq-answer-3" type="button" class="w-full p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-sm font-bold flex-shrink-0">Q3</span>
                            <span class="font-headline font-bold text-base sm:text-lg text-on-surface">Does FluxFitness connect with biometric fingerprint & face turnstile gates?</span>
                        </div>
                        <span class="material-symbols-outlined text-cyan-400 text-2xl transition-transform duration-300" :class="isOpen(3) ? 'rotate-180 text-cyan-400' : 'text-gray-400'">expand_more</span>
                    </button>
                    <div x-show="isOpen(3)" id="faq-answer-3" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 pb-6 pt-2 text-sm text-on-surface-variant leading-relaxed border-t border-white/5 space-y-4">
                        <p>
                            Yes! FluxFitness integrates with popular biometric brands (such as eSSL, Realtime, ZKTeco, and Hikvision) as well as turnstile tripod gates. When an expired member checks in, the turnstile automatically denies entry and sends a payment prompt to their phone.
                        </p>
                        <div class="flex items-center justify-between pt-3 text-xs border-t border-white/5 text-gray-400">
                            <span class="inline-flex items-center gap-1.5 text-cyan-400 font-semibold">
                                <span class="material-symbols-outlined text-sm">sensors</span> 0.3s Hardware Sync
                            </span>
                            <div class="flex items-center gap-2">
                                <span>Was this helpful?</span>
                                <button type="button" @click="feedback = 'yes'" :class="feedback === 'yes' ? 'text-emerald-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👍 Yes</button>
                                <span>•</span>
                                <button type="button" @click="feedback = 'no'" :class="feedback === 'no' ? 'text-rose-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👎 No</button>
                                <span x-show="feedback" class="text-primary text-[11px] font-mono ml-1">Thanks!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 4: Excel & Software Migration -->
                <div x-show="matches('migration', 'Can we import our existing members from Excel or previous software?', 'Yes! You can upload your CSV/Excel sheet or share it with our migration team on WhatsApp. All member dates, dues, and contact info are migrated within 24 hours free of charge.')" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="glass-card rounded-2xl border transition-all duration-300 overflow-hidden" 
                    :class="isOpen(4) ? 'border-amber-500/50 bg-surface-container-high/60 shadow-[0_0_25px_rgba(245,158,11,0.1)]' : 'border-outline-variant/30 hover:border-outline-variant/60'"
                    x-data="{ feedback: null }"
                >
                    <button @click="toggle(4)" :aria-expanded="String(isOpen(4))" aria-controls="faq-answer-4" type="button" class="w-full p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm font-bold flex-shrink-0">Q4</span>
                            <span class="font-headline font-bold text-base sm:text-lg text-on-surface">Can we import our existing members from Excel or previous software?</span>
                        </div>
                        <span class="material-symbols-outlined text-amber-400 text-2xl transition-transform duration-300" :class="isOpen(4) ? 'rotate-180 text-amber-400' : 'text-gray-400'">expand_more</span>
                    </button>
                    <div x-show="isOpen(4)" id="faq-answer-4" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 pb-6 pt-2 text-sm text-on-surface-variant leading-relaxed border-t border-white/5 space-y-4">
                        <p>
                            Absolutely. You can use our built-in 1-click Excel/CSV importer, or simply send your spreadsheets to our dedicated onboarding specialist on WhatsApp. We map all past payment history, active membership dates, and remaining dues within <strong>24 hours at zero additional cost</strong>.
                        </p>
                        <div class="flex items-center justify-between pt-3 text-xs border-t border-white/5 text-gray-400">
                            <span class="inline-flex items-center gap-1.5 text-amber-400 font-semibold">
                                <span class="material-symbols-outlined text-sm">flash_on</span> Free 24-Hour Migration
                            </span>
                            <div class="flex items-center gap-2">
                                <span>Was this helpful?</span>
                                <button type="button" @click="feedback = 'yes'" :class="feedback === 'yes' ? 'text-emerald-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👍 Yes</button>
                                <span>•</span>
                                <button type="button" @click="feedback = 'no'" :class="feedback === 'no' ? 'text-rose-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👎 No</button>
                                <span x-show="feedback" class="text-primary text-[11px] font-mono ml-1">Thanks!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 5: Multi-Branch Chains -->
                <div x-show="matches('multigym', 'Can I manage multiple gym branches under one account?', 'Yes, FluxFitness multi-tenant architecture allows you to switch branches with 1 click, view consolidated financial health, and track staff performance across branches.')" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="glass-card rounded-2xl border transition-all duration-300 overflow-hidden" 
                    :class="isOpen(5) ? 'border-purple-500/50 bg-surface-container-high/60 shadow-[0_0_25px_rgba(168,85,247,0.1)]' : 'border-outline-variant/30 hover:border-outline-variant/60'"
                    x-data="{ feedback: null }"
                >
                    <button @click="toggle(5)" :aria-expanded="String(isOpen(5))" aria-controls="faq-answer-5" type="button" class="w-full p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center text-sm font-bold flex-shrink-0">Q5</span>
                            <span class="font-headline font-bold text-base sm:text-lg text-on-surface">Can I manage multiple gym branches under one owner account?</span>
                        </div>
                        <span class="material-symbols-outlined text-purple-400 text-2xl transition-transform duration-300" :class="isOpen(5) ? 'rotate-180 text-purple-400' : 'text-gray-400'">expand_more</span>
                    </button>
                    <div x-show="isOpen(5)" id="faq-answer-5" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 pb-6 pt-2 text-sm text-on-surface-variant leading-relaxed border-t border-white/5 space-y-4">
                        <p>
                            Yes! Gym owners can monitor individual branch revenue, comparative attendance, and staff performance from a central master cockpit. You can also configure <strong>Cross-Branch Roaming Passes</strong> so VIP members can work out at any of your gym locations seamlessly.
                        </p>
                        <div class="flex items-center justify-between pt-3 text-xs border-t border-white/5 text-gray-400">
                            <span class="inline-flex items-center gap-1.5 text-purple-400 font-semibold">
                                <span class="material-symbols-outlined text-sm">domain</span> Centralized Multi-Gym Dashboard
                            </span>
                            <div class="flex items-center gap-2">
                                <span>Was this helpful?</span>
                                <button type="button" @click="feedback = 'yes'" :class="feedback === 'yes' ? 'text-emerald-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👍 Yes</button>
                                <span>•</span>
                                <button type="button" @click="feedback = 'no'" :class="feedback === 'no' ? 'text-rose-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👎 No</button>
                                <span x-show="feedback" class="text-primary text-[11px] font-mono ml-1">Thanks!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 6: Staff Security & Contact Protection -->
                <div x-show="matches('migration', 'Is my gym member data safe from staff or ex-trainers stealing contacts?', 'Yes, FluxFitness uses strict role-based permission controls and phone number masking so receptionists and trainers cannot export or steal your contact database.')" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="glass-card rounded-2xl border transition-all duration-300 overflow-hidden" 
                    :class="isOpen(6) ? 'border-primary/50 bg-surface-container-high/60 shadow-[0_0_25px_rgba(34,197,94,0.1)]' : 'border-outline-variant/30 hover:border-outline-variant/60'"
                    x-data="{ feedback: null }"
                >
                    <button @click="toggle(6)" :aria-expanded="String(isOpen(6))" aria-controls="faq-answer-6" type="button" class="w-full p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center text-sm font-bold flex-shrink-0">Q6</span>
                            <span class="font-headline font-bold text-base sm:text-lg text-on-surface">Is my member directory safe from staff stealing phone numbers?</span>
                        </div>
                        <span class="material-symbols-outlined text-primary text-2xl transition-transform duration-300" :class="isOpen(6) ? 'rotate-180 text-primary' : 'text-gray-400'">expand_more</span>
                    </button>
                    <div x-show="isOpen(6)" id="faq-answer-6" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 pb-6 pt-2 text-sm text-on-surface-variant leading-relaxed border-t border-white/5 space-y-4">
                        <p>
                            Yes. FluxFitness provides <strong>phone number masking</strong> (`+91 ••••• ••492`) and disables bulk Excel export rights for trainers and desk staff. Only the verified Gym Owner or Super Admin holds export and financial visibility rights.
                        </p>
                        <div class="flex items-center justify-between pt-3 text-xs border-t border-white/5 text-gray-400">
                            <span class="inline-flex items-center gap-1.5 text-primary font-semibold">
                                <span class="material-symbols-outlined text-sm">security</span> Role-Based Privacy & Contact Protection
                            </span>
                            <div class="flex items-center gap-2">
                                <span>Was this helpful?</span>
                                <button type="button" @click="feedback = 'yes'" :class="feedback === 'yes' ? 'text-emerald-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👍 Yes</button>
                                <span>•</span>
                                <button type="button" @click="feedback = 'no'" :class="feedback === 'no' ? 'text-rose-400 font-bold' : 'hover:text-white'" class="cursor-pointer">👎 No</button>
                                <span x-show="feedback" class="text-primary text-[11px] font-mono ml-1">Thanks!</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Still Have Questions Interactive Help Card -->
            <div class="glass-card rounded-3xl p-8 border border-primary/30 max-w-4xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6 bg-gradient-to-r from-surface-container-high/60 via-surface-container-low/40 to-surface-container-high/60 shadow-2xl">
                <div class="space-y-2 text-center md:text-left">
                    <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Direct WhatsApp Support Online</span>
                    </div>
                    <h3 class="font-headline font-bold text-xl sm:text-2xl text-white">Have a specific question about your gym setup?</h3>
                    <p class="text-gray-400 text-xs sm:text-sm">Our gym software specialists answer questions on WhatsApp within 2 minutes.</p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-3">
                    <a href="https://wa.me/{{ config('app.contact_whatsapp', '919265098227') }}?text=Hi%20FluxFitness%20Team%2C%20I%20have%20a%20question%20about%20biometric%20setup" target="_blank" class="px-5 py-3 rounded-xl bg-[#25D366] text-black font-headline font-black text-xs sm:text-sm uppercase tracking-wider flex items-center gap-2 hover:brightness-110 shadow-lg shadow-[#25D366]/20 transition-all cursor-pointer">
                        <span class="material-symbols-outlined text-lg">chat</span>
                        <span>Chat on WhatsApp</span>
                    </a>
                    <a href="{{ route('request-demo') }}" class="px-5 py-3 rounded-xl bg-surface-container-high text-white border border-white/10 font-headline font-bold text-xs sm:text-sm uppercase tracking-wider hover:border-primary/50 transition-all cursor-pointer">
                        Book Live Demo
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Interactive Contact Hub Section -->
    <section id="contact" class="py-16 sm:py-24 bg-surface-container-lowest relative z-10 border-t border-outline-variant/20 overflow-hidden" x-data="{
        activeTab: 'whatsapp',
        selectedTopic: 'pricing',
        gymName: '',
        customMsg: '',

        get topicText() {
            switch(this.selectedTopic) {
                case 'pricing': return 'Plan Pricing & Special Offers';
                case 'biometric': return 'Biometric Machine & Gate Setup';
                case 'migration': return 'Free Excel Data Migration';
                case 'multigym': return 'Multi-Branch Franchise Setup';
                default: return 'General Gym Inquiry';
            }
        },

        get generatedWhatsAppUrl() {
            let base = 'https://wa.me/{{ config('app.contact_whatsapp', '919265098227') }}?text=';
            let msg = 'Hi FluxFitness Team! 👋🏻\n';
            if (this.gymName.trim()) {
                msg += 'I am from ' + this.gymName.trim() + '.\n';
            }
            msg += 'I want to inquire about: ' + this.topicText + '.';
            if (this.customMsg.trim()) {
                msg += '\nNote: ' + this.customMsg.trim();
            }
            return base + encodeURIComponent(msg);
        }
    }">
        <!-- Ambient Background Glow -->
        <div class="absolute top-1/3 left-1/4 w-[750px] h-[450px] bg-gradient-to-r from-emerald-500/10 via-cyan-500/5 to-[#cafd00]/5 blur-[160px] rounded-full pointer-events-none"></div>

        <div class="container mx-auto px-4 sm:px-6 max-w-7xl relative z-10 space-y-12 sm:space-y-16">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto space-y-3 sm:space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-surface-container-high border border-primary/30 text-xs font-bold uppercase tracking-widest text-primary shadow-[0_0_15px_rgba(34,197,94,0.15)]">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>Direct Support & Sales Team</span>
                </div>
                <h2 class="font-headline font-extrabold text-2xl sm:text-4xl lg:text-5xl tracking-tight text-on-surface leading-tight">
                    LET'S TALK ABOUT YOUR <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-emerald-300 to-cyan-400">GYM'S GROWTH</span>
                </h2>
                <p class="text-on-surface-variant text-sm sm:text-base lg:text-lg">
                    Whether you need a live demo, custom multi-branch pricing, or biometric hardware advice, our Indian support team is ready to assist.
                </p>
            </div>

            <!-- Contact Grid: Direct Channels & Interactive Widget -->
            <div class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-start max-w-6xl mx-auto">
                <!-- Left: Direct Touchpoints & Availability Cards -->
                <div class="lg:col-span-5 space-y-6">
                    <!-- Live Availability Card -->
                    <div class="glass-card rounded-2xl p-6 border border-emerald-500/30 space-y-4 shadow-xl relative overflow-hidden bg-gradient-to-br from-surface-container-high/60 to-surface-container-low/60">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-400">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>Support Desk Active Now</span>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-md bg-emerald-500/10 text-[10px] font-mono font-bold text-emerald-400 border border-emerald-500/20">
                                Mon - Sat
                            </span>
                        </div>
                        <h3 class="font-headline font-bold text-xl text-white">Instant WhatsApp & Phone Assistance</h3>
                        <p class="text-gray-400 text-xs leading-relaxed">
                            No automated IVR loops. Talk directly with gym management specialists who understand Indian gym workflows.
                        </p>
                        <div class="pt-2 flex items-center gap-4 text-xs text-gray-300 border-t border-white/5">
                            <span class="flex items-center gap-1.5 text-emerald-400 font-bold">
                                <span class="material-symbols-outlined text-sm">bolt</span> < 2 Mins Reply
                            </span>
                            <span>•</span>
                            <span class="flex items-center gap-1.5 text-cyan-400 font-bold">
                                <span class="material-symbols-outlined text-sm">translate</span> Hindi & English
                            </span>
                        </div>
                    </div>

                    <!-- Direct Contact List -->
                    <div class="space-y-3.5">
                        <!-- WhatsApp Direct -->
                        <a href="https://wa.me/{{ config('app.contact_whatsapp', '919265098227') }}?text=Hi%20FluxFitness%20Team%2C%20I%20would%20like%20to%20know%20more%20about%20FluxFitness%20software" target="_blank" class="glass-card rounded-2xl p-4 border border-outline-variant/30 hover:border-emerald-500/50 flex items-center justify-between group transition-all cursor-pointer">
                            <div class="flex items-center gap-3.5">
                                <div class="w-11 h-11 rounded-xl bg-[#25D366]/20 text-[#25D366] flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <span class="material-symbols-outlined text-2xl">chat</span>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400 uppercase font-bold tracking-wider">Official WhatsApp</div>
                                    <div class="font-headline font-bold text-sm text-white">{{ config('app.contact_phone', '+91 92650 98227') }}</div>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-gray-400 group-hover:text-[#25D366] group-hover:translate-x-1 transition-all">arrow_forward</span>
                        </a>

                        <!-- Direct Phone Call -->
                        <a href="tel:{{ config('app.contact_phone', '+919265098227') }}" class="glass-card rounded-2xl p-4 border border-outline-variant/30 hover:border-primary/50 flex items-center justify-between group transition-all cursor-pointer">
                            <div class="flex items-center gap-3.5">
                                <div class="w-11 h-11 rounded-xl bg-primary/20 text-primary flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <span class="material-symbols-outlined text-2xl">call</span>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400 uppercase font-bold tracking-wider">Direct Sales Line</div>
                                    <div class="font-headline font-bold text-sm text-white">{{ config('app.contact_phone', '+91 92650 98227') }}</div>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-gray-400 group-hover:text-primary group-hover:translate-x-1 transition-all">arrow_forward</span>
                        </a>

                        <!-- Email Direct -->
                        <a href="mailto:{{ config('app.contact_email', 'contact.fluxfitness@gmail.com') }}" class="glass-card rounded-2xl p-4 border border-outline-variant/30 hover:border-cyan-400/50 flex items-center justify-between group transition-all cursor-pointer">
                            <div class="flex items-center gap-3.5">
                                <div class="w-11 h-11 rounded-xl bg-cyan-400/20 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <span class="material-symbols-outlined text-2xl">mail</span>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400 uppercase font-bold tracking-wider">Email Support</div>
                                    <div class="font-headline font-bold text-sm text-white">{{ config('app.contact_email', 'contact.fluxfitness@gmail.com') }}</div>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-gray-400 group-hover:text-cyan-400 group-hover:translate-x-1 transition-all">arrow_forward</span>
                        </a>
                    </div>
                </div>

                <!-- Right: WhatsApp Message Builder & Callback Engine -->
                <div class="lg:col-span-7 glass-card rounded-3xl p-6 sm:p-8 border border-primary/30 shadow-2xl space-y-6 relative overflow-hidden bg-gradient-to-b from-surface-container-high/60 via-surface-container-low/40 to-surface-container-high/60">
                    <!-- Tab Switcher -->
                    <div class="flex items-center gap-2 p-1 bg-surface-container-high rounded-xl border border-white/10">
                        <button type="button" @click="activeTab = 'whatsapp'" :class="activeTab === 'whatsapp' ? 'bg-[#25D366] text-black font-black shadow-[0_0_15px_rgba(37,211,102,0.35)]' : 'text-gray-400 hover:text-white'" class="flex-1 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all cursor-pointer flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-base">chat</span>
                            <span>Quick WhatsApp Chat</span>
                        </button>
                        <button type="button" @click="activeTab = 'callback'" :class="activeTab === 'callback' ? 'bg-primary text-black font-black shadow-[0_0_15px_rgba(34,197,94,0.35)]' : 'text-gray-400 hover:text-white'" class="flex-1 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all cursor-pointer flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-base">phone_callback</span>
                            <span>Request Callback</span>
                        </button>
                    </div>

                    <!-- TAB 1: WhatsApp Message Builder -->
                    <div x-show="activeTab === 'whatsapp'" x-transition:enter="transition ease-out duration-200" class="space-y-5">
                        <!-- Step 1: Select Topic Chips -->
                        <div class="space-y-2">
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-300">1. Select What You Need Help With:</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" @click="selectedTopic = 'pricing'" :class="selectedTopic === 'pricing' ? 'bg-primary/20 text-primary border-primary' : 'bg-surface-container-high text-gray-300 border-white/10 hover:border-white/20'" class="p-2.5 rounded-xl border text-left text-xs font-semibold transition-all cursor-pointer flex items-center gap-2">
                                    <span>🏷️</span>
                                    <span>Plan Pricing & Discount</span>
                                </button>
                                <button type="button" @click="selectedTopic = 'biometric'" :class="selectedTopic === 'biometric' ? 'bg-cyan-400/20 text-cyan-400 border-cyan-400' : 'bg-surface-container-high text-gray-300 border-white/10 hover:border-white/20'" class="p-2.5 rounded-xl border text-left text-xs font-semibold transition-all cursor-pointer flex items-center gap-2">
                                    <span>📟</span>
                                    <span>Biometric Gate Sync</span>
                                </button>
                                <button type="button" @click="selectedTopic = 'migration'" :class="selectedTopic === 'migration' ? 'bg-amber-400/20 text-amber-400 border-amber-400' : 'bg-surface-container-high text-gray-300 border-white/10 hover:border-white/20'" class="p-2.5 rounded-xl border text-left text-xs font-semibold transition-all cursor-pointer flex items-center gap-2">
                                    <span>📊</span>
                                    <span>Free Excel Migration</span>
                                </button>
                                <button type="button" @click="selectedTopic = 'multigym'" :class="selectedTopic === 'multigym' ? 'bg-purple-400/20 text-purple-400 border-purple-400' : 'bg-surface-container-high text-gray-300 border-white/10 hover:border-white/20'" class="p-2.5 rounded-xl border text-left text-xs font-semibold transition-all cursor-pointer flex items-center gap-2">
                                    <span>🏢</span>
                                    <span>Multi-Branch Setup</span>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Optional Gym Name / City -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-300">2. Your Gym Name & City (Optional):</label>
                            <input 
                                type="text" 
                                x-model="gymName" 
                                placeholder="e.g. Iron Paradise Gym, Pune" 
                                class="w-full px-4 py-2.5 bg-surface-container-high/90 border border-white/10 focus:border-[#25D366] rounded-xl text-xs sm:text-sm text-white placeholder-gray-500 outline-none transition-all focus:ring-1 focus:ring-[#25D366]"
                            >
                        </div>

                        <!-- Step 3: Live WhatsApp Bubble Preview -->
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center text-xs font-bold uppercase tracking-wider text-gray-400">
                                <span>3. Live WhatsApp Preview:</span>
                                <span class="text-emerald-400 text-[10px]">Ready to send</span>
                            </div>
                            <div class="bg-[#0b141b] rounded-2xl p-4 border border-[#25D366]/30 shadow-xl space-y-2 font-body">
                                <div class="flex items-center justify-between pb-1.5 border-b border-white/10 text-[11px] text-gray-400">
                                    <span>To: <strong>FluxFitness Official Support</strong></span>
                                    <span class="text-emerald-400 font-bold">Online</span>
                                </div>
                                <div class="bg-[#202c33] rounded-xl p-3 text-xs text-gray-100 space-y-1 border border-white/5">
                                    <p>Hi FluxFitness Team! 👋</p>
                                    <p x-show="gymName.trim()" class="text-emerald-300 font-semibold" x-text="'I am from ' + gymName.trim() + '.'"></p>
                                    <p>I want to inquire about: <strong class="text-white" x-text="topicText"></strong>.</p>
                                    <div class="text-[10px] text-gray-400 text-right">Just now • Sending via WhatsApp</div>
                                </div>
                            </div>
                        </div>

                        <!-- 1-Click Launch Button -->
                        <a :href="generatedWhatsAppUrl" target="_blank" class="w-full py-4 rounded-xl bg-[#25D366] text-black font-headline font-black text-sm uppercase tracking-wider flex items-center justify-center gap-2 hover:brightness-110 shadow-lg shadow-[#25D366]/25 active:scale-[0.98] transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-xl">send</span>
                            <span>Launch WhatsApp Chat Now</span>
                        </a>
                    </div>

                    <!-- TAB 2: Request Instant Callback Form (Server-Backed Lead Ingestion) -->
                    <div x-show="activeTab === 'callback'" x-cloak x-transition:enter="transition ease-out duration-200" class="space-y-4">
                        @if(! $callbackSuccess)
                            <form wire:submit="requestCallback" class="space-y-4">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold uppercase tracking-wider text-gray-300">Your Full Name:</label>
                                    <input type="text" wire:model="callbackName" placeholder="e.g. Rahul Sharma" class="w-full px-4 py-2.5 bg-surface-container-high/90 border border-white/10 focus:border-primary rounded-xl text-xs sm:text-sm text-white placeholder-gray-500 outline-none transition-all">
                                    @error('callbackName')
                                        <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold uppercase tracking-wider text-gray-300">Mobile Phone Number <span class="text-rose-400">*</span>:</label>
                                    <input type="tel" wire:model="callbackPhone" placeholder="+91 98200 00000" class="w-full px-4 py-2.5 bg-surface-container-high/90 border border-white/10 focus:border-primary rounded-xl text-xs sm:text-sm text-white placeholder-gray-500 outline-none transition-all">
                                    @error('callbackPhone')
                                        <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold uppercase tracking-wider text-gray-300">Gym Name & Approximate Members (Optional):</label>
                                    <input type="text" wire:model="callbackGym" placeholder="e.g. FitZone Gym (300 Members)" class="w-full px-4 py-2.5 bg-surface-container-high/90 border border-white/10 focus:border-primary rounded-xl text-xs sm:text-sm text-white placeholder-gray-500 outline-none transition-all">
                                    @error('callbackGym')
                                        <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <button type="submit" wire:loading.attr="disabled" :disabled="$wire.callbackSubmitting" class="w-full py-4 rounded-xl bg-primary text-black font-headline font-black text-sm uppercase tracking-wider flex items-center justify-center gap-2 hover:brightness-110 shadow-lg shadow-primary/25 active:scale-[0.98] transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="requestCallback" class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-xl">phone_in_talk</span>
                                        <span>Request Free Callback</span>
                                    </span>
                                    <span wire:loading.flex wire:target="requestCallback" class="flex items-center gap-2">
                                        <svg class="w-5 h-5 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Submitting Request...</span>
                                    </span>
                                </button>

                                <p class="text-[11px] text-gray-400 text-center pt-1">
                                    By submitting, you agree to receive a callback from our team. We respect your privacy.
                                </p>
                            </form>
                        @else
                            <!-- Callback Success State -->
                            <div class="p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-center space-y-3">
                                <span class="material-symbols-outlined text-emerald-400 text-4xl">check_circle</span>
                                <h4 class="font-headline font-bold text-lg text-white">Callback Request Received!</h4>
                                <p class="text-gray-300 text-xs">
                                    Our senior gym software specialist will call you shortly within business hours.
                                </p>
                                <button type="button" wire:click="resetCallback" wire:loading.attr="disabled" wire:target="resetCallback" class="inline-flex items-center justify-center text-xs text-primary font-bold hover:underline cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="resetCallback">Submit another request</span>
                                    <x-loading-icon target="resetCallback" variant="text" name="Resetting" class="w-3 h-3 text-primary" />
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bottom High-Conversion CTA Banner -->
    <section class="py-14 sm:py-20 relative overflow-hidden">
        <div class="container mx-auto px-4 sm:px-6 max-w-5xl relative z-10">
            <div class="glass-card rounded-3xl p-6 sm:p-14 border border-primary/40 text-center space-y-6 sm:space-y-8 relative overflow-hidden shadow-2xl">
                <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-96 h-96 bg-primary-container/15 rounded-full blur-3xl pointer-events-none"></div>

                <div class="space-y-3 sm:space-y-4 max-w-2xl mx-auto relative z-10">
                    <h2 class="font-headline font-extrabold text-2xl sm:text-4xl lg:text-5xl text-on-surface leading-tight">
                        READY TO ELEVATE YOUR <br />
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-primary-container">GYM BUSINESS?</span>
                    </h2>
                    <p class="text-on-surface-variant text-sm sm:text-base lg:text-lg">
                        Join hundreds of successful gym owners who streamlined their operations and boosted member retention with FluxFitness.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center relative z-10">
                    <button wire:click="startFreeTrial" wire:loading.attr="disabled"
                        class="cursor-pointer w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-primary-container text-on-primary-fixed font-headline font-bold text-sm sm:text-lg px-8 sm:px-9 py-3.5 sm:py-4 rounded-xl shadow-xl shadow-primary/20 hover:brightness-110 active:scale-[0.98] transition-all uppercase tracking-wide">
                        <span wire:loading.remove wire:target="startFreeTrial">Get Started Free</span>
                        <span wire:loading wire:target="startFreeTrial">Starting...</span>
                    </button>
                    <a href="{{ route('request-demo') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-surface-container-high border border-outline-variant/40 text-on-surface font-headline font-semibold text-sm sm:text-lg px-6 sm:px-8 py-3.5 sm:py-4 rounded-xl hover:bg-surface-container-highest hover:border-primary/50 transition-all">
                        Request a Personalized Demo
                    </a>
                </div>

                <div class="text-xs text-on-surface-variant flex flex-wrap items-center justify-center gap-2 sm:gap-4 relative z-10 pt-1 sm:pt-2 font-medium">
                    <span>⚡ Instant 2-minute setup</span>
                    <span class="hidden xs:inline">•</span>
                    <span>🔒 100% Data Isolation</span>
                    <span class="hidden xs:inline">•</span>
                    <span>🇮🇳 Built for Indian Gyms</span>
                </div>
            </div>
        </div>
    </section>

    @push('meta') 
        <!-- JSON-LD FAQ Schema Synchronized with On-Page Accordion -->
        @verbatim
            <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "FAQPage",
                "mainEntity": [
                    {
                        "@type": "Question",
                        "name": "How does the 14-day free trial work?",
                        "acceptedAnswer": {
                            "@type": "Answer",
                            "text": "You get 100% unrestricted access to every feature (WhatsApp due chasers, biometric check-ins, multi-trainer commissions, and GST invoicing) for 14 full days. There is no credit card or upfront payment required."
                        }
                    },
                    {
                        "@type": "Question",
                        "name": "How does WhatsApp automated payment reminders and UPI collection work?",
                        "acceptedAnswer": {
                            "@type": "Answer",
                            "text": "FluxFitness connects directly with verified WhatsApp messaging. The system tracks member expiration dates and automatically dispatches polite reminders 3 days and 1 day prior, embedded with a dynamic UPI QR code. When members pay, validity is renewed in real-time and a GST receipt is pushed back instantly."
                        }
                    },
                    {
                        "@type": "Question",
                        "name": "Does FluxFitness connect with biometric fingerprint & face turnstile gates?",
                        "acceptedAnswer": {
                            "@type": "Answer",
                            "text": "Yes! FluxFitness integrates with popular biometric brands (such as eSSL, Realtime, ZKTeco, and Hikvision) as well as turnstile tripod gates. When an expired member checks in, the turnstile automatically denies entry and sends a payment prompt to their phone."
                        }
                    },
                    {
                        "@type": "Question",
                        "name": "Can we import our existing members from Excel or previous software?",
                        "acceptedAnswer": {
                            "@type": "Answer",
                            "text": "Absolutely. You can use our built-in 1-click Excel/CSV importer, or send your spreadsheets to our dedicated onboarding specialist on WhatsApp. We map all past payment history, active membership dates, and remaining dues within 24 hours at zero additional cost."
                        }
                    },
                    {
                        "@type": "Question",
                        "name": "Can I manage multiple gym branches under one owner account?",
                        "acceptedAnswer": {
                            "@type": "Answer",
                            "text": "Yes! Gym owners can monitor individual branch revenue, comparative attendance, and staff performance from a central master cockpit, and configure Cross-Branch Roaming Passes."
                        }
                    },
                    {
                        "@type": "Question",
                        "name": "Is my member directory safe from staff stealing phone numbers?",
                        "acceptedAnswer": {
                            "@type": "Answer",
                            "text": "Yes. FluxFitness provides phone number masking and disables bulk Excel export rights for trainers and desk staff. Only the verified Gym Owner or Super Admin holds export and financial visibility rights."
                        }
                    }
                ]
            }
            </script>
        @endverbatim
    @endpush
</div>
