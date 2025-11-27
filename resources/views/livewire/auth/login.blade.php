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

        $this->redirectIntended(default: route('profile', absolute: false));
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
                            class="w-full pl-10 pr-4 py-3 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 @error('email') border-red-500 focus:ring-red-500 @enderror" />
                    </div>
                    @error('email')
                        <p class="text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Password -->
                <div class="space-y-3" x-data="{ showPassword: false }">
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
                        <input wire:model="password" x-bind:type="showPassword ? 'text' : 'password'" required autocomplete="current-password"
                            placeholder="{{ __('Password') }}"
                            class="w-full pl-10 pr-24 py-3 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 @error('password') border-red-500 focus:ring-red-500 @enderror" />

                        <!-- Password Visibility Toggle -->
                        <button type="button" x-on:click="showPassword = !showPassword"
                            class="absolute right-16 top-1/2 transform -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 rounded">
                            <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                            </svg>
                        </button>

                        @if (Route::has('password.request'))
                            <div class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                <flux:link
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors whitespace-nowrap"
                                    :href="route('password.request')" wire:navigate>
                                    {{ __('Forgot?') }}
                                </flux:link>
                            </div>
                        @endif
                    </div>
                    @error('password')
                        <p class="text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <flux:checkbox wire:model="remember" :label="__('Remember me')"
                        class="text-slate-700 dark:text-slate-300 text-sm" />
                </div>

                <!-- Login Button -->
                <button type="submit" wire:loading.attr="disabled"
                    class="group w-full py-3 px-4 bg-gradient-to-r from-blue-600 via-purple-600 to-blue-700 hover:from-blue-700 hover:via-purple-700 hover:to-blue-800 disabled:from-slate-500 disabled:via-slate-500 disabled:to-slate-500 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-[1.02] disabled:hover:scale-100 hover:shadow-lg shadow-md focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-800 disabled:opacity-75">
                    <span class="flex items-center justify-center gap-2">
                        <!-- Loading Text -->
                        <span wire:loading wire:target="login" class="hidden">{{ __('Logging in...') }}</span>
                        <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>

                        <!-- Loading Spinner -->
                        <svg wire:loading wire:target="login" class="hidden w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>

                        <!-- Arrow Icon (hidden during loading) -->
                        <svg wire:loading.remove wire:target="login" class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </span>
                </button>

                <!-- General Error Messages -->
                @if($errors->any() && !$errors->has(['email', 'password']))
                    <div class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-red-700 dark:text-red-300 font-medium">Authentication Error</p>
                        </div>
                        <ul class="mt-2 text-sm text-red-600 dark:text-red-400 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>• {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
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
