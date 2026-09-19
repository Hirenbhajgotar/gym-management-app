<!-- Flash Messages (Success / Error alerts) -->
@if (session()->has('success'))
    <div class="mb-8 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 shadow-[0_0_20px_rgba(16,185,129,0.15)] flex items-start gap-3 animate-fade-in">
        <span class="material-symbols-outlined text-emerald-400 text-2xl flex-shrink-0 mt-0.5">check_circle</span>
        <div class="flex-1 text-sm font-medium text-emerald-200">
            {{ session('success') }}
        </div>
    </div>
@endif

@if (session()->has('error'))
    <div class="mb-8 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 shadow-[0_0_20px_rgba(244,63,94,0.15)] flex items-start gap-3 animate-fade-in">
        <span class="material-symbols-outlined text-rose-400 text-2xl flex-shrink-0 mt-0.5">error</span>
        <div class="flex-1 text-sm font-medium text-rose-200">
            {{ session('error') }}
        </div>
    </div>
@endif

<!-- Profile Hero Header -->
<div class="space-y-6 mb-12 animate-slide-up">
    <!-- Status Badge Pill -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-[#0d1520]/90 border border-emerald-500/40 text-xs font-bold uppercase tracking-widest text-emerald-400 shadow-[0_0_15px_rgba(16,185,129,0.15)]">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
            </span>
            <span>ACCOUNT CONTROL CENTER • LIVE OPERATIONAL STATUS</span>
        </div>

        <div class="hidden sm:inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-[11px] font-mono text-gray-400">
            <span class="material-symbols-outlined text-xs text-cyan-400">lock</span>
            <span>256-BIT ENCRYPTED PORTAL</span>
        </div>
    </div>

    <!-- Authentic Kinetic Headline & Live Stats Header -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div class="space-y-2">
            <h1 class="font-headline font-black text-4xl sm:text-5xl lg:text-6xl leading-[0.95] tracking-tighter text-white uppercase">
                WELCOME BACK, <br />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-emerald-300 to-[#cafd00] drop-shadow-[0_0_30px_rgba(6,182,212,0.4)]">
                    {{ $this->user->name }}
                </span>
            </h1>
            <p class="text-gray-400 font-medium text-base sm:text-lg max-w-2xl">
                Monitor your multi-branch gym locations, system telemetry, active membership limits, billing invoices, and plan upgrades.
            </p>
        </div>

        <!-- Live Quick Stats Badges -->
        <div class="flex flex-wrap items-center gap-3 sm:gap-4 bg-[#0d1520]/90 border border-white/10 rounded-2xl p-3 sm:p-4 backdrop-blur-xl shadow-2xl">
            <div class="px-3 sm:px-4 py-1 text-center border-r border-white/10">
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 font-mono">Gyms</div>
                <div class="text-xl sm:text-2xl font-headline font-black text-white">{{ $this->totalGymsCount }}</div>
            </div>
            <div class="px-3 sm:px-4 py-1 text-center border-r border-white/10">
                <div class="text-[10px] font-bold uppercase tracking-widest text-cyan-400 font-mono">Members</div>
                <div class="text-xl sm:text-2xl font-headline font-black text-cyan-300">{{ number_format($this->totalMembersCount) }}</div>
            </div>
            <div class="px-3 sm:px-4 py-1 text-center border-r border-white/10">
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 font-mono">Invoices</div>
                <div class="text-xl sm:text-2xl font-headline font-black text-white">{{ $this->latestPayments->count() }}</div>
            </div>
            <div class="px-3 sm:px-4 py-1 text-center">
                <div class="text-[10px] font-bold uppercase tracking-widest text-emerald-400 font-mono">Plan Tier</div>
                <div class="text-xs sm:text-sm font-headline font-bold text-emerald-400 uppercase tracking-wider">
                    {{ $this->currentPlan?->name ?? 'Free Tier' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Shortcut Pills Bar -->
    <div class="flex flex-wrap items-center gap-2.5 pt-2">
        <a href="#your-gyms" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface-container-low/80 hover:bg-surface-container-high border border-white/10 hover:border-cyan-400/40 text-xs font-semibold text-gray-300 hover:text-white transition-all">
            <span class="material-symbols-outlined text-sm text-cyan-400">store</span>
            <span>Your Gyms ({{ $this->totalGymsCount }})</span>
        </a>
        <a href="#subscription-status" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface-container-low/80 hover:bg-surface-container-high border border-white/10 hover:border-emerald-400/40 text-xs font-semibold text-gray-300 hover:text-white transition-all">
            <span class="material-symbols-outlined text-sm text-emerald-400">verified_user</span>
            <span>Subscription Status</span>
        </a>
        <a href="#available-plans" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface-container-low/80 hover:bg-surface-container-high border border-white/10 hover:border-[#cafd00]/40 text-xs font-semibold text-gray-300 hover:text-white transition-all">
            <span class="material-symbols-outlined text-sm text-[#cafd00]">workspace_premium</span>
            <span>Upgrade Tier</span>
        </a>
        <a href="#invoices-history" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface-container-low/80 hover:bg-surface-container-high border border-white/10 hover:border-cyan-400/40 text-xs font-semibold text-gray-300 hover:text-white transition-all">
            <span class="material-symbols-outlined text-sm text-cyan-400">receipt_long</span>
            <span>Invoices & Billing</span>
        </a>
        <button @click="showSettingsModal = true; settingsTab = 'security'" type="button" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface-container-low/80 hover:bg-surface-container-high border border-white/10 hover:border-emerald-400/40 text-xs font-semibold text-gray-300 hover:text-white transition-all cursor-pointer">
            <span class="material-symbols-outlined text-sm text-emerald-400">shield</span>
            <span>Security Settings</span>
        </button>
    </div>
</div>
