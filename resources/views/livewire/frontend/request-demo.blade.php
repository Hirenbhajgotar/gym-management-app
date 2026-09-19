<div>
    <!-- Toast Messages -->
    @livewire('toast')

    <!-- Hero & Form Section -->
    <section class="relative pt-24 pb-16 lg:pt-32 lg:pb-24 bg-[#07090e] border-b border-white/[0.06]">
        <div class="container mx-auto px-6 max-w-7xl relative z-10">
            <!-- Breadcrumb Navigation -->
            <div class="flex items-center gap-2 text-xs font-medium text-gray-400 mb-8">
                <a href="{{ route('home') }}" wire:navigate class="hover:text-cyan-400 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">home</span>
                    <span>Home</span>
                </a>
                <span class="text-gray-600">/</span>
                <span class="text-emerald-400 font-semibold">Request Demo</span>
            </div>

            <div class="grid lg:grid-cols-12 gap-10 lg:gap-12 items-start">
                <!-- Left Column: Direct Value Points -->
                <div class="lg:col-span-6 space-y-6 lg:pr-4">
                    <!-- Status Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs font-semibold text-emerald-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>1-on-1 Product Walkthrough</span>
                    </div>

                    <!-- Headline -->
                    <div class="space-y-3">
                        <h1 class="font-headline font-bold text-3xl sm:text-4xl lg:text-5xl text-white tracking-tight leading-tight">
                            See how FluxFitness automates your <span class="text-emerald-400">gym operations</span>
                        </h1>
                        <p class="text-gray-300 text-sm sm:text-base leading-relaxed font-body">
                            Schedule a 20-minute personalized walkthrough. We'll show you how automated WhatsApp fee reminders, biometric turnstile gates, and member records work for your gym setup.
                        </p>
                    </div>

                    <!-- Value Proposition Points -->
                    <div class="space-y-4 pt-2 text-sm font-body">
                        <!-- Point 1 -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 flex-shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-lg">chat</span>
                            </div>
                            <div class="space-y-0.5">
                                <h3 class="font-headline font-semibold text-white text-sm sm:text-base">Automated WhatsApp Fee Reminders</h3>
                                <p class="text-gray-400 text-xs sm:text-sm leading-relaxed">
                                    Sends polite due alerts 3 days and 1 day before expiry with a dynamic UPI QR link. Status updates instantly on payment.
                                </p>
                            </div>
                        </div>

                        <!-- Point 2 -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 flex-shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-lg">fingerprint</span>
                            </div>
                            <div class="space-y-0.5">
                                <h3 class="font-headline font-semibold text-white text-sm sm:text-base">Biometric Turnstile Integration</h3>
                                <p class="text-gray-400 text-xs sm:text-sm leading-relaxed">
                                    Fast 0.2s check-ins with fingerprint, face scanners, or QR passes. Unpaid expired memberships are automatically locked out.
                                </p>
                            </div>
                        </div>

                        <!-- Point 3 -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 flex-shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-lg">monitoring</span>
                            </div>
                            <div class="space-y-0.5">
                                <h3 class="font-headline font-semibold text-white text-sm sm:text-base">Owner Mobile Dashboard</h3>
                                <p class="text-gray-400 text-xs sm:text-sm leading-relaxed">
                                    Monitor daily fee collections, live floor attendance, trainer commissions, and staff permissions directly on your phone.
                                </p>
                            </div>
                        </div>

                        <!-- Point 4 -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-400/10 border border-emerald-400/20 flex items-center justify-center text-emerald-300 flex-shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-lg">sync</span>
                            </div>
                            <div class="space-y-0.5">
                                <h3 class="font-headline font-semibold text-white text-sm sm:text-base">Free Member Data Migration</h3>
                                <p class="text-gray-400 text-xs sm:text-sm leading-relaxed">
                                    Share your Excel sheet or old software export. Our team formats and maps all active members and validities within 24 hours.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Trust Highlights Strip -->
                    <div class="flex items-center gap-6 pt-3 text-xs text-gray-400 font-medium font-body border-t border-white/[0.08]">
                        <span class="flex items-center gap-1.5 text-emerald-400">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            2-Minute Setup
                        </span>
                        <span class="flex items-center gap-1.5 text-cyan-400">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            Isolated Tenant Data
                        </span>
                        <span class="flex items-center gap-1.5 text-gray-300">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            Works on Any Phone / PC
                        </span>
                    </div>

                    <!-- Direct Support Links Strip -->
                    <div class="pt-1 text-xs text-gray-400 flex flex-wrap items-center justify-between gap-4">
                        <span class="text-gray-300">Need quick answers?</span>
                        <div class="flex items-center gap-4">
                            @if(config('app.contact_phone'))
                                <a href="{{ $this->whatsAppUrl }}" 
                                   target="_blank" 
                                   class="inline-flex items-center gap-1.5 text-[#25D366] hover:brightness-110 font-medium transition-colors">
                                    <span class="material-symbols-outlined text-sm">chat</span>
                                    <span>WhatsApp Direct</span>
                                </a>
                                <a href="tel:{{ config('app.contact_phone') }}" class="inline-flex items-center gap-1 text-cyan-400 hover:text-cyan-300 font-medium transition-colors">
                                    <span class="material-symbols-outlined text-sm">call</span>
                                    <span>{{ config('app.contact_phone') }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Right Column: Demo Booking Form -->
                <div class="lg:col-span-6">
                    <div class="rounded-2xl border border-white/10 bg-[#0a0f18] p-6 sm:p-8 space-y-5 shadow-xl">
                        @if(! $isSubmitted)
                            <div class="space-y-1">
                                <h2 class="font-headline font-bold text-xl sm:text-2xl text-white">
                                    Book Your Free Demo
                                </h2>
                                <p class="text-xs text-gray-400 font-body">
                                    Fill in your details and our team will get in touch to schedule your walkthrough.
                                </p>
                            </div>

                            <form wire:submit.prevent="submitRequest" class="space-y-4 text-sm font-body">
                                <!-- Rate Limit Error Banner -->
                                @error('rate_limit')
                                    <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center gap-2.5">
                                        <span class="material-symbols-outlined text-rose-400 text-lg flex-shrink-0">timer</span>
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror

                                <!-- Full Name -->
                                <div class="space-y-1">
                                    <label for="name" class="block text-xs font-semibold text-gray-300">
                                        Full Name <span class="text-rose-400">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                            <span class="material-symbols-outlined text-lg">person</span>
                                        </div>
                                        <input
                                            type="text"
                                            id="name"
                                            wire:model="name"
                                            class="w-full pl-10 pr-4 py-2.5 bg-[#0d1522] border border-white/10 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl text-white placeholder-gray-500 outline-none transition-all text-sm"
                                            placeholder="e.g. Rahul Sharma"
                                            required
                                        >
                                    </div>
                                    @error('name')
                                        <p class="text-xs text-rose-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Email & Phone Row -->
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label for="email" class="block text-xs font-semibold text-gray-300">
                                            Email Address <span class="text-rose-400">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                                <span class="material-symbols-outlined text-lg">mail</span>
                                            </div>
                                            <input
                                                type="email"
                                                id="email"
                                                wire:model="email"
                                                class="w-full pl-10 pr-4 py-2.5 bg-[#0d1522] border border-white/10 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl text-white placeholder-gray-500 outline-none transition-all text-sm"
                                                placeholder="rahul@fitzone.com"
                                                required
                                            >
                                        </div>
                                        @error('email')
                                            <p class="text-xs text-rose-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-1">
                                        <label for="phone" class="block text-xs font-semibold text-gray-300">
                                            Phone Number <span class="text-rose-400">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                                <span class="material-symbols-outlined text-lg">phone_iphone</span>
                                            </div>
                                            <input
                                                type="tel"
                                                id="phone"
                                                wire:model="phone"
                                                class="w-full pl-10 pr-4 py-2.5 bg-[#0d1522] border border-white/10 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl text-white placeholder-gray-500 outline-none transition-all text-sm"
                                                placeholder="+91 98200 00000"
                                                required
                                            >
                                        </div>
                                        @error('phone')
                                            <p class="text-xs text-rose-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Gym Name & Members Row -->
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label for="company_name" class="block text-xs font-semibold text-gray-300">
                                            Gym / Business Name
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                                <span class="material-symbols-outlined text-lg">fitness_center</span>
                                            </div>
                                            <input
                                                type="text"
                                                id="company_name"
                                                wire:model="company_name"
                                                class="w-full pl-10 pr-4 py-2.5 bg-[#0d1522] border border-white/10 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl text-white placeholder-gray-500 outline-none transition-all text-sm"
                                                placeholder="e.g. Iron Paradise Gym"
                                            >
                                        </div>
                                        @error('company_name')
                                            <p class="text-xs text-rose-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-1">
                                        <label for="gym_size" class="block text-xs font-semibold text-gray-300">
                                            Active Member Count
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                                <span class="material-symbols-outlined text-lg">group</span>
                                            </div>
                                            <input
                                                type="number"
                                                id="gym_size"
                                                wire:model="gym_size"
                                                min="1"
                                                max="100000"
                                                class="w-full pl-10 pr-4 py-2.5 bg-[#0d1522] border border-white/10 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl text-white placeholder-gray-500 outline-none transition-all text-sm"
                                                placeholder="e.g. 250"
                                            >
                                        </div>
                                        @error('gym_size')
                                            <p class="text-xs text-rose-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Specific Questions or Challenges -->
                                <div class="space-y-1">
                                    <label for="message" class="block text-xs font-semibold text-gray-300">
                                        Questions or Current Challenges (Optional)
                                    </label>
                                    <textarea
                                        id="message"
                                        wire:model="message"
                                        rows="3"
                                        class="w-full px-4 py-2.5 bg-[#0d1522] border border-white/10 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 rounded-xl text-white placeholder-gray-500 outline-none transition-all text-sm resize-none"
                                        placeholder="e.g. Turnstile machine model, multi-branch setup..."
                                        maxlength="1000"
                                    ></textarea>
                                    @error('message')
                                        <p class="text-xs text-rose-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Privacy Note -->
                                <div class="p-3 rounded-xl bg-cyan-500/5 border border-cyan-500/15 flex items-center gap-2 text-xs text-cyan-200">
                                    <span class="material-symbols-outlined text-cyan-400 text-sm flex-shrink-0">lock</span>
                                    <span>Your contact details are strictly confidential. Zero spam.</span>
                                </div>

                                <!-- Submit Button -->
                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="submitRequest"
                                    class="w-full py-3.5 px-6 bg-[#22c55e] hover:bg-[#16a34a] text-black font-headline font-bold text-sm rounded-xl transition-all uppercase tracking-wider cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                >
                                    <span wire:loading.remove wire:target="submitRequest" class="flex items-center gap-2">
                                        <span>Schedule Free Demo</span>
                                        <span class="material-symbols-outlined text-base">arrow_forward</span>
                                    </span>

                                    <span wire:loading.flex wire:target="submitRequest" class="flex items-center gap-2">
                                        <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Submitting...</span>
                                    </span>
                                </button>
                            </form>
                        @else
                            <!-- Success State -->
                            <div class="py-8 text-center space-y-4">
                                <div class="w-14 h-14 rounded-full bg-emerald-500/15 border border-emerald-400 flex items-center justify-center text-emerald-400 mx-auto">
                                    <span class="material-symbols-outlined text-2xl">check</span>
                                </div>
                                <div class="space-y-1">
                                    <h3 class="font-headline font-bold text-xl text-white">Demo Request Received!</h3>
                                    <p class="text-xs sm:text-sm text-gray-300 max-w-sm mx-auto font-body">
                                        Thank you! Our gym systems specialist will connect with you shortly to schedule your walkthrough.
                                    </p>
                                </div>
                                <div class="pt-2">
                                    <button 
                                        type="button" 
                                        wire:click="resetForm" 
                                        wire:loading.attr="disabled"
                                        wire:target="resetForm"
                                        class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-gray-300 text-xs font-medium hover:bg-white/10 hover:text-white transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                                    >
                                        <span wire:loading.remove wire:target="resetForm">
                                            Submit Another Request
                                        </span>

                                        <span wire:loading.flex wire:target="resetForm" class="items-center gap-2">
                                            <svg class="w-3.5 h-3.5 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Loading...</span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 2: Everyday Gym Headaches vs FluxFitness Solution -->
    <section class="py-20 bg-[#0a0f18] border-b border-white/[0.06]">
        <div class="container mx-auto px-6 max-w-7xl">
            <div class="text-center max-w-3xl mx-auto mb-14 space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#0d1520] border border-cyan-500/20 text-xs font-semibold text-cyan-400">
                    <span>Operations Comparison</span>
                </div>
                <h2 class="font-headline font-bold text-2xl sm:text-4xl text-white tracking-tight">
                    Common daily gym issues, <span class="text-emerald-400">handled automatically</span>
                </h2>
                <p class="text-gray-400 text-sm sm:text-base font-body">
                    We built FluxFitness specifically for gym owners who want to eliminate awkward fee follow-ups and manual register entries.
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Comparison 1: Fee Recovery -->
                <div class="p-6 rounded-2xl bg-[#070b12] border border-white/10 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                            <span class="material-symbols-outlined text-lg">payments</span>
                        </div>
                        <h3 class="font-headline font-semibold text-base text-white">Chasing Expired Memberships</h3>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3 text-xs font-body">
                        <div class="p-3.5 rounded-xl bg-rose-500/5 border border-rose-500/20 text-rose-200 space-y-1">
                            <div class="font-semibold text-rose-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>The Manual Way</span>
                            </div>
                            <p class="text-gray-300">Desk staff forgets to check registers, makes awkward phone calls, or members continue working out unpaid.</p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-200 space-y-1">
                            <div class="font-semibold text-emerald-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                <span>With FluxFitness</span>
                            </div>
                            <p class="text-gray-300">WhatsApp sends renewal alerts 3 days and 1 day prior with a dynamic UPI QR. Membership auto-renews instantly on payment.</p>
                        </div>
                    </div>
                </div>

                <!-- Comparison 2: Turnstile Entry & Buddy Check-in -->
                <div class="p-6 rounded-2xl bg-[#070b12] border border-white/10 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                            <span class="material-symbols-outlined text-lg">meeting_room</span>
                        </div>
                        <h3 class="font-headline font-semibold text-base text-white">Gate Access & Unpaid Walk-ins</h3>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3 text-xs font-body">
                        <div class="p-3.5 rounded-xl bg-rose-500/5 border border-rose-500/20 text-rose-200 space-y-1">
                            <div class="font-semibold text-rose-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>The Manual Way</span>
                            </div>
                            <p class="text-gray-300">Reception gets crowded during peak hours; expired members or non-members walk straight onto the gym floor.</p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-200 space-y-1">
                            <div class="font-semibold text-cyan-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                <span>With FluxFitness</span>
                            </div>
                            <p class="text-gray-300">Turnstile locks out expired members in 0.2s and sends an instant renewal prompt directly to their WhatsApp.</p>
                        </div>
                    </div>
                </div>

                <!-- Comparison 3: Staff / Trainer Client Poaching -->
                <div class="p-6 rounded-2xl bg-[#070b12] border border-white/10 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                            <span class="material-symbols-outlined text-lg">lock_person</span>
                        </div>
                        <h3 class="font-headline font-semibold text-base text-white">Trainer & Member Data Privacy</h3>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3 text-xs font-body">
                        <div class="p-3.5 rounded-xl bg-rose-500/5 border border-rose-500/20 text-rose-200 space-y-1">
                            <div class="font-semibold text-rose-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>The Manual Way</span>
                            </div>
                            <p class="text-gray-300">Trainers write down member contact numbers, switch gyms, and take your personal training clients with them.</p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-200 space-y-1">
                            <div class="font-semibold text-amber-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                <span>With FluxFitness</span>
                            </div>
                            <p class="text-gray-300">Member phone numbers are masked for staff and trainers. Only verified Gym Owners can access and export full ledgers.</p>
                        </div>
                    </div>
                </div>

                <!-- Comparison 4: Daily Registers & Remote Visibility -->
                <div class="p-6 rounded-2xl bg-[#070b12] border border-white/10 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                            <span class="material-symbols-outlined text-lg">analytics</span>
                        </div>
                        <h3 class="font-headline font-semibold text-base text-white">Owner Remote Visibility</h3>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3 text-xs font-body">
                        <div class="p-3.5 rounded-xl bg-rose-500/5 border border-rose-500/20 text-rose-200 space-y-1">
                            <div class="font-semibold text-rose-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>The Manual Way</span>
                            </div>
                            <p class="text-gray-300">Owner must physically sit at reception or wait for evening cash tallies to know accurate collections.</p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-200 space-y-1">
                            <div class="font-semibold text-emerald-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                <span>With FluxFitness</span>
                            </div>
                            <p class="text-gray-300">Mobile dashboard gives you real-time revenue tallies, attendance counts, and trainer sales from anywhere, 24/7.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 2.5: Interactive Gym Revenue Leakage Calculator -->
    <section class="py-20 bg-[#07090e] border-b border-white/[0.06]" id="leakage-calculator">
        <div class="container mx-auto px-6 max-w-7xl"
             x-data="{
                members: 250,
                fee: 1800,
                slippagePercent: 12,
                recoveryRate: 0.85,
                get monthlyRevenue() {
                    return this.members * this.fee;
                },
                get monthlyLeakage() {
                    return Math.round(this.monthlyRevenue * (this.slippagePercent / 100));
                },
                get annualLeakage() {
                    return this.monthlyLeakage * 12;
                },
                get monthlyRecovered() {
                    return Math.round(this.monthlyLeakage * this.recoveryRate);
                },
                get annualRecovered() {
                    return this.monthlyRecovered * 12;
                },
                formatInr(val) {
                    const num = Number(val);
                    if (isNaN(num) || !isFinite(num)) {
                        return '₹0';
                    }
                    return '₹' + num.toLocaleString('en-IN');
                },
                scrollToForm() {
                    const el = document.getElementById('name');
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.focus();
                    }
                }
             }">
            
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-14 space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-xs font-semibold text-rose-400">
                    <span>Revenue Leakage Calculator</span>
                </div>
                <h2 class="font-headline font-bold text-2xl sm:text-4xl text-white tracking-tight">
                    Calculate estimated uncollected dues at your gym
                </h2>
                <p class="text-gray-400 text-sm font-body max-w-2xl mx-auto">
                    Gyms without automated turnstiles typically lose 10%–15% in dues from expired grace periods and forgotten follow-ups. Adjust your numbers below:
                </p>
            </div>

            <!-- Calculator Layout Grid -->
            <div class="grid lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left: Controls & Slider Inputs (7 Cols) -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="p-6 sm:p-8 rounded-2xl bg-[#0a0f18] border border-white/10 space-y-6 shadow-lg">
                        
                        <!-- Control 1: Member Count Slider -->
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <label class="text-xs font-semibold text-gray-300 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-cyan-400 text-base">groups</span>
                                    <span>Active Gym Members</span>
                                </label>
                                <div class="px-3 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/20 font-mono font-bold text-cyan-300 text-sm">
                                    <span x-text="members"></span> <span class="text-xs text-cyan-400/70 font-sans font-normal">Members</span>
                                </div>
                            </div>
                            
                            <input
                                type="range"
                                id="slider-members"
                                aria-label="Active Gym Members"
                                min="30"
                                max="1500"
                                step="10"
                                x-model.number="members"
                                class="w-full h-2 bg-[#121c2d] rounded-lg appearance-none cursor-pointer accent-cyan-400"
                            >
                            
                            <!-- Quick Preset Pills -->
                            <div class="flex items-center gap-2 pt-1 flex-wrap">
                                <span class="text-[11px] text-gray-500 font-mono">Presets:</span>
                                <template x-for="preset in [100, 250, 500, 800, 1200]" :key="preset">
                                    <button
                                        type="button"
                                        @click="members = preset"
                                        :class="members === preset ? 'bg-cyan-500/20 border-cyan-400 text-cyan-300' : 'bg-[#0d1522] border-white/10 text-gray-400 hover:text-white'"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono border transition-all"
                                        x-text="preset"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        <!-- Control 2: Average Monthly Fee Slider -->
                        <div class="space-y-3 border-t border-white/[0.06] pt-6">
                            <div class="flex justify-between items-center">
                                <label class="text-xs font-semibold text-gray-300 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-emerald-400 text-base">payments</span>
                                    <span>Avg. Monthly Fee / Member</span>
                                </label>
                                <div class="px-3 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 font-mono font-bold text-emerald-300 text-sm">
                                    <span x-text="formatInr(fee)"></span><span class="text-xs text-emerald-400/70 font-sans font-normal">/mo</span>
                                </div>
                            </div>
                            
                            <input
                                type="range"
                                id="slider-fee"
                                aria-label="Average Monthly Fee per Member"
                                min="600"
                                max="8000"
                                step="100"
                                x-model.number="fee"
                                class="w-full h-2 bg-[#121c2d] rounded-lg appearance-none cursor-pointer accent-emerald-400"
                            >
                            
                            <!-- Quick Preset Pills -->
                            <div class="flex items-center gap-2 pt-1 flex-wrap">
                                <span class="text-[11px] text-gray-500 font-mono">Presets:</span>
                                <template x-for="preset in [1000, 1500, 2000, 3500, 5000]" :key="preset">
                                    <button
                                        type="button"
                                        @click="fee = preset"
                                        :class="fee === preset ? 'bg-emerald-500/20 border-emerald-400 text-emerald-300' : 'bg-[#0d1522] border-white/10 text-gray-400 hover:text-white'"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono border transition-all"
                                        x-text="formatInr(preset)"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        <!-- Estimated Base Monthly Inflow -->
                        <div class="p-3.5 rounded-xl bg-[#0d1522] border border-white/5 flex items-center justify-between text-xs font-body">
                            <span class="text-gray-400">Total Potential Monthly Billing:</span>
                            <span class="font-mono font-bold text-white text-sm" x-text="formatInr(monthlyRevenue)"></span>
                        </div>
                    </div>

                    <!-- Leakage Breakdown Pills -->
                    <div class="grid sm:grid-cols-3 gap-3 text-xs font-body">
                        <div class="p-3.5 rounded-xl bg-rose-500/5 border border-rose-500/15 text-gray-300 space-y-1">
                            <div class="font-semibold text-rose-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">door_open</span>
                                <span>~5% Gate Slip</span>
                            </div>
                            <p class="text-[11px] text-gray-400">Expired members working out without renewal during peak front-desk rush.</p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-amber-500/5 border border-amber-500/15 text-gray-300 space-y-1">
                            <div class="font-semibold text-amber-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">chat_bubble</span>
                                <span>~4% Delay Drag</span>
                            </div>
                            <p class="text-[11px] text-gray-400">Delayed manual WhatsApp reminders after member grace periods expire.</p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-orange-500/5 border border-orange-500/15 text-gray-300 space-y-1">
                            <div class="font-semibold text-orange-400 flex items-center gap-1 text-[11px] uppercase">
                                <span class="material-symbols-outlined text-sm">receipt_long</span>
                                <span>~3% POS & PT Loss</span>
                            </div>
                            <p class="text-[11px] text-gray-400">Unrecorded guest passes, manual PT logs, and missed supplement tabs.</p>
                        </div>
                    </div>
                </div>

                <!-- Right: Calculation Result & Recovery (5 Cols) -->
                <div class="lg:col-span-5 space-y-5">
                    
                    <!-- Leakage Loss Card -->
                    <div class="p-6 rounded-2xl bg-[#140b0f] border border-rose-500/20 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-rose-400">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span>Estimated Annual Leakage</span>
                            </div>
                            <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-rose-500/10 text-rose-300 border border-rose-500/20">12% Slippage</span>
                        </div>

                        <div>
                            <div class="font-mono font-bold text-3xl sm:text-4xl text-rose-300" x-text="formatInr(annualLeakage)"></div>
                            <div class="text-xs text-rose-300/80 font-body mt-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">trending_down</span>
                                <span>Losing approx. <strong class="text-white font-mono" x-text="formatInr(monthlyLeakage)"></strong> every month</span>
                            </div>
                        </div>
                    </div>

                    <!-- Recovery Card -->
                    <div class="p-6 rounded-2xl bg-[#08140e] border border-emerald-500/25 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-400">
                                <span class="material-symbols-outlined text-base">verified</span>
                                <span>Recovered With FluxFitness</span>
                            </div>
                            <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300 border border-emerald-500/20 font-semibold">~85% Recaptured</span>
                        </div>

                        <div>
                            <div class="font-mono font-bold text-3xl sm:text-4xl text-emerald-400" x-text="formatInr(annualRecovered)"></div>
                            <div class="text-xs text-emerald-300/90 font-body mt-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">savings</span>
                                <span>Adds <strong class="text-white font-mono" x-text="formatInr(monthlyRecovered)"></strong> net cashflow back every month</span>
                            </div>
                        </div>

                        <!-- Direct Recovery Pillars -->
                        <div class="space-y-2 border-t border-emerald-500/15 pt-4 text-xs font-body text-gray-300">
                            <div class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-emerald-400 text-sm mt-0.5">lock_clock</span>
                                <span><strong>0.2s Turnstile Lockout:</strong> No entry without active membership.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-emerald-400 text-sm mt-0.5">send_to_mobile</span>
                                <span><strong>Automated WhatsApp QR:</strong> 1-tap UPI payment 3 days prior.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-emerald-400 text-sm mt-0.5">account_balance</span>
                                <span><strong>Direct-to-Bank Settlement:</strong> Prevents counter cash discrepancies.</span>
                            </div>
                        </div>

                        <!-- CTA Button -->
                        <button
                            type="button"
                            @click="scrollToForm()"
                            class="w-full py-3 px-4 rounded-xl bg-[#22c55e] hover:bg-[#16a34a] text-black font-headline font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-all cursor-pointer"
                        >
                            <span>Schedule Demo to Stop Leakage</span>
                            <span class="material-symbols-outlined text-base">arrow_upward</span>
                        </button>
                    </div>

                </div>

            </div>

        </div>
    </section>

    @php
        $testimonialsList = $testimonials ?? [];
        $topicList = array_map(fn($t) => $t['topic'] ?? 'Review', $testimonialsList);
    @endphp

    @if(!empty($testimonialsList))
    <!-- Section 3: Verified Fitness Operator Testimonials & Auto-Sliding Showcase -->
    <section 
        class="py-20 bg-[#0a0f18] border-b border-white/[0.06]"
        role="region"
        aria-roledescription="carousel"
        aria-label="Customer Testimonials"
        x-data="{
            current: 0,
            total: {{ count($testimonialsList) }},
            isPaused: false,
            progress: 0,
            progressInterval: null,
            topics: {{ \Illuminate\Support\Js::from($topicList) }},
            init() {
                this.startTimer();
            },
            destroy() {
                this.stopTimer();
            },
            startTimer() {
                this.stopTimer();
                this.progress = 0;
                
                // Animate 5-second progress bar
                this.progressInterval = setInterval(() => {
                    if (!this.isPaused) {
                        this.progress += 2;
                        if (this.progress >= 100) {
                            this.progress = 0;
                            this.next();
                        }
                    }
                }, 100);
            },
            stopTimer() {
                if (this.progressInterval) clearInterval(this.progressInterval);
            },
            next() {
                this.current = (this.current + 1) % this.total;
                this.progress = 0;
            },
            prev() {
                this.current = (this.current - 1 + this.total) % this.total;
                this.progress = 0;
            },
            goTo(index) {
                this.current = index;
                this.progress = 0;
                this.startTimer();
            }
        }"
    >
        <div class="container mx-auto px-6 max-w-7xl">
            <!-- Section Header & Navigation Controls -->
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6">
                <div class="space-y-3 max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#0d1520] border border-cyan-500/20 text-xs font-semibold text-cyan-400">
                        <span>★ 4.9 / 5 Average Rating Across {{ $this->activeGymsCount }} Gyms</span>
                    </div>
                    <h2 class="font-headline font-bold text-2xl sm:text-4xl text-white tracking-tight">
                        What gym owners say about <span class="text-emerald-400">switching to FluxFitness</span>
                    </h2>
                    <p class="text-gray-400 text-sm sm:text-base font-body">
                        Real gym operators sharing how automated reminders and biometric turnstiles streamlined their operations.
                    </p>
                </div>

                <!-- Navigation Arrow Controls & Pause Toggle -->
                <div class="flex items-center gap-2 self-start md:self-end">
                    <button 
                        type="button"
                        @click="isPaused = !isPaused; if (!isPaused) startTimer();" 
                        class="px-3 h-10 rounded-xl bg-[#070b12] border border-white/10 hover:border-amber-400/40 text-gray-300 hover:text-white flex items-center justify-center gap-1.5 transition-all cursor-pointer text-xs font-medium"
                        :aria-label="isPaused ? 'Resume auto-sliding testimonials' : 'Pause auto-sliding testimonials'"
                        :title="isPaused ? 'Resume auto-slide' : 'Pause auto-slide'"
                    >
                        <span class="material-symbols-outlined text-base" x-text="isPaused ? 'play_arrow' : 'pause'"></span>
                        <span x-text="isPaused ? 'Play' : 'Pause'"></span>
                    </button>
                    <button 
                        type="button"
                        @click="prev(); startTimer();" 
                        class="w-10 h-10 rounded-xl bg-[#070b12] border border-white/10 hover:border-cyan-400/40 text-gray-300 hover:text-white flex items-center justify-center transition-all cursor-pointer"
                        aria-label="Previous Testimonial"
                    >
                        <span class="material-symbols-outlined text-xl">arrow_back</span>
                    </button>
                    <button 
                        type="button"
                        @click="next(); startTimer();" 
                        class="w-10 h-10 rounded-xl bg-[#070b12] border border-white/10 hover:border-emerald-400/40 text-gray-300 hover:text-white flex items-center justify-center transition-all cursor-pointer"
                        aria-label="Next Testimonial"
                    >
                        <span class="material-symbols-outlined text-xl">arrow_forward</span>
                    </button>
                </div>
            </div>

            <!-- Topic Quick-Jump Pills -->
            <div class="flex flex-wrap items-center gap-2 mb-6 text-xs font-semibold">
                <template x-for="(topic, idx) in topics" :key="idx">
                    <button
                        type="button"
                        @click="goTo(idx)"
                        class="px-3.5 py-1.5 rounded-lg border transition-all cursor-pointer flex items-center gap-1.5"
                        :class="current === idx 
                            ? 'bg-cyan-500/15 border-cyan-400/50 text-cyan-300' 
                            : 'bg-[#070b12] border-white/10 text-gray-400 hover:border-white/20 hover:text-gray-200'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="current === idx ? 'bg-cyan-400' : 'bg-gray-600'"></span>
                        <span x-text="topic"></span>
                    </button>
                </template>
            </div>

            <!-- Slider Viewport Track -->
            <div 
                class="relative overflow-hidden rounded-2xl"
                @mouseenter="isPaused = true"
                @mouseleave="isPaused = false"
            >
                <div 
                    class="flex transition-transform duration-500 ease-out"
                    :style="`transform: translateX(-${current * 100}%);`"
                    aria-live="polite"
                >
                    @foreach($testimonialsList as $idx => $t)
                        <div class="w-full flex-shrink-0" role="group" aria-roledescription="slide" aria-label="Testimonial {{ $idx + 1 }} of {{ count($testimonialsList) }}">
                            <div class="p-8 sm:p-12 rounded-3xl bg-[#0a0f18]/95 border border-white/15 relative overflow-hidden shadow-2xl backdrop-blur-xl">
                                <!-- Quote Watermark (Top-Left behind quote content) -->
                                <div class="absolute left-6 top-6 sm:left-10 sm:top-10 text-white/[0.04] sm:text-cyan-500/[0.05] pointer-events-none select-none z-0">
                                    <svg class="w-20 h-20 sm:w-28 sm:h-28 fill-current" viewBox="0 0 24 24">
                                        <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                                    </svg>
                                </div>

                                <div class="grid lg:grid-cols-12 gap-8 items-center relative z-10">
                                    <div class="lg:col-span-8 space-y-6">
                                        <!-- Stars & Badge Header -->
                                        <div class="flex flex-wrap items-center gap-3">
                                            <div class="flex items-center text-amber-400 text-xs tracking-wider">
                                                @for($s = 0; $s < ($t['rating'] ?? 5); $s++)
                                                    <span>★</span>
                                                @endfor
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                                                <span class="material-symbols-outlined text-sm">{{ $t['metric_icon'] ?? 'trending_up' }}</span>
                                                {{ $t['metric_badge'] ?? '+34% On-Time Renewals' }}
                                            </span>
                                            <span class="text-xs text-gray-400 hidden sm:inline">{{ $t['tag'] ?? 'Commercial Gym' }}</span>
                                        </div>

                                        <!-- Quote -->
                                        <blockquote class="font-headline text-sm sm:text-base lg:text-lg text-gray-100 font-medium leading-relaxed">
                                            @if(!empty($t['highlight']) && str_contains($t['quote'], $t['highlight']))
                                                "{!! str_replace(
                                                    e($t['highlight']), 
                                                    '<span class="text-emerald-400 font-semibold">' . e($t['highlight']) . '</span>', 
                                                    e($t['quote'])
                                                ) !!}"
                                            @else
                                                "{{ $t['quote'] }}"
                                            @endif
                                        </blockquote>

                                        <!-- Author Profile -->
                                        <div class="pt-3 border-t border-white/[0.06] flex items-center gap-3.5">
                                            <div class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $t['avatar_color'] ?? 'from-emerald-500/30 to-cyan-500/30' }} border border-white/10 flex items-center justify-center font-headline font-bold text-white text-base flex-shrink-0">
                                                {{ $t['initials'] ?? substr($t['name'] ?? 'G', 0, 2) }}
                                            </div>
                                            <div>
                                                <div class="font-headline font-bold text-white text-sm sm:text-base flex items-center gap-1.5">
                                                    <span>{{ $t['name'] }}</span>
                                                    <span class="material-symbols-outlined text-emerald-400 text-sm" title="Verified Gym Operator">verified</span>
                                                </div>
                                                <p class="text-xs text-gray-400">{{ $t['role'] }} • {{ $t['location'] }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Key Highlights Card Column -->
                                    <div class="lg:col-span-4 flex flex-col gap-3 font-body">
                                        @if(!empty($t['stat_1_title']))
                                             <div class="p-4 rounded-xl bg-[#0a0f18] border border-white/[0.06] space-y-1">
                                                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">{{ $t['stat_1_title'] }}</div>
                                                <div class="font-headline font-bold text-white text-lg">{{ $t['stat_1_value'] }}</div>
                                                <p class="text-xs text-emerald-400 font-medium">{{ $t['stat_1_sub'] }}</p>
                                            </div>
                                        @endif
                                        @if(!empty($t['stat_2_title']))
                                            <div class="p-4 rounded-xl bg-[#0a0f18] border border-white/[0.06] space-y-1">
                                                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">{{ $t['stat_2_title'] }}</div>
                                                <div class="font-headline font-bold text-white text-base">{{ $t['stat_2_value'] }}</div>
                                                <p class="text-xs text-cyan-400 font-medium">{{ $t['stat_2_sub'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Slide Navigation Indicators & Auto Progress Bar -->
            <div class="flex items-center justify-between gap-4 mt-6 pt-4 border-t border-white/[0.06]">
                <div class="flex items-center gap-2">
                    <template x-for="(item, index) in total" :key="index">
                        <button 
                            type="button"
                            @click="goTo(index)" 
                            class="relative h-2 rounded-full overflow-hidden transition-all duration-300 cursor-pointer"
                            :class="current === index ? 'w-8 bg-white/20' : 'w-2 bg-white/10 hover:bg-white/20'"
                            :aria-label="`Go to slide ${index + 1}`"
                        >
                            <div 
                                x-show="current === index" 
                                class="absolute inset-y-0 left-0 bg-emerald-400 rounded-full transition-all duration-100 ease-linear"
                                :style="`width: ${progress}%;`"
                            ></div>
                        </button>
                    </template>
                </div>

                <div class="flex items-center gap-2 text-xs font-mono text-gray-400">
                    <span class="flex items-center gap-1.5 px-2.5 py-0.5 rounded bg-[#070b12] border border-white/10 text-[11px]">
                        <span class="w-1.5 h-1.5 rounded-full" :class="isPaused ? 'bg-amber-400' : 'bg-emerald-400'"></span>
                        <span x-text="isPaused ? 'Paused' : 'Auto-sliding'"></span>
                    </span>
                    <span class="text-gray-600">|</span>
                    <span class="text-white font-medium" x-text="`${String(current + 1).padStart(2, '0')} / ${String(total).padStart(2, '0')}`"></span>
                </div>
            </div>

            <!-- Proof Metrics Bar -->
            <div class="mt-10 p-5 rounded-xl bg-[#070b12] border border-white/10 grid grid-cols-2 md:grid-cols-4 gap-4 text-center font-body">
                <div class="space-y-0.5">
                    <div class="text-xl sm:text-2xl font-headline font-bold text-white">{{ $this->activeGymsCount }}</div>
                    <div class="text-xs text-gray-400">Active Gyms in India</div>
                </div>
                <div class="space-y-0.5">
                    <div class="text-xl sm:text-2xl font-headline font-bold text-emerald-400">{{ $this->monthlyDuesProcessed }}</div>
                    <div class="text-xs text-gray-400">Monthly Dues Processed</div>
                </div>
                <div class="space-y-0.5">
                    <div class="text-xl sm:text-2xl font-headline font-bold text-cyan-400">0.2s</div>
                    <div class="text-xs text-gray-400">Gate Response Speed</div>
                </div>
                <div class="space-y-0.5">
                    <div class="text-xl sm:text-2xl font-headline font-bold text-gray-200">99.9%</div>
                    <div class="text-xs text-gray-400">WhatsApp Delivery Rate</div>
                </div>
            </div>
        </div>
    </section>
    @endif

    <!-- Section 4: Built for Your Specific Gym Setup -->
    <section class="py-20 bg-[#07090e] border-b border-white/[0.06]">
        <div class="container mx-auto px-6 max-w-7xl">
            <div class="text-center max-w-2xl mx-auto mb-14 space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#0d1520] border border-emerald-500/20 text-xs font-semibold text-emerald-400">
                    <span>Gym Configurations</span>
                </div>
                <h2 class="font-headline font-bold text-2xl sm:text-4xl text-white tracking-tight">
                    Built for your specific gym setup
                </h2>
                <p class="text-gray-400 text-sm font-body">
                    Whether you operate a local strength gym, a boutique studio, or a multi-branch chain, FluxFitness fits your workflow.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 font-body">
                <!-- Gym Type 1: Commercial & Strength Gym -->
                <div class="p-6 rounded-2xl bg-[#0a0f18] border border-white/10 space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                        <span class="material-symbols-outlined text-xl">fitness_center</span>
                    </div>
                    <div>
                        <h3 class="font-headline font-semibold text-base text-white">Commercial & Strength Gyms</h3>
                        <p class="text-xs text-gray-400 mt-1">High-volume member check-ins, monthly/quarterly packages, turnstile gates, and trainer incentives.</p>
                    </div>
                    <ul class="space-y-2 text-xs text-gray-300 border-t border-white/5 pt-3">
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>Tripod turnstile & biometric machine sync</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>Automated WhatsApp renewal payment links</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>GST billing & supplement sales POS</span>
                        </li>
                    </ul>
                </div>

                <!-- Gym Type 2: Boutique Studios, Yoga & Crossfit -->
                <div class="p-6 rounded-2xl bg-[#0a0f18] border border-white/10 space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <span class="material-symbols-outlined text-xl">self_improvement</span>
                    </div>
                    <div>
                        <h3 class="font-headline font-semibold text-base text-white">Studios, Yoga & CrossFit</h3>
                        <p class="text-xs text-gray-400 mt-1">Class-based scheduling, batch capacity caps, personal training sessions, and trainer commission tallies.</p>
                    </div>
                    <ul class="space-y-2 text-xs text-gray-300 border-t border-white/5 pt-3">
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>Class scheduling & slot reservation caps</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>Trainer session tracking & commission payout logs</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>Member diet & workout plan sharing</span>
                        </li>
                    </ul>
                </div>

                <!-- Gym Type 3: Multi-Branch Gym Chains -->
                <div class="p-6 rounded-2xl bg-[#0a0f18] border border-white/10 space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <span class="material-symbols-outlined text-xl">domain</span>
                    </div>
                    <div>
                        <h3 class="font-headline font-semibold text-base text-white">Multi-Branch Gym Chains</h3>
                        <p class="text-xs text-gray-400 mt-1">Centralized franchise oversight, cross-branch roaming passes, and role-based staff permissions.</p>
                    </div>
                    <ul class="space-y-2 text-xs text-gray-300 border-t border-white/5 pt-3">
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>Central owner cockpit for all branch revenues</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>Cross-branch roaming pass support</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-400 text-sm">check</span>
                            <span>Strict branch manager & receptionist roles</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 5: Hardware & Device Ecosystem -->
    <section class="py-16 bg-[#0a0f18] border-b border-white/[0.06]">
        <div class="container mx-auto px-6 max-w-6xl">
            <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                <div class="space-y-2 max-w-md">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-cyan-400">
                        <span class="material-symbols-outlined text-sm">hardware</span>
                        <span>Hardware Compatibility</span>
                    </div>
                    <h3 class="font-headline font-bold text-xl sm:text-2xl text-white">Works with your existing biometric machines</h3>
                    <p class="text-xs text-gray-400 font-body leading-relaxed">
                        No need to buy expensive proprietary hardware. FluxFitness connects directly with standard biometric devices and turnstiles.
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 flex-1 font-headline font-medium text-xs text-center">
                    <div class="p-3.5 rounded-xl bg-[#070b12] border border-white/10 space-y-1">
                        <span class="material-symbols-outlined text-xl text-emerald-400">fingerprint</span>
                        <div class="text-white font-semibold">eSSL Machines</div>
                        <div class="text-[10px] text-gray-400 font-body">Biometric & Facial</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-[#070b12] border border-white/10 space-y-1">
                        <span class="material-symbols-outlined text-xl text-cyan-400">face</span>
                        <div class="text-white font-semibold">Realtime & ZKTeco</div>
                        <div class="text-[10px] text-gray-400 font-body">LAN & Cloud Sync</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-[#070b12] border border-white/10 space-y-1">
                        <span class="material-symbols-outlined text-xl text-amber-400">door_sliding</span>
                        <div class="text-white font-semibold">Tripod Turnstiles</div>
                        <div class="text-[10px] text-gray-400 font-body">Flap & Full Height</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-[#070b12] border border-white/10 space-y-1">
                        <span class="material-symbols-outlined text-xl text-emerald-400">qr_code_2</span>
                        <div class="text-white font-semibold">Dynamic UPI QR</div>
                        <div class="text-[10px] text-gray-400 font-body">Instant Auto-Receipt</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @php
        $faqsList = $faqs ?? [];
    @endphp

    <!-- Section 6: Compact Demo FAQs (Objection Handling) -->
    <section class="py-20 bg-[#07090e] border-b border-white/[0.06]" x-data="{ activeFaq: null }">
        <div class="container mx-auto px-6 max-w-4xl">
            <!-- Section Header -->
            <div class="text-center max-w-2xl mx-auto mb-12 space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#0d1520] border border-cyan-500/20 text-xs font-semibold text-cyan-400">
                    <span>Common Questions</span>
                </div>
                <h2 class="font-headline font-bold text-2xl sm:text-4xl text-white tracking-tight">
                    Frequently Asked Questions
                </h2>
                <p class="text-gray-400 text-sm font-body">
                    Everything you need to know about setup, biometric machines, and Excel data migration.
                </p>
            </div>

            <!-- FAQ Accordion List -->
            <div class="space-y-3 font-body">
                @forelse($faqsList as $fIdx => $faq)
                    <div 
                        class="rounded-xl border transition-all duration-200 overflow-hidden"
                        :class="activeFaq === {{ $fIdx }} 
                            ? 'bg-[#0d1522] border-cyan-500/30' 
                            : 'bg-[#0a0f18] border-white/10 hover:border-white/20'"
                    >
                        <!-- Question Header Button -->
                        <button
                            type="button"
                            id="faq-button-{{ $fIdx }}"
                            @click="activeFaq = (activeFaq === {{ $fIdx }} ? null : {{ $fIdx }})"
                            class="w-full p-5 text-left flex items-center justify-between gap-4 cursor-pointer select-none"
                            :aria-expanded="activeFaq === {{ $fIdx }}"
                            aria-controls="faq-panel-{{ $fIdx }}"
                        >
                            <div class="space-y-1 pr-2">
                                @if(!empty($faq['tag']))
                                    <span class="inline-block text-[10px] font-mono font-medium uppercase tracking-wider text-cyan-400/90 bg-cyan-500/10 px-2 py-0.5 rounded">
                                        {{ $faq['tag'] }}
                                    </span>
                                @endif
                                <h3 class="font-headline font-semibold text-sm sm:text-base text-white leading-snug">
                                    {{ $faq['question'] }}
                                </h3>
                            </div>

                            <div 
                                class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 transition-transform duration-200 border"
                                :class="activeFaq === {{ $fIdx }} 
                                    ? 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30 rotate-180' 
                                    : 'bg-white/5 text-gray-400 border-white/10'"
                            >
                                <span class="material-symbols-outlined text-base">expand_more</span>
                            </div>
                        </button>

                        <!-- Accordion Body -->
                        <div 
                            id="faq-panel-{{ $fIdx }}"
                            role="region"
                            aria-labelledby="faq-button-{{ $fIdx }}"
                            x-show="activeFaq === {{ $fIdx }}"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="px-5 pb-5 pt-3.5 text-xs sm:text-sm text-gray-300 leading-relaxed border-t border-white/5"
                        >
                            <p>{{ $faq['answer'] }}</p>
                        </div>
                    </div>
                @empty
                    <div class="p-6 rounded-xl bg-[#0a0f18] border border-white/10 text-center text-gray-400 text-sm">
                        No FAQs available at the moment. Please contact us if you have any questions.
                    </div>
                @endforelse
            </div>

            <!-- Bottom Question Link -->
            <div class="mt-8 p-4 rounded-xl bg-[#0a0f18] border border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-body">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 flex-shrink-0">
                        <span class="material-symbols-outlined text-base">forum</span>
                    </div>
                    <div>
                        <div class="font-headline font-semibold text-white text-xs sm:text-sm">Have a different question?</div>
                        <p class="text-gray-400 text-xs">Chat directly with our solutions team on WhatsApp.</p>
                    </div>
                </div>

                @if(config('app.contact_phone'))
                    <a 
                        href="{{ $this->whatsAppUrl }}"
                        target="_blank"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-[#25D366]/15 hover:bg-[#25D366]/25 border border-[#25D366]/30 text-[#25D366] font-medium transition-all whitespace-nowrap cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-sm">chat</span>
                        <span>Ask on WhatsApp</span>
                    </a>
                @endif
            </div>
        </div>
    </section>

    <!-- Section 7: Final Conversion CTA Banner -->
    <section class="py-20 bg-[#07090e]">
        <div class="container mx-auto px-6 max-w-4xl">
            <div class="rounded-2xl border border-white/10 bg-[#0a0f18] p-8 sm:p-12 text-center space-y-6 shadow-xl">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs font-semibold text-emerald-400">
                    <span>Book Your Walkthrough</span>
                </div>

                <!-- Heading & Subtitle -->
                <div class="space-y-3 max-w-2xl mx-auto">
                    <h2 class="font-headline font-bold text-2xl sm:text-4xl text-white tracking-tight leading-tight">
                        Ready to automate fee collection and member access?
                    </h2>
                    <p class="text-gray-300 text-sm sm:text-base font-body leading-relaxed">
                        Join {{ $this->activeGymsCount }} fitness centers across India. Schedule your 20-minute 1-on-1 walkthrough to see FluxFitness live.
                    </p>
                </div>

                <!-- Action Buttons Grid -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2 font-headline">
                    <button
                        type="button"
                        onclick="document.getElementById('name')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); document.getElementById('name')?.focus();"
                        class="w-full sm:w-auto px-6 py-3 bg-[#22c55e] hover:bg-[#16a34a] text-black font-bold text-sm rounded-xl transition-all uppercase tracking-wider cursor-pointer flex items-center justify-center gap-2"
                    >
                        <span>Schedule Free Demo</span>
                        <span class="material-symbols-outlined text-base font-bold">arrow_upward</span>
                    </button>

                    @if(config('app.contact_phone'))
                        <a
                            href="{{ $this->whatsAppUrl }}"
                            target="_blank"
                            class="w-full sm:w-auto px-6 py-3 bg-[#25D366]/10 hover:bg-[#25D366]/20 border border-[#25D366]/30 text-[#25D366] font-medium text-sm rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-base">chat</span>
                            <span>Chat on WhatsApp</span>
                        </a>
                    @endif
                </div>

                <!-- Trust Badges Footer Strip -->
                <div class="pt-6 border-t border-white/[0.06] flex flex-wrap items-center justify-center gap-6 text-xs text-gray-400 font-medium font-body">
                    <span class="flex items-center gap-1.5 text-emerald-400">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        No credit card required
                    </span>
                    <span class="flex items-center gap-1.5 text-cyan-400">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        Free Excel data migration
                    </span>
                    <span class="flex items-center gap-1.5 text-gray-300">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        24-Hour setup guarantee
                    </span>
                </div>
            </div>
        </div>
    </section>
</div>
