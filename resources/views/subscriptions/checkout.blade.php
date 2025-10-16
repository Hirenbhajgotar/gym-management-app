<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        {{ $checkoutType === 'renewal' ? 'Renew Subscription' : ($checkoutType === 'upgrade' ? 'Upgrade Plan' : 'New Subscription') }}
        - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <style>
        :root {
            --font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 25%, #2d1b4e 50%, #1a1a3e 75%, #0f0f23 100%);
        }

        .feature-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .checkout-card {
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .plan-comparison {
            position: relative;
        }

        .plan-comparison::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            background: linear-gradient(45deg, #7c3aed, #ec4899);
            border-radius: 50%;
            z-index: 1;
        }

        .upgrade-arrow {
            animation: bounce 2s infinite;
        }

        @keyframes bounce {

            0%,
            20%,
            50%,
            80%,
            100% {
                transform: translateX(0);
            }

            40% {
                transform: translateX(5px);
            }

            60% {
                transform: translateX(-5px);
            }
        }

        .security-badge {
            transition: all 0.3s ease;
        }

        .security-badge:hover {
            transform: scale(1.05);
        }

        .price-highlight {
            background: linear-gradient(45deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                filter: brightness(1);
            }

            to {
                filter: brightness(1.2);
            }
        }
    </style>
</head>

<body class="text-white min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="container mx-auto max-w-4xl">
            <div class="grid lg:grid-cols-3 gap-8">

                <!-- Order Summary Sidebar -->
                <div class="lg:col-span-1 order-2 lg:order-1">
                    <div class="feature-card rounded-2xl p-6 sticky top-6 checkout-card">
                        <h3 class="text-xl font-bold mb-6 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Order Summary
                        </h3>

                        @if ($checkoutType === 'renewal' && isset($subscription))
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-300">{{ $plan->name }}</span>
                                    <span class="font-semibold">{{ $subscription->currency }}
                                        {{ number_format($subscription->price, 2) }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm text-gray-400">
                                    <span>{{ ucfirst($subscription->billing_cycle) }} renewal</span>
                                    <span>{{ $subscription->billing_cycle === 'yearly' ? 'per year' : 'per month' }}</span>
                                </div>
                                <div class="border-t border-white/10 pt-4">
                                    <div class="flex items-center justify-between text-lg font-bold">
                                        <span>Total</span>
                                        <span class="price-highlight">{{ $subscription->currency }}
                                            {{ number_format($subscription->price, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        @elseif($checkoutType === 'upgrade' && isset($subscription))
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-300 line-through">{{ $subscription->plan->name }}</span>
                                    <span class="text-gray-500 line-through">{{ $subscription->currency }}
                                        {{ number_format($subscription->plan->price_yearly, 2) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-green-400">{{ $plan->name }}</span>
                                    <span class="font-semibold text-green-400">{{ $subscription->currency }}
                                        {{ number_format($amount / 100, 2) }}</span>
                                </div>
                                <div class="border-t border-white/10 pt-4">
                                    <div class="flex items-center justify-between text-lg font-bold">
                                        <span>Total</span>
                                        <span class="price-highlight">{{ $subscription->currency }}
                                            {{ number_format($amount / 100, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        @elseif($checkoutType === 'new')
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-300">{{ $plan->name }}</span>
                                    @if (isset($trialDays) && $trialDays > 0 && $amount == 0)
                                        <span class="font-semibold text-green-400">Free</span>
                                    @else
                                        <span class="font-semibold">{{ $plan->currency }}
                                            {{ number_format($amount / 100, 2) }}</span>
                                    @endif
                                </div>
                                @if (isset($trialDays) && $trialDays > 0)
                                    <div class="flex items-center justify-between text-sm text-green-400">
                                        <span>{{ $trialDays }} days free trial</span>
                                        <span class="text-xs">then {{ $plan->currency }}
                                            {{ number_format($plan->price_yearly, 2) }}/year</span>
                                    </div>
                                @endif
                                <div class="border-t border-white/10 pt-4">
                                    <div class="flex items-center justify-between text-lg font-bold">
                                        <span>Total</span>
                                        @if (isset($trialDays) && $trialDays > 0 && $amount == 0)
                                            <span class="price-highlight">Free</span>
                                        @else
                                            <span class="price-highlight">{{ $plan->currency }}
                                                {{ number_format($amount / 100, 2) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Security Badges -->
                        <div class="mt-6 pt-6 border-t border-white/10">
                            <div class="flex items-center justify-center space-x-6 text-xs text-gray-400">
                                <div class="flex items-center security-badge">
                                    <svg class="w-4 h-4 mr-1 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    SSL Secured
                                </div>
                                <div class="flex items-center security-badge">
                                    <svg class="w-4 h-4 mr-1 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" />
                                    </svg>
                                    PCI Compliant
                                </div>
                                <div class="flex items-center security-badge">
                                    <svg class="w-4 h-4 mr-1 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    256-bit Encryption
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Checkout Content -->
                <div class="lg:col-span-2 order-1 lg:order-2">
                    <div class="feature-card rounded-2xl p-8 checkout-card">

                        <!-- Header Section -->
                        <div class="text-center mb-8">
                            @if ($checkoutType === 'renewal')
                                <div
                                    class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <h1 class="text-3xl font-bold mb-2">Renew Your Subscription</h1>
                                <p class="text-gray-300">Keep your gym management tools running smoothly</p>
                            @elseif($checkoutType === 'upgrade')
                                <div
                                    class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-green-500 to-emerald-500 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                </div>
                                <h1 class="text-3xl font-bold mb-2">Upgrade Your Plan</h1>
                                <p class="text-gray-300">Unlock more features and grow your gym business</p>
                            @elseif($checkoutType === 'new')
                                <div
                                    class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                </div>
                                <h1 class="text-3xl font-bold mb-2">Start Your Journey</h1>
                                <p class="text-gray-300">Get started with GymFlow and transform your gym management</p>
                            @endif
                        </div>

                        <!-- Plan Details Card -->
                        <div
                            class="bg-gradient-to-r from-white/5 to-white/10 rounded-xl p-6 mb-8 border border-white/10">
                            @if ($checkoutType === 'renewal')
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="text-xl font-semibold text-white">{{ $plan->name }}</h3>
                                        <p class="text-gray-300 text-sm">{{ $subscription->billing_cycle }}
                                            subscription
                                            renewal</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-green-400">{{ $subscription->currency }}
                                            {{ number_format($subscription->price, 2) }}</div>
                                        <div class="text-xs text-gray-400">
                                            {{ $subscription->billing_cycle === 'yearly' ? 'per year' : 'per month' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center text-sm text-gray-300">
                                    <svg class="w-4 h-4 mr-2 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Next billing date: {{ $subscription->current_period_end?->format('M d, Y') }}
                                </div>
                            @elseif($checkoutType === 'upgrade')
                                <div class="mb-4">
                                    <div class="plan-comparison flex items-center justify-between mb-4 relative">
                                        <div class="text-center flex-1">
                                            <div class="text-sm text-gray-400 mb-1">Current Plan</div>
                                            <div class="font-semibold">{{ $subscription->plan->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $subscription->currency }}
                                                {{ number_format($subscription->plan->price_yearly, 2) }}/year</div>
                                        </div>
                                        <div class="flex items-center px-4 upgrade-arrow">
                                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                            </svg>
                                        </div>
                                        <div class="text-center flex-1">
                                            <div class="text-sm text-green-400 mb-1">New Plan</div>
                                            <div class="font-semibold text-green-400">{{ $plan->name }}</div>
                                            <div class="text-sm text-green-300 price-highlight">
                                                {{ $subscription->currency }} {{ number_format($amount / 100, 2) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @elseif($checkoutType === 'new')
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="text-xl font-semibold text-white">{{ $plan->name }}</h3>
                                        <p class="text-gray-300 text-sm">New subscription</p>
                                    </div>
                                    <div class="text-right">
                                        @if (isset($trialDays) && $trialDays > 0 && $amount == 0)
                                            <div class="text-2xl font-bold text-green-400">Free Trial</div>
                                            <div class="text-xs text-gray-400">{{ $trialDays }} days free, then
                                                {{ $plan->currency }} {{ number_format($plan->price_yearly, 2) }}/year
                                            </div>
                                        @else
                                            <div class="text-2xl font-bold text-green-400">{{ $plan->currency }}
                                                {{ number_format($amount / 100, 2) }}</div>
                                            <div class="text-xs text-gray-400">per year</div>
                                        @endif
                                    </div>
                                </div>
                                @if (isset($trialDays) && $trialDays > 0)
                                    <div
                                        class="flex items-center text-sm text-green-400 bg-green-500/10 rounded-lg p-3">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <span>{{ $trialDays }} days free trial included - no payment required
                                            today!</span>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <!-- Payment Section -->
                        <div class="text-center">
                            <h3 class="text-lg font-semibold mb-4">Complete Your Payment</h3>
                            <p class="text-gray-300 text-sm mb-6">Secure payment powered by Razorpay</p>

                            <!-- Razorpay Checkout Button -->
                            <form action="{{ route('subscriptions.verify-payment') }}" method="POST"
                                id="subscription-form">
                                @csrf
                                <script src="https://checkout.razorpay.com/v1/checkout.js" data-key="{{ $key }}"
                                    data-amount="{{ $amount }}" data-currency="INR" data-order_id="{{ $orderId }}" data-name="GymFlow"
                                    data-description="{{ $checkoutType === 'renewal' ? 'Subscription Renewal' : ($checkoutType === 'upgrade' ? 'Subscription Upgrade' : 'New Subscription') }}"
                                    data-prefill.name="{{ auth()->user()->name }}" data-prefill.email="{{ auth()->user()->email }}"
                                    data-theme.color="#7c3aed"></script>
                            </form>
                        </div>

                        <!-- Back Link -->
                        <div class="mt-8 text-center">
                            <a href="{{ route('profile') }}"
                                class="inline-flex items-center text-gray-400 hover:text-white transition-colors text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-submit form when Razorpay payment is successful
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Razorpay is loaded
            if (typeof Razorpay !== 'undefined') {
                const options = {
                    key: "{{ $key }}",
                    amount: "{{ $amount }}",
                    currency: "INR",
                    order_id: "{{ $orderId }}",
                    name: "GymFlow",
                    description: "{{ $checkoutType === 'renewal' ? 'Subscription Renewal' : ($checkoutType === 'upgrade' ? 'Subscription Upgrade' : 'New Subscription') }}",
                    prefill: {
                        name: "{{ auth()->user()->name }}",
                        email: "{{ auth()->user()->email }}",
                    },
                    theme: {
                        color: "#7c3aed"
                    },
                    handler: function(response) {
                        // Show loading state
                        const submitBtn = document.querySelector('.razorpay-payment-button');
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            submitBtn.innerHTML =
                                '<span class="flex items-center"><svg class="w-4 h-4 animate-spin mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Processing...</span>';
                        }

                        // Create hidden form and submit
                        const form = document.getElementById('subscription-form');
                        const paymentIdInput = document.createElement('input');
                        paymentIdInput.type = 'hidden';
                        paymentIdInput.name = 'razorpay_payment_id';
                        paymentIdInput.value = response.razorpay_payment_id;

                        const orderIdInput = document.createElement('input');
                        orderIdInput.type = 'hidden';
                        orderIdInput.name = 'razorpay_order_id';
                        orderIdInput.value = response.razorpay_order_id;

                        const signatureInput = document.createElement('input');
                        signatureInput.type = 'hidden';
                        signatureInput.name = 'razorpay_signature';
                        signatureInput.value = response.razorpay_signature;

                        form.appendChild(paymentIdInput);
                        form.appendChild(orderIdInput);
                        form.appendChild(signatureInput);

                        form.submit();
                    },
                    modal: {
                        ondismiss: function() {
                            // Optional: Handle when user closes the payment modal
                            console.log('Payment modal dismissed');
                        }
                    }
                };

                const rzp = new Razorpay(options);

                // Auto-open the payment modal when page loads
                setTimeout(function() {
                    rzp.open();
                }, 500);
            } else {
                console.error('Razorpay SDK not loaded');
            }
        });
    </script>
</body>

</html>
