<div>
    <section class="min-h-screen py-16 relative overflow-hidden bg-surface text-on-surface font-body selection:bg-primary selection:text-on-primary antialiased pt-24">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-20 right-10 w-40 h-40 bg-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-40 left-10 w-48 h-48 bg-cyan-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgb(148 163 184 / 0.08) 1px, transparent 0); background-size: 40px 40px;"></div>
        </div>

        <div class="container mx-auto px-6 relative z-10">
            <div class="max-w-lg mx-auto">
                <!-- Header -->
                <div class="text-center mb-8 animate-slide-up">
                    <div class="flex items-center justify-center space-x-2 text-sm text-gray-400 mb-4">
                        <a href="{{ route('home') }}" wire:navigate class="hover:text-cyan-400 transition-colors">Home</a>
                        <span>/</span>
                        <span class="text-gray-300">Check In</span>
                    </div>

                    <h1 class="text-3xl lg:text-4xl font-bold mb-4">
                        <span class="bg-primary bg-clip-text text-transparent">
                            {{ $gym->name ?? 'Gym' }}
                        </span>
                    </h1>
                    <p class="text-gray-300">Enter your phone number to check in securely</p>
                </div>

                <!-- Check-in Form Card -->
                <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl border border-gray-800 p-8 animate-slide-up" style="animation-delay: 0.1s;">
                    @if($showResult)
                        <!-- Result Display -->
                        <div class="text-center">
                            @if(isset($result['success']) && $result['success'])
                                <div class="mb-6">
                                    @if(isset($result['already_checked_in']) && $result['already_checked_in'])
                                        <div class="w-20 h-20 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <svg class="w-10 h-10 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-2xl font-bold text-yellow-400 mb-2">Already Checked In</h3>
                                    @elseif(isset($result['already_checked_out']) && $result['already_checked_out'])
                                        <div class="w-20 h-20 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <svg class="w-10 h-10 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-2xl font-bold text-yellow-400 mb-2">Already Checked Out</h3>
                                    @else
                                        <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-2xl font-bold text-green-400 mb-2">{{ $isCheckout ? 'Check-Out' : 'Check-In' }} Successful!</h3>
                                    @endif
                                </div>

                                <p class="text-gray-300 mb-6">{{ $result['message'] ?? 'Check-in successful' }}</p>

                                @if(isset($result['attendance']) && $result['attendance'])
                                    <div class="bg-gray-800/50 rounded-lg p-4 mb-6 text-left">
                                        @if ($isCheckout)
                                            <p class="text-sm text-gray-400">Check-out Time</p>
                                            <p class="text-lg font-semibold text-white">{{ $result['attendance']->check_out_time->format('F j, Y g:i A') }}</p>
                                        @else
                                            <p class="text-sm text-gray-400">Check-in Time</p>
                                            <p class="text-lg font-semibold text-white">{{ $result['attendance']->check_in_time->format('F j, Y g:i A') }}</p>
                                        @endif
                                    </div>
                                @endif
                            @else
                                <div class="mb-6">
                                    <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-2xl font-bold text-red-400 mb-2">{{ $isCheckout ? 'Check-Out' : 'Check-In' }} Failed</h3>
                                </div>

                                <p class="text-gray-300 mb-6">{{ $result['message'] ?? 'Something went wrong. Please try again.' }}</p>
                            @endif

                            <button
                                wire:click="resetForm"
                                class="inline-flex items-center justify-center px-6 py-3 bg-primary-container text-on-primary-fixed font-semibold shadow-primary/10 active:scale-[0.98] transition-all hover:brightness-110 uppercase tracking-wide disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer rounded-xl shadow-xl"
                            >
                                <span wire:loading.remove wire:target="resetForm" class="flex items-center gap-2">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Try Again
                                </span>
                                <x-loading-icon target="resetForm" variant="text" name="Redirecting" />
                            </button>
                        </div>
                    @elseif($otpSent && !$showResult)
                        <!-- OTP Verification Form -->
                        <form wire:submit.prevent="verifyOtp" class="space-y-6">
                            <div class="text-center mb-4">
                                <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-400">Verification code sent to</p>
                                <p class="font-medium text-white">{{ $phoneNumber }}</p>
                            </div>

                            <!-- OTP Input -->
                            <div>
                                <label for="otp" class="block text-sm font-medium text-gray-300 mb-2">
                                    Enter 6-digit code
                                </label>
                                <input
                                    type="text"
                                    id="otp"
                                    wire:model="otp"
                                    placeholder="Enter verification code"
                                    maxlength="6"
                                    class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-300 text-center text-2xl tracking-wided"
                                    autofocus
                                >
                                @error('otp')
                                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <p class="text-xs text-gray-500 text-center">
                                Code expires in 5 minutes. Check your WhatsApp for the code.
                            </p>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                class="cursor-pointer w-full flex items-center justify-center gap-2 bg-primary-container text-on-primary-fixed font-headline font-bold text-lg py-4 rounded-xl shadow-xl shadow-primary/10 active:scale-[0.98] transition-all hover:brightness-110 uppercase tracking-wide disabled:opacity-75 disabled:cursor-not-allowed"
                            >
                                <span wire:loading.remove wire:target="verifyOtp" class="flex items-center gap-2">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Verify & Check In
                                </span>
                                <x-loading-icon target="verifyOtp" variant="text" name="Verifying" />
                            </button>

                            <!-- Resend Button -->
                            <button
                                type="button"
                                wire:click="resetForm"
                                class="cursor-pointer text-sm text-gray-500 hover:text-gray-400 transition-colors"
                            >
                                ← Use different phone number
                            </button>
                        </form>
                    @else
                        <!-- Phone Number Form (Initial) -->
                        <form wire:submit.prevent="sendOtp" class="space-y-6">
                            <!-- Phone Input -->
                            <div>
                                <label for="phoneNumber" class="block text-sm font-medium text-gray-300 mb-2">
                                    Registered Phone Number
                                </label>
                                <input
                                    type="tel"
                                    id="phoneNumber"
                                    wire:model="phoneNumber"
                                    placeholder="+911234567897"
                                    class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-300"
                                    autofocus
                                >
                                @error('phoneNumber')
                                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            @if($useOtpFlow)
                            <p class="text-xs text-gray-500">
                                We'll send a verification code to your registered WhatsApp number.
                            </p>
                            @endif

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                class="cursor-pointer w-full flex items-center justify-center gap-2 bg-primary-container text-on-primary-fixed font-headline font-bold text-lg py-4 rounded-xl shadow-xl shadow-primary/10 active:scale-[0.98] transition-all hover:brightness-110 uppercase tracking-wide disabled:opacity-75 disabled:cursor-not-allowed"
                            >
                                <span wire:loading.remove wire:target="sendOtp">
                                    @if($useOtpFlow)
                                        Send Code
                                    @elseif($isCheckout)
                                        Check Out
                                    @else
                                        Check In
                                    @endif
                                </span>
                                <x-loading-icon target="sendOtp" variant="text" name="Loading" />
                            </button>
                        </form>
                    @endif
                </div>

                <!-- Help Text -->
                <p class="text-center text-gray-500 text-sm mt-6">
                    Having trouble? Please contact the gym front desk for assistance.
                </p>
            </div>
        </div>
    </section>
</div>