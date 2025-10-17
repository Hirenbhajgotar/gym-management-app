@extends('layouts.checkout')

@section('title', 'Complete Payment - ' . config('app.name'))

@section('success-message', 'Your WhatsApp message credits have been added')

@section('redirect-message', 'Redirecting to settings...')

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
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white">{{ config('app.name') }}</h1>
                            <p class="text-blue-100 text-xs">Gym Management Platform</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 bg-white/20 backdrop-blur-sm px-3 py-1.5 rounded-full">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-white text-xs font-medium">Secure</span>
                    </div>
                </div>
                <p class="text-blue-50 text-sm">Complete your WhatsApp message package purchase</p>
            </div>

            <div class="p-8">
                <!-- Gym Information -->
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-4 mb-6 border border-slate-200">
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Purchasing for</p>
                            <p class="text-base font-bold text-slate-900 truncate">{{ $tenant->name }}</p>
                        </div>
                    </div>
                </div>

                <!-- Package Details Card -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 mb-6 border border-blue-100">
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-900">Package Details</h2>
                        <span class="bg-blue-600 text-white text-xs font-semibold px-3 py-1 rounded-full">Premium</span>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-blue-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Package Name</p>
                                    <p class="text-base font-semibold text-gray-900">{{ $packageName }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pb-3 border-b border-blue-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Message Credits</p>
                                    <p class="text-base font-semibold text-gray-900">{{ number_format($messageCount) }}
                                        messages</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <span class="text-sm text-gray-600 font-medium">Total Amount</span>
                            <div class="text-right">
                                <div
                                    class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-green-600 to-emerald-600">
                                    ₹{{ number_format($amount / 100, 2) }}
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Inclusive of all taxes</p>
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
                        <span id="button-text">Proceed to Pay ₹{{ number_format($amount / 100, 2) }}</span>
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
                    <a href="{{ route('filament.admin.pages.settings', ['tenant' => $tenant->slug, 'tab' => 'whatsapp']) }}"
                        class="inline-flex items-center space-x-2 text-gray-600 hover:text-gray-900 text-sm font-medium transition-colors group">
                        <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Back to Settings</span>
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
                <p class="text-xs font-semibold text-gray-700">Instant Delivery</p>
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
        const payButton = document.getElementById('rzp-button');
        const buttonText = document.getElementById('button-text');
        const loadingOverlay = document.getElementById('loading-overlay');
        let isProcessing = false;

        const options = {
            key: '{{ $key }}',
            amount: '{{ $amount }}',
            currency: 'INR',
            order_id: '{{ $orderId }}',
            name: '{{ config('app.name') }}',
            description: 'Purchase {{ $packageName }}',
            handler: function(response) {
                if (isProcessing) return;
                isProcessing = true;

                // Disable button and show processing state
                payButton.disabled = true;
                buttonText.innerHTML =
                    '<svg class="animate-spin h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

                // Handle successful payment
                fetch('{{ route('payments.verify') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content')
                        },
                        body: JSON.stringify({
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature,
                            tenant_id: '{{ $tenantId }}'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success overlay
                            loadingOverlay.classList.remove('hidden');

                            // Redirect after showing success message
                            setTimeout(() => {
                                window.location.href = data.redirect_url ||
                                    '{{ route('payments.success', ['tenant' => $tenantId]) }}&payment_id=' +
                                    data.payment_id;
                            }, 2000);
                        } else {
                            window.location.href =
                                '{{ route('payments.failure', ['tenant' => $tenantId, 'order_id' => $orderId]) }}';
                        }
                    })
                    .catch(error => {
                        console.error('Payment verification failed:', error);
                        window.location.href =
                            '{{ route('payments.failure', ['tenant' => $tenantId, 'order_id' => $orderId]) }}';
                    });
            },
            modal: {
                ondismiss: function() {
                    // Re-enable button if payment modal is dismissed
                    if (!isProcessing) {
                        payButton.disabled = false;
                        buttonText.textContent = 'Proceed to Pay ₹{{ number_format($amount / 100, 2) }}';
                    }

                    // window.location.href =
                    //     '{{ route('filament.admin.pages.settings', ['tenant' => $tenant->slug, 'tab' => 'whatsapp']) }}';
                }
            },
            prefill: {
                name: '{{ auth()->user()->name ?? '' }}',
                email: '{{ auth()->user()->email ?? '' }}',
            },
            theme: {
                color: '#2563eb'
            }
        };

        const rzp = new Razorpay(options);

        payButton.onclick = function(e) {
            e.preventDefault();
            if (!isProcessing) {
                rzp.open();
            }
        };
    </script>
@endpush
