<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.blank')] class extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <style>
        .kinetic-pulse {
            background: radial-gradient(circle at center, rgba(197, 255, 201, 0.08) 0%, transparent 70%);
        }
        .atmosphere-glow {
            background: radial-gradient(circle at center, rgba(31, 43, 72, 1) 0%, rgba(6, 14, 32, 1) 80%);
        }

        @keyframes pulse-slow {
            0%, 100% { opacity: 1; transform: translateY(0); }
            50% { opacity: 0.9; transform: translateY(-5px); }
        }
        .animate-pulse-slow {
            animation: pulse-slow 4s ease-in-out infinite;
        }
    </style>

    <main class="min-h-screen flex flex-col md:flex-row overflow-hidden">
        <!-- LEFT PANEL (45%) -->
        <section class="w-full md:w-[45%] bg-surface flex flex-col relative p-8 md:p-12 min-h-screen z-10">
            <!-- Header/Navigation -->
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

            <!-- Content Area -->
            <div class="flex-grow flex flex-col justify-center max-w-md mx-auto w-full">
                <!-- Lock Icon with Glow -->
                <div class="mb-8 relative inline-flex ">
                    {{-- <div class="absolute inset-0 bg-tertiary/20 blur-2xl rounded-full"></div> --}}
                    <div class="relative bg-surface-container-high p-4 w-14 h-14 rounded-2xl border border-outline-variant/50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-3xl text-tertiary" data-icon="lock_reset" style="font-variation-settings: 'FILL' 1;">enhanced_encryption</span>
                    </div>
                </div>

                <h1 class="font-space-grotesk text-4xl md:text-5xl font-bold text-on-surface mb-4 tracking-tight leading-none">
                    {{ __('Reset Password?') }}
                </h1>
                <p class="text-on-surface-variant text-md mb-10 max-w-sm">
                    {{ __('Enter your new password below to regain access to your account.') }}
                </p>

                <!-- Reset Form -->
                <form wire:submit="resetPassword" class="space-y-6">
                    <!-- Email Field -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant ml-1">{{ __('EMAIL ADDRESS') }}</label>
                        <div class="relative group">
                            <input wire:model="email" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-4 text-on-surface focus:outline-none focus:ring-2 focus:ring-tertiary/50 focus:border-tertiary transition-all placeholder:text-outline/50 @error('email') border-error focus:ring-error @enderror" type="email" autocomplete="email" />
                            <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant group-focus-within:text-tertiary transition-colors">mail</span>
                        </div>
                        @error('email')
                            <p class="text-xs text-error mt-1 ml-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant ml-1">{{ __('NEW PASSWORD') }}</label>
                        <div class="relative group">
                            <input wire:model="password" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-4 text-on-surface focus:outline-none focus:ring-2 focus:ring-tertiary/50 focus:border-tertiary transition-all placeholder:text-outline/50 @error('password') border-error focus:ring-error @enderror" placeholder="{{ __('Password') }}" type="password" required autocomplete="new-password" />
                            <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-tertiary transition-colors">lock</span>
                        </div>
                        @error('password')
                            <p class="text-xs text-error mt-1 ml-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant ml-1">{{ __('CONFIRM PASSWORD') }}</label>
                        <div class="relative group">
                            <input wire:model="password_confirmation" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-4 text-on-surface focus:outline-none focus:ring-2 focus:ring-tertiary/50 focus:border-tertiary transition-all placeholder:text-outline/50 @error('password_confirmation') border-error focus:ring-error @enderror" placeholder="{{ __('Confirm password') }}" type="password" required autocomplete="new-password" />
                            <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-tertiary transition-colors">lock</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button class="w-full flex items-center justify-center bg-tertiary hover:brightness-110 text-on-tertiary-fixed font-bold py-4 rounded-xl transition-all active:scale-[0.98] shadow-lg shadow-tertiary/10 gap-2 group disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove class="font-bold text-on-tertiary-fixed text-md uppercase tracking-widest group-hover:scale-105 transition-transform">{{ __('RESET PASSWORD') }}</span>
                        <span wire:loading.flex class="items-center gap-2 font-bold text-on-tertiary-fixed text-md uppercase tracking-widest">
                            <svg class="w-5 h-5 animate-spin text-on-tertiary-fixed" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('Processing...') }}
                        </span>
                    </button>

                    <div class="pt-2 text-center">
                        <p class="text-on-surface-variant font-body text-sm">
                            Remember your password? <a class="text-tertiary hover:underline font-semibold ml-1" href="{{ route('login') }}" wire:navigate>Log In</a>
                        </p>
                    </div>

                    <!-- Session Status -->
                    @if (session('status'))
                        <div class="mt-4 p-4 bg-surface-container-high/40 rounded-lg border border-outline-variant/20 flex items-center gap-3">
                            <span class="material-symbols-outlined text-tertiary">info</span>
                            <span class="text-sm font-medium">{{ session('status') }}</span>
                        </div>
                    @endif
                </form>
            </div>

            <!-- Footer Anchor -->
            <footer class="mt-auto pt-8">
                <p class="text-[10px] uppercase tracking-[0.1em] text-on-surface-variant/40 font-medium">
                    © {{ date('Y') }} FLUXFITNESS GLOBAL • SECURE AUTHENTICATION LAYER
                </p>
            </footer>
            <!-- Left Panel Pulse Glow -->
            <div class="absolute inset-0 kinetic-pulse pointer-events-none -z-10 opacity-30"></div>
        </section>

        <!-- RIGHT PANEL (55%) -->
        <section class="hidden md:flex flex-1 bg-surface-container-low relative overflow-hidden items-center justify-center">
            <!-- Atmosphere Radial Glow -->
            <div class="absolute inset-0 atmosphere-glow"></div>
            <!-- Graphic Elements Container -->
            <div class="relative w-full h-full max-w-2xl flex flex-col justify-center items-center px-12">
                <!-- Floating Stat Badge Top Right -->
                <div class="absolute top-10 right-0 glass-card p-4 rounded-xl shadow-2xl z-20 flex items-center gap-4 animate-pulse-slow">
                    <div class="w-10 h-10 rounded-full bg-tertiary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-tertiary text-xl">trending_up</span>
                    </div>
                    <div>
                        <p class="font-label text-xs text-on-surface-variant uppercase tracking-widest leading-none mb-1">Active Members</p>
                        <p class="font-space-grotesk text-lg font-bold text-on-surface leading-none">2,400+</p>
                    </div>
                </div>
                <!-- Floating Chart Card -->
                <div class="relative w-80 h-64 bg-surface-container-high rounded-2xl p-6 shadow-2xl z-10 mb-8 border border-outline-variant/10">
                    <div class="flex justify-between items-center mb-8">
                        <p class="text-[0.7rem] text-on-surface-variant uppercase tracking-[0.2em]">Growth Analytics</p>
                        <span class="w-2 h-2 rounded-full bg-tertiary shadow-[0_0_10px_rgba(197,255,201,0.6)]"></span>
                    </div>
                    <div class="flex items-end justify-between h-32 gap-3">
                        <div class="w-8 bg-on-secondary-fixed-variant rounded-t-sm h-[40%]"></div>
                        <div class="w-8 bg-on-secondary-fixed-variant rounded-t-sm h-[60%]"></div>
                        <div class="w-8 bg-on-secondary-fixed-variant rounded-t-sm h-[55%]"></div>
                        <div class="w-8 bg-tertiary rounded-t-sm h-[100%] shadow-[0_0_20px_rgba(197,255,201,0.3)]"></div>
                        <div class="w-8 bg-on-secondary-fixed-variant rounded-t-sm h-[75%]"></div>
                        <div class="w-8 bg-on-secondary-fixed-variant rounded-t-sm h-[65%]"></div>
                    </div>
                </div>
                <!-- Secondary Floating Badges -->
                <div class="flex gap-4 w-full justify-center">
                    <div class="glass-card px-4 py-3 rounded-lg flex items-center gap-2 border border-outline-variant/10">
                        <div class="w-2 h-2 rounded-full bg-tertiary"></div>
                        <p class="font-label text-xs text-on-surface uppercase tracking-widest">98% Attendance Rate</p>
                    </div>
                </div>
                <!-- Floating Stat Badge Bottom Left -->
                <div class="absolute bottom-[25%] left-0 glass-card p-4 rounded-xl shadow-2xl z-20 flex items-center gap-4" style="bottom: 25%">
                    <div class="w-10 h-10 rounded-lg bg-surface-container-highest flex items-center justify-center border border-outline-variant/20">
                        <span class="material-symbols-outlined text-tertiary text-xl">location_on</span>
                    </div>
                    <div>
                        <p class="font-label text-xs text-on-surface-variant uppercase tracking-widest leading-none mb-1">Gym Branches</p>
                        <p class="font-space-grotesk text-lg font-bold text-on-surface leading-none">40+</p>
                    </div>
                </div>
                <!-- Large Stacked Branding Text -->
                <div class="absolute bottom-16 left-12">
                    <div class="flex flex-col space-y-[-0.5rem]">
                        <span class="font-space-grotesk text-5xl font-extrabold text-on-surface tracking-tighter">CONTINUOUS</span>
                        <span class="font-space-grotesk text-6xl font-black italic text-tertiary tracking-tighter uppercase">FITNESS</span>
                        <span class="font-space-grotesk text-5xl font-extrabold text-on-secondary-fixed-variant tracking-tighter">EVOLUTION</span>
                    </div>
                </div>
            </div>
            <!-- Background Texture -->
            <div class="absolute inset-0 opacity-10 pointer-events-none">
                <svg height="100%" width="100%" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern height="40" id="grid" patternunits="userSpaceOnUse" width="40">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#40485c" stroke-width="0.5"></path>
                        </pattern>
                    </defs>
                    <rect fill="url(#grid)" height="100%" width="100%"></rect>
                </svg>
            </div>
        </section>
    </main>
</div>
