<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'shake': 'shake 0.5s cubic-bezier(.36,.07,.19,.97) both',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        shake: {
                            '10%, 90%': {
                                transform: 'translate3d(-1px, 0, 0)'
                            },
                            '20%, 80%': {
                                transform: 'translate3d(2px, 0, 0)'
                            },
                            '30%, 50%, 70%': {
                                transform: 'translate3d(-4px, 0, 0)'
                            },
                            '40%, 60%': {
                                transform: 'translate3d(4px, 0, 0)'
                            }
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gradient-to-br from-slate-50 via-red-50 to-orange-50 min-h-screen flex items-center justify-center p-4">
    <!-- Background decorations -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-red-400/10 rounded-full blur-3xl animate-pulse-slow"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-orange-400/10 rounded-full blur-3xl animate-pulse-slow"
            style="animation-delay: 1s;"></div>
    </div>

    <div class="relative max-w-2xl w-full">
        <!-- Main Card -->
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-red-600 to-orange-600 px-8 py-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white">{{ config('app.name') }}</h1>
                            <p class="text-red-100 text-xs">Gym Management Platform</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Error Icon -->
                <div class="flex justify-center mb-6">
                    <div class="relative">
                        <div
                            class="w-24 h-24 bg-gradient-to-br from-red-100 to-orange-100 rounded-full flex items-center justify-center animate-shake">
                            <div
                                class="w-20 h-20 bg-gradient-to-br from-red-500 to-orange-500 rounded-full flex items-center justify-center">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                        </div>
                        <!-- Animated circles -->
                        <div class="absolute inset-0 rounded-full border-4 border-red-300 animate-ping opacity-20">
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Payment Failed</h2>
                    <p class="text-gray-600">Your payment could not be processed at this time</p>
                </div>

                <!-- Gym Information -->
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-4 mb-6 border border-slate-200">
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-red-500 to-orange-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Payment attempted for
                            </p>
                            <p class="text-base font-bold text-slate-900 truncate">{{ $tenant->name }}</p>
                        </div>
                    </div>
                </div>

                @if ($payment)
                    <!-- Payment Details -->
                    <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-xl p-6 mb-6 border border-red-100">
                        <h3 class="text-base font-bold text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Transaction Details
                        </h3>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between pb-3 border-b border-red-200">
                                <span class="text-sm text-gray-600 font-medium">Order ID</span>
                                <span
                                    class="text-sm font-mono font-semibold text-gray-900 bg-white px-3 py-1 rounded">{{ $payment->razorpay_order_id }}</span>
                            </div>

                            <div class="flex items-center justify-between pb-3 border-b border-red-200">
                                <span class="text-sm text-gray-600 font-medium">Amount</span>
                                <span
                                    class="text-lg font-bold text-gray-900">₹{{ number_format($payment->amount, 2) }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600 font-medium">Status</span>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 capitalize">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                    {{ $payment->status->getLabel() }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Common Reasons -->
                <div class="bg-blue-50 rounded-xl p-5 mb-6 border border-blue-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        Common Reasons for Payment Failure
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start">
                            <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>Insufficient balance or credit limit exceeded</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>Incorrect card details or expired card</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>Payment declined by your bank</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>Network connectivity issues</span>
                        </li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3 mb-6">
                    <a href="{{ route('filament.admin.pages.settings', ['tenant' => $tenant->slug, 'tab' => 'whatsapp']) }}"
                        class="group relative w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl overflow-hidden flex items-center justify-center">
                        <span
                            class="absolute inset-0 w-full h-full bg-gradient-to-r from-white/0 via-white/20 to-white/0 transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></span>
                        <span class="relative flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Try Again</span>
                        </span>
                    </a>

                    <a href="{{ route('filament.admin.pages.dashboard', ['tenant' => $tenant->slug]) }}"
                        class="w-full bg-white hover:bg-gray-50 text-gray-700 font-semibold py-3 px-6 rounded-xl border-2 border-gray-200 transition-all duration-300 hover:border-gray-300 flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Go to Dashboard</span>
                    </a>
                </div>

                <!-- Support Section -->
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-5 border border-purple-100">
                    <div class="flex items-start space-x-3">
                        <div
                            class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 mb-1">Need Help?</h4>
                            <p class="text-sm text-gray-600 mb-3">Our support team is here to assist you with payment
                                issues</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="mailto:{{ env('SUPPORT_MAIL') }}"
                                    class="inline-flex items-center space-x-1 text-xs font-semibold text-purple-700 hover:text-purple-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span>Email Support</span>
                                </a>
                                <span class="text-gray-300">•</span>
                                <a href="tel:+91{{ env('SUPPORT_CONTACT') }}"
                                    class="inline-flex items-center space-x-1 text-xs font-semibold text-purple-700 hover:text-purple-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    <span>Call Us</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Note -->
        <div class="mt-6 text-center">
            <div
                class="inline-flex items-center space-x-2 bg-white/60 backdrop-blur-sm rounded-full px-4 py-2 border border-gray-200">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span class="text-xs text-gray-600 font-medium">Your payment information is secure and encrypted</span>
            </div>
        </div>
    </div>
</body>

</html>
