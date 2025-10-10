<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'bounce-gentle': 'bounce 2s infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'scale-in': 'scaleIn 0.5s ease-out',
                        'fade-in-up': 'fadeInUp 0.6s ease-out',
                    },
                    keyframes: {
                        scaleIn: {
                            '0%': {
                                transform: 'scale(0.8)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'scale(1)',
                                opacity: '1'
                            }
                        },
                        fadeInUp: {
                            '0%': {
                                transform: 'translateY(20px)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'translateY(0)',
                                opacity: '1'
                            }
                        }
                    }
                }
            }
        }
    </script>
</head>

<body
    class="bg-gradient-to-br from-emerald-50 via-green-50 to-teal-50 min-h-screen flex items-center justify-center p-4">
    <!-- Background decorations -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-emerald-400/10 rounded-full blur-3xl animate-pulse-slow">
        </div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-teal-400/10 rounded-full blur-3xl animate-pulse-slow"
            style="animation-delay: 1s;"></div>
    </div>

    <div class="relative max-w-2xl w-full">
        <!-- Main Card -->
        <div
            class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-100 overflow-hidden animate-scale-in">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-8 py-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white">{{ config('app.name') }}</h1>
                            <p class="text-emerald-100 text-xs">Gym Management Platform</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Success Icon -->
                <div class="flex justify-center mb-6">
                    <div class="relative">
                        <div
                            class="w-24 h-24 bg-gradient-to-br from-emerald-100 to-teal-100 rounded-full flex items-center justify-center animate-bounce-gentle">
                            <div
                                class="w-20 h-20 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-full flex items-center justify-center">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>
                        <!-- Animated circles -->
                        <div class="absolute inset-0 rounded-full border-4 border-emerald-300 animate-ping opacity-20">
                        </div>
                    </div>
                </div>

                <!-- Success Message -->
                <div class="text-center mb-6 animate-fade-in-up">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Payment Successful!</h2>
                    <p class="text-gray-600">Your WhatsApp message package has been purchased successfully</p>
                </div>

                <!-- Gym Information -->
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-4 mb-6 border border-slate-200 animate-fade-in-up"
                    style="animation-delay: 0.2s;">
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Payment completed for
                            </p>
                            <p class="text-base font-bold text-slate-900 truncate">{{ $tenant->name }}</p>
                        </div>
                    </div>
                </div>

                @if ($payment && $payment->metadata)
                    <!-- Package Details -->
                    <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl p-6 mb-6 border border-emerald-100 animate-fade-in-up"
                        style="animation-delay: 0.3s;">
                        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                            <svg class="w-6 h-6 mr-3 text-emerald-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                            Messages Purchased Successfully
                        </h3>

                        <!-- Package Summary Card -->
                        <div class="bg-white/70 backdrop-blur-sm rounded-lg p-4 mb-4 border border-emerald-200">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-bold text-gray-900">
                                            {{ $payment->metadata['package_name'] ?? 'N/A' }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ $payment->metadata['message_count'] ?? 'N/A' }} WhatsApp Messages</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-emerald-600">
                                        ₹{{ number_format($payment->amount, 2) }}</div>
                                    <div class="text-xs text-gray-500">Paid Successfully</div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between pb-2 border-b border-emerald-200">
                                    <span class="text-sm text-gray-600 font-medium">Order ID</span>
                                    <span
                                        class="text-sm font-mono font-semibold text-gray-900 bg-white px-3 py-1 rounded">{{ $payment->razorpay_order_id }}</span>
                                </div>

                                <div class="flex items-center justify-between pb-2 border-b border-emerald-200">
                                    <span class="text-sm text-gray-600 font-medium">Package Type</span>
                                    <span
                                        class="text-sm font-semibold text-gray-900">{{ $payment->metadata['package_name'] ?? 'N/A' }}</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600 font-medium">Messages Included</span>
                                    <span
                                        class="text-sm font-semibold text-gray-900">{{ $payment->metadata['message_count'] ?? 'N/A' }}</span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between pb-2 border-b border-emerald-200">
                                    <span class="text-sm text-gray-600 font-medium">Amount Paid</span>
                                    <span
                                        class="text-lg font-bold text-gray-900">₹{{ number_format($payment->amount, 2) }}</span>
                                </div>

                                <div class="flex items-center justify-between pb-2 border-b border-emerald-200">
                                    <span class="text-sm text-gray-600 font-medium">Payment Date</span>
                                    <span
                                        class="text-sm font-semibold text-gray-900">{{ $payment->created_at->format('M d, Y') }}</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600 font-medium">Status</span>
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 capitalize">
                                        <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse"></span>
                                        {{ $payment->status->getLabel() }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Success Message -->
                        <div class="mt-4 p-3 bg-emerald-100/50 rounded-lg border border-emerald-200">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm font-medium text-emerald-800">Package activated successfully! Your
                                    WhatsApp messaging quota has been updated.</span>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="space-y-3 mb-6 animate-fade-in-up" style="animation-delay: 0.4s;">
                    <a href="{{ route('filament.admin.pages.settings', ['tenant' => $tenant->slug, 'tab' => 'whatsapp']) }}"
                        class="group relative w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl overflow-hidden flex items-center justify-center">
                        <span
                            class="absolute inset-0 w-full h-full bg-gradient-to-r from-white/0 via-white/20 to-white/0 transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></span>
                        <span class="relative flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>Manage WhatsApp Settings</span>
                        </span>
                    </a>

                    <a href="{{ route('filament.admin.pages.dashboard', ['tenant' => $tenant->slug]) }}"
                        class="w-full bg-white hover:bg-gray-50 text-gray-700 font-semibold py-3 px-6 rounded-xl border-2 border-gray-200 transition-all duration-300 hover:border-gray-300 flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                            </path>
                        </svg>
                        <span>Go to Dashboard</span>
                    </a>
                </div>

                <!-- Support Section -->
                <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-xl p-5 border border-emerald-100 animate-fade-in-up"
                    style="animation-delay: 0.5s;">
                    <div class="flex items-start space-x-3">
                        <div
                            class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 mb-1">Need Help?</h4>
                            <p class="text-sm text-gray-600 mb-3">Our support team is here to assist you with any
                                questions</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="mailto:{{ env('SUPPORT_MAIL') }}"
                                    class="inline-flex items-center space-x-1 text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    <span>Email Support</span>
                                </a>
                                <span class="text-gray-300">•</span>
                                <a href="tel:+91{{ env('SUPPORT_CONTACT') }}"
                                    class="inline-flex items-center space-x-1 text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                        </path>
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
        <div class="mt-6 text-center animate-fade-in-up" style="animation-delay: 0.6s;">
            <div
                class="inline-flex items-center space-x-2 bg-white/60 backdrop-blur-sm rounded-full px-4 py-2 border border-gray-200">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                    </path>
                </svg>
                <span class="text-xs text-gray-600 font-medium">Your payment information is secure and encrypted</span>
            </div>
        </div>
    </div>
</body>

</html>
