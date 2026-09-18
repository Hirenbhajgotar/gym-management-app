<!-- Available Plans Grid Section (Billed Annually) -->
<div id="available-plans" class="bg-[#0d1520]/90 border border-white/10 rounded-2xl p-6 sm:p-8 shadow-[0_0_40px_rgba(0,0,0,0.6)] backdrop-blur-xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/10">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[#cafd00] text-2xl">workspace_premium</span>
                <h3 class="font-headline font-bold text-xl text-white uppercase tracking-tight">Available Subscription Plans</h3>
            </div>
            <p class="text-xs text-gray-400">Upgrade or adjust your gym management tier at any time</p>
        </div>

        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold font-mono bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 self-start sm:self-auto">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>BILLED ANNUALLY • SAVE 20%</span>
        </span>
    </div>

    @if (session()->has('plan_error'))
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-2">
            <span class="material-symbols-outlined text-base">error</span>
            <span>{{ session('plan_error') }}</span>
        </div>
    @endif

    @if (session()->has('plan_success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs flex items-center gap-2">
            <span class="material-symbols-outlined text-base">check_circle</span>
            <span>{{ session('plan_success') }}</span>
        </div>
    @endif

    <div class="grid md:grid-cols-2 gap-6">
        @forelse ($this->plans as $plan)
            @php
                $isCurrent = $this->currentPlan && $this->currentPlan->id === $plan->id;
                $monthlyEquivalent = (float) ($plan->price_monthly > 0 ? $plan->price_monthly : ($plan->price_yearly > 0 ? $plan->price_yearly / 12 : 0));
            @endphp
            <div wire:key="plan-{{ $plan->id }}" class="rounded-2xl p-6 sm:p-7 border transition-all duration-300 relative flex flex-col justify-between {{ $isCurrent ? 'bg-gradient-to-b from-emerald-950/30 to-[#0d1520] border-emerald-500/50 shadow-[0_0_25px_rgba(16,185,129,0.15)]' : 'bg-[#0a0f18]/90 border-white/10 hover:border-cyan-400/40 hover:shadow-[0_0_25px_rgba(6,182,212,0.1)]' }}">
                
                <!-- Optional Badge -->
                @if (!empty($plan->badge))
                    <div class="absolute -top-3 right-6">
                        <span class="bg-[#cafd00] text-black font-headline font-black text-[10px] uppercase tracking-widest px-3 py-1 rounded-full shadow-[0_0_15px_rgba(202,253,0,0.4)]">
                            {{ $plan->badge }}
                        </span>
                    </div>
                @endif

                <div class="space-y-4">
                    <div class="space-y-1">
                        <h4 class="font-headline font-black text-2xl text-white tracking-tight uppercase">{{ $plan->name }}</h4>
                        <p class="text-xs text-gray-400 line-clamp-2 leading-relaxed">{{ $plan->brief_description }}</p>
                    </div>

                    <!-- Price Display (Billed Annually) -->
                    <div class="pt-2 space-y-1">
                        @if ($plan->isFree())
                            <div class="font-headline font-black text-3xl sm:text-4xl text-emerald-400">Free</div>
                            <div class="text-xs text-gray-400 font-mono mt-1">No credit card required</div>
                        @else
                            <div class="flex items-baseline gap-1">
                                <span class="font-headline font-black text-3xl sm:text-4xl text-white">
                                    {{ $plan->currency->getSymbol() }}{{ number_format((float) $plan->price_yearly) }}
                                </span>
                                <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">/ Year</span>
                            </div>
                            <div class="text-[11px] font-mono text-emerald-400">
                                ≈ {{ $plan->currency->getSymbol() }}{{ number_format($monthlyEquivalent, 0) }}/month billed annually
                            </div>
                        @endif

                        @if ($plan->hasTrial())
                            <div class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 rounded-lg">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                <span>{{ $plan->trial_days }} Days Free Trial</span>
                            </div>
                        @endif
                    </div>

                    <!-- Features List -->
                    <div class="space-y-2.5 py-4 border-t border-white/10 text-xs">
                        @foreach ($plan->features->take(4) as $feature)
                            <div class="flex items-start gap-2.5 text-gray-300">
                                <span class="material-symbols-outlined text-emerald-400 text-base flex-shrink-0 mt-0.5">check_circle</span>
                                <span>{!! $feature->brief_description !!}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 border-t border-white/10">
                    @if ($isCurrent)
                        @if ($this->subscription && $this->subscription->isOnTrial())
                            <button wire:click="renewPlan" wire:loading.attr="disabled"
                                class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-gradient-to-r from-cyan-500 to-emerald-500 text-black font-headline font-bold text-xs rounded-xl shadow-[0_0_20px_rgba(6,182,212,0.3)] hover:brightness-110 transition-all uppercase tracking-wider cursor-pointer disabled:opacity-50">
                                <span wire:loading.remove wire:target="renewPlan" class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base font-bold">autorenew</span>
                                    <span>Active Trial • Convert to Paid</span>
                                </span>
                                <span wire:loading.flex wire:target="renewPlan" class="items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Converting...</span>
                                </span>
                            </button>
                        @else
                            <button disabled class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-surface-container-high border border-white/10 text-gray-400 font-headline font-bold text-xs rounded-xl cursor-not-allowed uppercase tracking-wider">
                                <span class="material-symbols-outlined text-sm text-emerald-400">check</span>
                                <span>Current Active Plan</span>
                            </button>
                        @endif
                    @elseif($this->subscription && $this->subscription->isActive())
                        <!-- Upgrade Action -->
                        <button wire:click="upgradePlan({{ $plan->id }})" wire:loading.attr="disabled"
                            class="w-full group relative overflow-hidden inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-[#22c55e] hover:bg-[#16a34a] text-black font-headline font-black text-xs rounded-xl shadow-[0_0_20px_rgba(34,197,94,0.3)] hover:shadow-[0_0_25px_rgba(34,197,94,0.6)] active:scale-[0.98] transition-all uppercase tracking-wider cursor-pointer disabled:opacity-50">
                            <span class="absolute inset-0 w-1/2 h-full bg-white/30 transform -skew-x-12 -translate-x-full group-hover:translate-x-[300%] transition-transform duration-1000 ease-out"></span>
                            <span wire:loading.remove wire:target="upgradePlan({{ $plan->id }})" class="flex items-center gap-1.5">
                                <span>Upgrade to {{ $plan->name }}</span>
                                <span class="material-symbols-outlined text-base group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </span>
                            <span wire:loading.flex wire:target="upgradePlan({{ $plan->id }})" class="items-center gap-2">
                                <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Upgrading...</span>
                            </span>
                        </button>
                    @else
                        <!-- Select Plan / Free Trial Action -->
                        @php
                            $btnLabel = 'Select Plan';
                            if ($plan->isFree()) {
                                $btnLabel = 'Get Started Free';
                            } elseif ($plan->hasTrial() && !$this->hasUsedTrial) {
                                $btnLabel = "Start {$plan->trial_days}-Day Trial";
                            }
                        @endphp
                        <button wire:click="selectPlan({{ $plan->id }}, '{{ \App\Enums\RecurringFrequency::YEARLY->value }}')" wire:loading.attr="disabled"
                            class="w-full group relative overflow-hidden inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-gradient-to-r from-cyan-400 to-emerald-400 hover:from-cyan-300 hover:to-emerald-300 text-black font-headline font-black text-xs rounded-xl shadow-[0_0_20px_rgba(6,182,212,0.3)] active:scale-[0.98] transition-all uppercase tracking-wider cursor-pointer disabled:opacity-50">
                            <span class="absolute inset-0 w-1/2 h-full bg-white/30 transform -skew-x-12 -translate-x-full group-hover:translate-x-[300%] transition-transform duration-1000 ease-out"></span>
                            <span wire:loading.remove wire:target="selectPlan({{ $plan->id }}, '{{ \App\Enums\RecurringFrequency::YEARLY->value }}')" class="flex items-center gap-1.5">
                                <span>{{ $btnLabel }}</span>
                                <span class="material-symbols-outlined text-base group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </span>
                            <span wire:loading.flex wire:target="selectPlan({{ $plan->id }}, '{{ \App\Enums\RecurringFrequency::YEARLY->value }}')" class="items-center gap-2">
                                <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Processing...</span>
                            </span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full py-8 text-center text-gray-400">
                <span class="material-symbols-outlined text-4xl text-gray-600 mb-2">inventory_2</span>
                <p class="text-sm font-medium">No subscription plans are currently available.</p>
            </div>
        @endforelse
    </div>
</div>
