@extends('layouts.checkout')

@section('title', (isset($isPendingRenewal) && $isPendingRenewal ? 'Complete Renewal' : ($checkoutType === 'renewal' ? 'Renew Subscription' : ($checkoutType === 'upgrade' ? 'Upgrade Plan' :
    'New Subscription'))) . ' - ' . config('app.name'))

@section('success-message', isset($isPendingRenewal) && $isPendingRenewal ? 'Your subscription renewal has been completed successfully' : ('Your subscription has been ' . ($checkoutType === 'renewal' ? 'renewed' : ($checkoutType
    === 'upgrade' ? 'upgraded' : 'activated')) . ' successfully'))

@section('redirect-message', 'Redirecting to dashboard...')

@section('content')
    <div class="relative max-w-2xl w-full">
        <!-- Main Card -->
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
            <!-- Header Section with Branding -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white"><a
                                    href="{{ route('home') }}">{{ config('app.name') }}</a></h1>
                            <p class="text-blue-100 text-xs">Gym Management Platform</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 bg-white/20 backdrop-blur-sm px-3 py-1.5 rounded-full">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-white text-xs font-medium">Secure</span>
                    </div>
                </div>
                <p class="text-blue-50 text-sm">
                    @if ($checkoutType === 'renewal')
                        Renew your subscription to continue enjoying premium features
                    @elseif($checkoutType === 'upgrade')
                        Upgrade to unlock more features and grow your gym business
                    @else
                        Start your subscription and transform your gym management
                    @endif
                </p>
            </div>

            <div class="p-8">
                <!-- Subscription Information -->
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-4 mb-6 border border-slate-200">
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">
                                @if ($checkoutType === 'renewal')
                                    Renewing
                                @elseif($checkoutType === 'upgrade')
                                    Upgrading to
                                @else
                                    Subscribing to
                                @endif
                            </p>
                            <p class="text-base font-bold text-slate-900 truncate">{{ $plan->name }}</p>
                        </div>
                    </div>
                </div>

                <!-- Plan Details Card -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 mb-6 border border-blue-100">
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-900">
                            @if ($checkoutType === 'renewal')
                                Renewal Details
                            @elseif($checkoutType === 'upgrade')
                                Upgrade Details
                            @else
                                Subscription Details
                            @endif
                        </h2>
                        <span class="bg-blue-600 text-white text-xs font-semibold px-3 py-1 rounded-full">
                            @if ($checkoutType === 'renewal')
                                Renewal
                            @elseif($checkoutType === 'upgrade')
                                Upgrade
                            @else
                                New
                            @endif
                        </span>
                    </div>

                    <div class="space-y-4">
                        @if ($checkoutType === 'renewal' && isset($subscription))
                            <div class="flex items-center justify-between pb-3 border-b border-blue-200">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-medium">Plan Renewal</p>
                                        <p class="text-base font-semibold text-gray-900">{{ $plan->name }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pb-3 border-b border-blue-200">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-medium">Billing Cycle</p>
                                        <p class="text-base font-semibold text-gray-900">
                                            {{ ucfirst($subscription->billing_cycle) }}</p>
                                    </div>
                                </div>
                            </div>
                        @elseif($checkoutType === 'upgrade' && isset($subscription))
                            <div class="flex items-center justify-between pb-3 border-b border-blue-200">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-medium">Current Plan</p>
                                        <p class="text-base font-semibold text-gray-900 line-through">
                                            {{ $subscription->plan->name }}</p>
                                    </div>
                                </div>
                                <span class="text-sm text-gray-500 line-through">{{ $subscription->currency }}
                                    {{ number_format($subscription->plan->price_yearly, 2) }}/year</span>
                            </div>

                            <div class="flex items-center justify-between pb-3 border-b border-blue-200">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-medium">New Plan</p>
                                        <p class="text-base font-semibold text-gray-900">{{ $plan->name }}</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center justify-between pb-3 border-b border-blue-200">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-medium">Plan</p>
                                        <p class="text-base font-semibold text-gray-900">{{ $plan->name }}</p>
                                    </div>
                                </div>
                            </div>

                                 @if (isset($trialDays) && $trialDays > 0)
                                     <div class="flex items-center text-sm text-green-400 bg-green-500/10 rounded-lg p-3">
                                         <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                             <path fill-rule="evenodd"
                                                 d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                 clip-rule="evenodd" />
                                         </svg>
                                         <span>{{ $trialDays }} days free trial included - no payment required
                                             today!</span>
                                     </div>
                                 @elseif($checkoutType === 'new' && isset($hasUsedTrial) && $hasUsedTrial)
                                     <div class="flex items-center text-sm text-blue-400 bg-blue-500/10 rounded-lg p-3">
                                         <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                             <path fill-rule="evenodd"
                                                 d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                 clip-rule="evenodd" />
                                         </svg>
                                         <span>You've already used your free trial. Proceeding with paid subscription.</span>
                                     </div>
                                 @endif
                        @endif

                        <div class="flex items-center justify-between pt-2">
                            <span class="text-sm text-gray-600 font-medium">Total Amount</span>
                            <div class="text-right">
                                <div
                                    class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-green-600 to-emerald-600">
                                    @if (isset($trialDays) && $trialDays > 0 && $amount == 0)
                                        Free
                                    @else
                                        ₹{{ number_format($amount / 100, 2) }}
                                    @endif
                                </div>
                                @if (isset($trialDays) && $trialDays > 0 && $amount > 0)
                                    <p class="text-xs text-gray-500 mt-1">Then
                                        ₹{{ number_format($plan->price_yearly, 2) }}/year</p>
                                @elseif($checkoutType === 'renewal' && isset($subscription))
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $subscription->billing_cycle === 'yearly' ? 'per year' : 'per month' }}</p>
                                @else
                                    <p class="text-xs text-gray-500 mt-1">per year</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Badge -->
                <div class="flex items-center justify-center space-x-2 mb-6 text-sm text-gray-600">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span class="font-medium">256-bit SSL encrypted payment</span>
                </div>

                <!-- Payment Button -->
                <button id="rzp-button"
                    class="group relative w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl overflow-hidden disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                    <span
                        class="absolute inset-0 w-full h-full bg-gradient-to-r from-white/0 via-white/20 to-white/0 transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></span>
                    <span class="relative flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span id="button-text">
                            @if (isset($trialDays) && $trialDays > 0 && $amount == 0)
                                Start Free Trial
                            @elseif(isset($isPendingRenewal) && $isPendingRenewal)
                                Complete Renewal Payment
                            @else
                                Pay ₹{{ number_format($amount / 100, 2) }}
                            @endif
                        </span>
                    </span>
                </button>

                <!-- Powered by Razorpay -->
                <div class="flex items-center justify-center space-x-2 mt-4">
                    <span class="text-xs text-gray-500">Secured by</span>
                    <div
                        class="flex items-center space-x-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold px-3 py-1 rounded text-xs">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z" />
                        </svg>
                        <span>Razorpay</span>
                    </div>
                </div>

                <!-- Back Link -->
                <div class="mt-6 text-center">
                    <a href="{{ route('profile') }}"
                        class="inline-flex items-center space-x-2 text-gray-600 hover:text-gray-900 text-sm font-medium transition-colors group">
                        <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Back to Profile</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Trust Indicators -->
        <div class="mt-6 grid grid-cols-3 gap-4 text-center">
            <div class="bg-white/60 backdrop-blur-sm rounded-lg p-3 border border-gray-100">
                <svg class="w-6 h-6 text-blue-600 mx-auto mb-1" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <p class="text-xs font-semibold text-gray-700">Secure Payment</p>
            </div>
            <div class="bg-white/60 backdrop-blur-sm rounded-lg p-3 border border-gray-100">
                <svg class="w-6 h-6 text-green-600 mx-auto mb-1" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-xs font-semibold text-gray-700">Instant Activation</p>
            </div>
            <div class="bg-white/60 backdrop-blur-sm rounded-lg p-3 border border-gray-100">
                <svg class="w-6 h-6 text-purple-600 mx-auto mb-1" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <p class="text-xs font-semibold text-gray-700">24/7 Support</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Auto-submit form when Razorpay payment is successful
        const payButton = document.getElementById('rzp-button');
        let isProcessing = false;
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Razorpay is loaded
            if (typeof Razorpay !== 'undefined') {
                const options = {
                    key: "{{ $key }}",
                    amount: "{{ $amount }}",
                    currency: "INR",
                    order_id: "{{ $orderId }}",
                    name: "{{ config('app.name') }}",
                    description: "{{ $checkoutType === 'renewal' ? 'Subscription Renewal' : ($checkoutType === 'upgrade' ? 'Subscription Upgrade' : 'New Subscription') }}",
                    prefill: {
                        name: "{{ auth()->user()->name }}",
                        email: "{{ auth()->user()->email }}",
                    },
                    theme: {
                        color: "#2563eb"
                    },
                    handler: function(response) {
                        if (isProcessing) return;
                        isProcessing = true;
                        // Show loading state

                        if (payButton) {
                            payButton.disabled = true;
                            payButton.innerHTML =
                                '<span class="flex items-center"><svg class="w-4 h-4 animate-spin mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Processing...</span>';
                        }

                        // Create hidden form and submit
                        const form = document.createElement('form');
                        form.action = "{{ route('subscriptions.verify-payment') }}";
                        form.method = "POST";

                        // Add CSRF token
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content');
                        form.appendChild(csrfInput);

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

                        document.body.appendChild(form);
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
                payButton.onclick = function(e) {
                    e.preventDefault();
                    if (!isProcessing) {
                        rzp.open();
                    }
                };

                // Auto-open the payment modal when page loads
                setTimeout(function() {
                    rzp.open();
                }, 500);
            } else {
                console.error('Razorpay SDK not loaded');
            }
        });
    </script>
@endpush
