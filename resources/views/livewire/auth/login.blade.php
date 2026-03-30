<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.blank', ['title' => 'Login | Fluxfitness | Gym Software | CRM, Billing, Member Tracking'])] class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('profile', absolute: false));
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }
}; ?>

<div>
    
    <div class="relative min-h-screen flex flex-col md:flex-row bg-surface text-on-surface font-body selection:bg-tertiary selection:text-on-tertiary antialiased overflow-hidden">
        <!-- Noise Texture -->
        <div class="fixed inset-0 noise-overlay z-0"></div>
    
        <!-- Left Side: Login Form -->
        <main class="relative z-10 w-full md:w-[45%] lg:w-[40%] bg-surface-container-low flex items-center justify-center p-8 lg:p-16">
            <div class="w-full max-w-md space-y-10">
                <!-- Brand Anchor Header -->
                <header class="flex items-center gap-3">
                    <a href="{{ route('home') }}" wire:navigate>
                        <div class="flex items-center gap-2 text-2xl font-black italic tracking-tighter text-tertiary font-space-grotesk uppercase">
                            <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">all_inclusive</span>
                            <span>FluxFitness</span>
                        </div>
                    </a>
                </header>
    
                <!-- Welcome Text -->
                <div class="space-y-2">
                    <h1 class="font-headline font-bold text-4xl lg:text-5xl tracking-tight text-on-surface leading-none">Welcome Back</h1>
                    <p class="text-on-surface-variant font-medium text-lg">Log in to manage your gym.</p>
                </div>
    
                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />
    
                <!-- Form -->
                <form wire:submit="login" class="space-y-6">
                    <div class="space-y-4">
                        <!-- Email Field -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant ml-1" for="email">Email address</label>
                            <div class="relative group">
                                <input wire:model="email" id="email" type="email" required autofocus autocomplete="email" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-4 text-on-surface focus:outline-none focus:ring-2 focus:ring-tertiary/50 focus:border-tertiary transition-all placeholder:text-outline/50 @error('email') border-error focus:ring-error @enderror" placeholder="coach@fluxfitness.com" />
                                <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant group-focus-within:text-tertiary transition-colors">mail</span>
                            </div>
                            @error('email')
                                <p class="text-xs text-error mt-1 ml-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
    
                        <!-- Password Field -->
                        <div class="space-y-2" x-data="{ showPassword: false }">
                            <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant ml-1" for="password">Password</label>
                            <div class="relative group">
                                <input wire:model="password" id="password" x-bind:type="showPassword ? 'text' : 'password'" required autocomplete="current-password" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-4 text-on-surface focus:outline-none focus:ring-2 focus:ring-tertiary/50 focus:border-tertiary transition-all placeholder:text-outline/50 @error('password') border-error focus:ring-error @enderror" placeholder="••••••••" />
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface transition-colors focus:outline-none">
                                    <span class="material-symbols-outlined" x-text="showPassword ? 'visibility_off' : 'visibility'">visibility</span>
                                </button>
                            </div>
                            @error('password')
                                <p class="text-xs text-error mt-1 ml-1 font-medium">{{ $message }}</p>
                            @enderror
    
                            <!-- Remember Me & Forgot Password -->
                            <div class="flex items-center justify-between pt-2 ml-1">
                                <label class="relative flex items-center gap-2 cursor-pointer group">
                                    <div class="relative flex items-center justify-center">
                                        <input type="checkbox" wire:model="remember" class="peer appearance-none w-4 h-4 border border-outline-variant/50 rounded-sm bg-surface-container-lowest checked:bg-tertiary checked:border-tertiary transition-colors focus:ring-2 focus:ring-tertiary/50 focus:outline-none cursor-pointer" />
                                        <span class="material-symbols-outlined absolute text-[14px] text-on-tertiary-fixed font-bold opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity">check</span>
                                    </div>
                                    <span class="text-sm font-medium text-on-surface-variant group-hover:text-on-surface transition-colors select-none">Remember me</span>
                                </label>
    
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" wire:navigate class="text-sm font-semibold text-primary hover:text-primary-dim transition-colors">Forgot password?</a>
                                @endif
                            </div>
                        </div>
                    </div>
    
                    <!-- Login Button -->
                    <button type="submit" wire:loading.attr="disabled" class="w-full flex items-center justify-center gap-2 bg-tertiary text-on-tertiary-fixed font-headline font-bold text-lg py-4 rounded-xl shadow-xl shadow-tertiary/10 active:scale-[0.98] transition-all hover:brightness-110 uppercase tracking-wide disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer">
                        <span wire:loading.remove wire:target="login">Log In</span>
                        <span wire:loading.flex wire:target="login" class="flex items-center gap-2">
                            <svg class="w-5 h-5 animate-spin text-on-tertiary-fixed" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Logging in...
                        </span>
                    </button>
    
                    <!-- Divider -->
                    @if (Route::has('register'))
                        <div class="relative flex items-center py-4">
                            <div class="flex-grow border-t border-outline-variant/20"></div>
                            <span class="flex-shrink mx-4 text-xs font-bold uppercase tracking-widest text-outline-variant">or</span>
                            <div class="flex-grow border-t border-outline-variant/20"></div>
                        </div>
                    @endif
    
                    <!-- Sign Up Link -->
                    @if (Route::has('register'))
                        <p class="text-center text-on-surface-variant font-medium">
                            Don't have an account?
                            <a href="{{ route('register') }}" wire:navigate class="text-tertiary font-bold hover:underline underline-offset-4 decoration-2">Sign Up</a>
                        </p>
                    @endif
                </form>
            </div>
        </main>
    
        <!-- Right Side: Visual Banner -->
        <section class="hidden md:flex relative flex-1 bg-surface-container-lowest items-center justify-center overflow-hidden kinetic-gradient">
            <!-- Background Elements -->
            <div class="absolute inset-0 z-0">
                <div class="absolute top-[-10%] right-[-10%] w-[600px] h-[600px] bg-primary/10 blur-[120px] rounded-full"></div>
                <div class="absolute bottom-[-10%] left-[-10%] w-[600px] h-[600px] bg-tertiary/5 blur-[120px] rounded-full"></div>
            </div>
    
            <!-- Dashboard Mockup Composition -->
            <div class="relative z-10 w-full max-w-2xl px-12">
                <div class="relative group">
                    <!-- Main Dashboard Window -->
                    <div class="glass-card rounded-2xl p-8 shadow-2xl relative overflow-hidden transform rotate-[-2deg] transition-transform duration-700 group-hover:rotate-0">
                        <div class="flex items-center justify-between mb-8">
                            <div class="space-y-1">
                                <div class="h-2 w-24 bg-primary/30 rounded-full"></div>
                                <div class="h-2 w-16 bg-outline-variant/30 rounded-full"></div>
                            </div>
                            <div class="flex gap-2">
                                <div class="w-2 h-2 rounded-full bg-error/40"></div>
                                <div class="w-2 h-2 rounded-full bg-tertiary/40"></div>
                                <div class="w-2 h-2 rounded-full bg-primary/40"></div>
                            </div>
                        </div>
                        <!-- Pulse Chart Simulation -->
                        <div class="space-y-6">
                            <div class="flex items-end gap-2 h-32">
                                <div class="flex-1 bg-primary/20 rounded-t-lg h-[40%]"></div>
                                <div class="flex-1 bg-primary/40 rounded-t-lg h-[65%]"></div>
                                <div class="flex-1 bg-primary/60 rounded-t-lg h-[50%]"></div>
                                <div class="flex-1 bg-primary/80 rounded-t-lg h-[85%]"></div>
                                <div class="flex-1 bg-tertiary rounded-t-lg h-[100%] shadow-[0_0_20px_rgba(197,255,201,0.3)]"></div>
                                <div class="flex-1 bg-primary/50 rounded-t-lg h-[70%]"></div>
                                <div class="flex-1 bg-primary/30 rounded-t-lg h-[45%]"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="h-16 bg-surface-container-highest/50 rounded-xl border border-white/5 p-3">
                                    <div class="h-2 w-12 bg-on-surface-variant/20 rounded mb-2"></div>
                                    <div class="h-4 w-20 bg-tertiary/20 rounded"></div>
                                </div>
                                <div class="h-16 bg-surface-container-highest/50 rounded-xl border border-white/5 p-3">
                                    <div class="h-2 w-12 bg-on-surface-variant/20 rounded mb-2"></div>
                                    <div class="h-4 w-20 bg-primary/20 rounded"></div>
                                </div>
                            </div>
                        </div>
                    </div>
    
                    <!-- Floating Stat Badges -->
                    <div class="absolute -top-10 -right-6 glass-card px-6 py-4 rounded-2xl shadow-xl transform rotate-[5deg] transition-all group-hover:translate-y-[-10px]">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-tertiary/20 rounded-lg">
                                <span class="material-symbols-outlined text-tertiary text-xl" style="font-variation-settings: 'FILL' 1;">trending_up</span>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ config('app.platform_state_badges.members_managed.title') }}</div>
                                <div class="text-xl font-headline font-bold text-on-surface">{{ config('app.platform_state_badges.members_managed.value') }}</div>
                            </div>
                        </div>
                    </div>
    
                    <div class="absolute -bottom-12 -left-8 glass-card px-6 py-4 rounded-2xl shadow-xl transform rotate-[-5deg] transition-all group-hover:translate-y-[10px]">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/20 rounded-lg">
                                <span class="material-symbols-outlined text-primary text-xl">location_on</span>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ config('app.platform_state_badges.gyms_powered.title') }}</div>
                                <div class="text-xl font-headline font-bold text-on-surface">{{ config('app.platform_state_badges.gyms_powered.value') }}</div>
                            </div>
                        </div>
                    </div>
    
                    <div class="absolute top-1/2 -translate-y-1/2 -left-12 glass-card px-5 py-3 rounded-2xl shadow-xl hidden lg:block transition-all group-hover:translate-x-[-10px]">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-tertiary animate-pulse shadow-[0_0_8px_rgba(197,255,201,0.8)]"></div>
                            <div class="text-sm font-bold tracking-tight text-on-surface">{{ config('app.platform_state_badges.retention_rate.value') }} {{ config('app.platform_state_badges.retention_rate.title') }}</div>
                        </div>
                    </div>
                </div>
    
                <!-- Tagline Overlay -->
                <div class="mt-24 space-y-4 relative">
                    <div class="h-[2px] w-20 bg-tertiary mb-6"></div>
                    <h2 class="font-headline font-extrabold text-6xl lg:text-7xl leading-[0.9] tracking-tighter text-on-surface drop-shadow-2xl">
                        CONTINUOUS <br />
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-tertiary">FITNESS</span> <br />
                        EVOLUTION
                    </h2>
                </div>
            </div>
    
            <!-- Bottom Gradient Fade -->
            <div class="absolute bottom-0 left-0 w-full h-1/3 bg-gradient-to-t from-surface-container-lowest to-transparent z-20"></div>
        </section>
    </div>
</div>
