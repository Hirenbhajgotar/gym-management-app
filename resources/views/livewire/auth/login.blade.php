<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
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

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('profile', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
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

<!-- Background with subtle gradient -->
<div
    class="min-h-screen- bg-gradient-to-br from-slate-50 via-white to-slate-100 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900 flex items-center justify-center p-8">
    <!-- Main login card -->
    <div class="w-full max-w-xl">
        <!-- Card with glassmorphism effect -->
        <div
            class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/20 dark:border-slate-700/50 p-8 relative overflow-hidden">
            <!-- Decorative elements -->
            <div
                class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-blue-400/20 to-purple-500/20 rounded-full blur-xl">
            </div>
            <div
                class="absolute -bottom-10 -left-10 w-24 h-24 bg-gradient-to-tr from-emerald-400/20 to-cyan-500/20 rounded-full blur-xl">
            </div>

            <!-- Header section -->
            <div class="relative z-10 text-center mb-8">
                <!-- Icon -->
                <div
                    class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl mb-4 shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>

                <h1
                    class="text-3xl font-bold bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 dark:from-white dark:via-slate-100 dark:to-white bg-clip-text text-transparent mb-2">
                    {{ __('Welcome Back') }}
                </h1>
                <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                    {{ __('Enter your credentials to access your gym dashboard') }}
                </p>
            </div>

            <!-- Session Status -->
            <x-auth-session-status class="relative z-10 text-center mb-6" :status="session('status')" />

            <!-- Login form -->
            <form wire:submit="login" class="relative z-10 flex flex-col gap-6">
                <!-- Email Address -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Email address') }}
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207">
                                </path>
                            </svg>
                        </div>
                        <input wire:model="email" type="email" required autofocus autocomplete="email"
                            placeholder="email@example.com"
                            class="w-full pl-10 pr-4 py-3 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400" />
                    </div>
                </div>

                <!-- Password -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Password') }}
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                </path>
                            </svg>
                        </div>
                        <input wire:model="password" type="password" required autocomplete="current-password"
                            placeholder="{{ __('Password') }}"
                            class="w-full pl-10 pr-16 py-3 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400" />

                        @if (Route::has('password.request'))
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <flux:link
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors"
                                    :href="route('password.request')" wire:navigate>
                                    {{ __('Forgot?') }}
                                </flux:link>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <flux:checkbox wire:model="remember" :label="__('Remember me')"
                        class="text-slate-700 dark:text-slate-300 text-sm" />
                </div>

                <!-- Login Button -->
                <button type="submit"
                    class="group w-full py-3 px-4 bg-gradient-to-r from-blue-600 via-purple-600 to-blue-700 hover:from-blue-700 hover:via-purple-700 hover:to-blue-800 text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg shadow-md focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-800">
                    <span class="flex items-center justify-center gap-2">
                        {{ __('Log in') }}
                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1 group-hover:scale-110"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </span>
                </button>
            </form>

            @if (Route::has('register'))
                <div class="relative z-10 text-center">
                    <p class="text-slate-600 dark:text-slate-400 text-sm">
                        {{ __('Don\'t have an account?') }}
                        <flux:link :href="route('register')" wire:navigate
                            class="font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                            {{ __('Sign up') }}
                        </flux:link>
                    </p>
                </div>
            @endif
        </div>

        <!-- Footer text -->
        <p class="text-center text-xs text-slate-500 dark:text-slate-400 mt-6">
            Secure login powered by Laravel
        </p>
    </div>
</div>
