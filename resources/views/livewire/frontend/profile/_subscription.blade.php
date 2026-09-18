<!-- 1. Current Subscription Status Card -->
<div id="subscription-status" class="bg-[#0d1520]/90 border border-white/10 rounded-2xl p-6 sm:p-8 shadow-[0_0_40px_rgba(0,0,0,0.6)] backdrop-blur-xl relative overflow-hidden">
    <div class="flex items-center justify-between pb-4 mb-6 border-b border-white/10">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-emerald-400 text-2xl">verified_user</span>
            <h3 class="font-headline font-bold text-xl text-white uppercase tracking-tight">Active Subscription</h3>
        </div>
        @if ($this->subscription && $this->subscription->isActive())
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                {{ $this->subscription->status->value }}
            </span>
        @elseif($this->subscription && $this->subscription->isExpired())
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-rose-500/15 text-rose-400 border border-rose-500/30">
                Expired
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500/15 text-amber-400 border border-amber-500/30">
                No Subscription
            </span>
        @endif
    </div>

    @if ($this->subscription && $this->subscription->isActive())
        <!-- Active Subscription Panel -->
        <div class="space-y-6">
            <div class="p-6 rounded-2xl bg-gradient-to-r from-emerald-950/30 via-[#0a1518] to-cyan-950/30 border border-emerald-500/30 relative overflow-hidden">
                <div class="grid md:grid-cols-3 gap-6 items-center">
                    <!-- Plan Title & Badge -->
                    <div class="space-y-1">
                        <div class="text-[11px] font-bold uppercase tracking-widest text-emerald-400 font-mono">Current Tier</div>
                        <div class="font-headline font-black text-3xl text-white tracking-tight uppercase">
                            {{ $this->currentPlan?->name ?? 'Custom Plan' }}
                        </div>
                    </div>

                    <!-- Price & Cycle -->
                    <div class="space-y-1">
                        <div class="text-[11px] font-bold uppercase tracking-widest text-gray-400 font-mono">Price & Billing</div>
                        <div class="text-2xl font-headline font-black text-white flex items-baseline gap-1.5">
                            <span>{{ $this->subscription->currency->getSymbol() ?? '₹' }}{{ number_format((float) $this->subscription->price, 2) }}</span>
                            <span class="text-xs text-gray-400 font-medium font-body uppercase">/ {{ $this->subscription->billing_cycle }}</span>
                        </div>
                    </div>

                    <!-- Expiration / Trial Status -->
                    <div class="space-y-1">
                        @if ($this->subscription->isOnTrial())
                            <div class="text-[11px] font-bold uppercase tracking-widest text-cyan-400 font-mono">Trial Remaining</div>
                            <div class="text-xl font-headline font-black text-cyan-300 flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">timelapse</span>
                                <span>{{ $this->subscription->trialDaysLeft() }} Days Left</span>
                            </div>
                        @else
                            <div class="text-[11px] font-bold uppercase tracking-widest text-gray-400 font-mono">Next Billing Date</div>
                            <div class="text-lg font-headline font-bold text-white flex items-center gap-2">
                                <span class="material-symbols-outlined text-emerald-400 text-lg">event</span>
                                <span>{{ $this->subscription->current_period_end?->format('M d, Y') ?? 'N/A' }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Renewal Button (if needed) -->
            <div class="flex flex-wrap items-center gap-4">
                <button wire:click="renewPlan" wire:loading.attr="disabled"
                    class="group relative overflow-hidden inline-flex items-center justify-center gap-2.5 bg-[#22c55e] hover:bg-[#16a34a] text-black font-headline font-black text-xs sm:text-sm px-6 py-3.5 rounded-xl shadow-[0_0_20px_rgba(34,197,94,0.3)] hover:shadow-[0_0_25px_rgba(34,197,94,0.6)] active:scale-[0.98] transition-all uppercase tracking-wider disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                    <span class="absolute inset-0 w-1/2 h-full bg-white/30 transform -skew-x-12 -translate-x-full group-hover:translate-x-[300%] transition-transform duration-1000 ease-out"></span>
                    <span wire:loading.remove wire:target="renewPlan" class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg font-bold">autorenew</span>
                        <span>Renew Subscription</span>
                    </span>
                    <span wire:loading.flex wire:target="renewPlan" class="items-center gap-2">
                        <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Processing Renewal...</span>
                    </span>
                </button>
            </div>
        </div>

    @elseif($this->subscription && $this->subscription->isExpired())
        <!-- Expired State Notice -->
        <div class="p-6 rounded-2xl bg-rose-500/10 border border-rose-500/30 space-y-4">
            <div class="flex items-center gap-3 text-rose-400">
                <span class="material-symbols-outlined text-3xl">error</span>
                <div>
                    <h4 class="font-headline font-bold text-lg text-white">Subscription Has Expired</h4>
                    <p class="text-xs text-rose-200/80">Your subscription period has ended. Renew now to restore full access to biometric check-in, attendance, and member management.</p>
                </div>
            </div>
            <button wire:click="renewPlan" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 bg-gradient-to-r from-rose-500 to-orange-500 text-white font-headline font-bold text-sm px-6 py-3.5 rounded-xl shadow-[0_0_20px_rgba(244,63,94,0.3)] hover:brightness-110 active:scale-[0.98] transition-all uppercase tracking-wider cursor-pointer disabled:opacity-50">
                <span wire:loading.remove wire:target="renewPlan" class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">published_with_changes</span>
                    <span>Renew Subscription</span>
                </span>
                <span wire:loading.flex wire:target="renewPlan" class="items-center gap-2">
                    <svg class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Renewing...</span>
                </span>
            </button>
        </div>

    @else
        <!-- No Active Subscription State -->
        <div class="p-6 rounded-2xl bg-cyan-950/20 border border-cyan-500/20 space-y-3">
            <div class="flex items-center gap-3 text-cyan-400">
                <span class="material-symbols-outlined text-3xl">info</span>
                <div>
                    <h4 class="font-headline font-bold text-lg text-white">No Active Subscription</h4>
                    <p class="text-xs text-gray-400">Select one of our high-performance gym management plans below to get started with automated WhatsApp chasers, billing, and member check-ins.</p>
                </div>
            </div>
        </div>
    @endif
</div>
