<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.blank')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div>
    
    <main class="min-h-screen flex flex-col md:flex-row">
        <!-- LEFT SIDE: FORGOT PASSWORD FORM PANEL -->
        <section class="w-full md:w-[45%] lg:w-[45%] bg-surface-container-lowest relative flex flex-col px-8 md:px-16 py-12">
            <!-- TopAppBar Branding Context -->
            <header class="flex justify-between items-center mb-20">
                <a href="{{ route('home') }}" wire:navigate>
                    <div class="flex items-center gap-2 text-2xl font-black italic tracking-tighter text-tertiary font-space-grotesk uppercase">
                        <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">all_inclusive</span>
                        <span>FluxFitness</span>
                    </div>
                </a>
                <a class="text-on-surface-variant hover:brightness-110 flex items-center gap-2 text-sm font-semibold transition-colors" href="{{ route('login') }}" wire:navigate>
                    <span class="material-symbols-outlined text-lg">arrow_back</span>
                    Back to Login
                </a>
            </header>

            <div class="flex-grow flex flex-col justify-center max-w-md mx-auto w-full">
                <!-- Lock Icon with Glow -->
                <div class="mb-8 relative inline-flex ">
                    <div class="absolute inset-0 bg-tertiary/20 blur-2xl rounded-full"></div>
                    <div class="relative bg-surface-container-high p-4 w-14 h-14 rounded-2xl border border-outline-variant/50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-3xl text-tertiary" data-icon="lock_reset" style="font-variation-settings: 'FILL' 1;">lock_reset</span>
                    </div>
                </div>

                <h1 class="text-4xl md:text-5xl font-bold font-space-grotesk tracking-tight text-on-surface mb-4">
                    Forgot Password?
                </h1>

                <p class="text-on-surface-variant text-lg leading-relaxed mb-10">
                    No worries! Enter your registered email and we'll send you a reset link.
                </p>

                <!-- Form -->
                <form wire:submit="sendPasswordResetLink" class="space-y-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant ml-1" for="email">Email address</label>
                        <div class="relative group">
                            <input wire:model="email" id="email" type="email" required autofocus autocomplete="email" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-4 text-on-surface focus:outline-none focus:ring-2 focus:ring-tertiary/50 focus:border-tertiary transition-all placeholder:text-outline/50 @error('email') border-error focus:ring-error @enderror" placeholder="Enter your registered email" />
                            <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant group-focus-within:text-tertiary transition-colors">mail</span>
                        </div>
                        @error('email')
                            <p class="text-xs text-error mt-1 ml-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" class="w-full flex items-center justify-center bg-tertiary hover:brightness-110 text-on-tertiary-fixed font-bold py-4 rounded-xl transition-all active:scale-[0.98] shadow-lg shadow-tertiary/10 gap-2 group disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer">
                        <span wire:loading.remove wire:target="sendPasswordResetLink">Send Reset Link</span>
                        <span wire:loading.flex wire:target="sendPasswordResetLink" class="flex items-center gap-2">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Sending...
                        </span>
                        <span wire:loading.remove wire:target="sendPasswordResetLink" class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </button>
                </form>

                <div class="mt-8 text-center">
                    <p class="text-on-surface-variant">
                        Remember your password?
                        <a class="text-tertiary font-bold hover:underline ml-1" href="{{ route('login') }}" wire:navigate>Log In</a>
                    </p>
                </div>

                <!-- Success Status -->
                @if (session('status'))
                <div class="mt-12 p-4 bg-surface-container-high/40 rounded-lg border border-outline-variant/20 flex items-center gap-3">
                    <span class="material-symbols-outlined text-blue">info</span>
                    <span class="text-sm font-medium">{{ session('status') }}</span>
                </div>
                @endif
            </div>

            <!-- Footer Branding (Minimalist) -->
            <footer class="mt-auto pt-12">
                <p class="text-[10px] text-on-surface-variant/40 tracking-[0.2em] uppercase font-medium">
                    © {{ date('Y') }} FluxFitness Global • Secure Authentication Layer
                </p>
            </footer>
        </section>

        <!-- RIGHT SIDE: VISUAL/BANNER PANEL -->
        <section class="hidden md:flex flex-1 relative overflow-hidden bg-slate-950 items-center justify-center">
            <!-- Background Elements -->
            <div class="absolute inset-0 bg-gradient-to-br from-surface-container-lowest via-surface-container-low to-tertiary/5"></div>
            <div class="absolute top-20 right-0  w-80 h-80 bg-tertiary/20 rounded-full blur-[120px]" style="transform: translateX(150px) !important;"></div>
            <div class="absolute bottom-20 left-0 -translate-x-0 w-96 h-96 bg-primary/20 rounded-full blur-[120px]"></div>
            <div class="absolute inset-0 noise-overlay pointer-events-none"></div>

            <!-- Content -->
            <div class="relative z-10 px-16 text-center">
                <div class="mb-12 inline-block">
                    <!-- Geometric Power Symbol SVG -->
                    <x-icons.geometric-power-symbol />
                </div>

                <div class="space-y-4">
                    <h2 class="font-space-grotesk leading-none font-extrabold text-on-surface uppercase tracking-tighter">
                        Continuous<br/>
                        <span class="text-tertiary">Fitness</span><br/>
                        Evolution
                    </h2>
                    <p class="text-secondary-fixed opacity-70 text-xl max-w-sm mx-auto">
                        Your journey doesn't stop here. Recover your access and resume your peak performance.
                    </p>
                </div>

                <!-- Floating Glass Badges -->
                <div class="mt-16 grid grid-cols-1 gap-4 text-left max-w-sm mx-auto">
                    <div class="backdrop-blur-xl bg-surface-container/30 border border-outline-variant/20 p-4 rounded-xl flex items-center gap-4 hover:bg-surface-container/50 transition-all">
                        <div class="h-10 w-10 bg-primary/20 rounded-lg flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined">groups</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-primary tracking-widest uppercase">Community</p>
                            <p class="text-lg font-bold text-on-surface">2,400+ Active Members</p>
                        </div>
                    </div>

                    <div class="backdrop-blur-xl bg-surface-container/30 border border-outline-variant/20 p-4 rounded-xl flex items-center gap-4 hover:bg-surface-container/50 transition-all ml-5">
                        <div class="h-10 w-10 bg-secondary/20 rounded-lg flex items-center justify-center text-secondary">
                            <span class="material-symbols-outlined">monitoring</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-secondary tracking-widest uppercase">Consistency</p>
                            <p class="text-lg font-bold text-on-surface">98% Attendance Rate</p>
                        </div>
                    </div>

                    <div class="backdrop-blur-xl bg-surface-container/30 border border-outline-variant/20 p-4 rounded-xl flex items-center gap-4 hover:bg-surface-container/50 transition-all">
                        <div class="h-10 w-10 bg-tertiary/20 rounded-lg flex items-center justify-center text-tertiary">
                            <span class="material-symbols-outlined">location_on</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-tertiary tracking-widest uppercase">Global Reach</p>
                            <p class="text-lg font-bold text-on-surface">40+ Gym Branches</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Abstract Background Visuals -->
            <div class="absolute bottom-10 right-10 flex gap-2">
                <div class="w-1 h-8 bg-primary/20 rounded-full"></div>
                <div class="w-1 h-12 bg-primary/40 rounded-full"></div>
                <div class="w-1 h-6 bg-primary/60 rounded-full"></div>
            </div>
        </section>
    </main>
</div>