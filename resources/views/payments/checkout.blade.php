<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Complete Payment - {{ config('app.name') }}</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-6">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Complete Your Payment</h1>
            <p class="text-gray-600">Secure payment powered by Razorpay</p>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Package Details</h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">Package:</span>
                    <span class="font-medium">{{ $packageName }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Messages:</span>
                    <span class="font-medium">{{ number_format($messageCount) }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold">
                    <span class="text-gray-900">Amount:</span>
                    <span class="text-green-600">₹{{ number_format($amount / 100, 2) }}</span>
                </div>
            </div>
        </div>

        <button id="rzp-button"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200">
            Pay ₹{{ number_format($amount / 100, 2) }}
        </button>

        <div class="mt-4 text-center">
            <a href="{{ route('filament.admin.pages.settings', ['tenant' => $tenant->slug, 'tab' => 'whatsapp']) }}" <a
                href="" class="text-gray-600 hover:text-gray-800 text-sm">
                ← Back to Settings
            </a>
        </div>
    </div>

    <script>
        const options = {
            key: '{{ $key }}',
            amount: '{{ $amount }}',
            currency: 'INR',
            order_id: '{{ $orderId }}',
            name: '{{ config('app.name') }}',
            description: 'Purchase {{ $packageName }}',
            handler: function(response) {
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
                            window.location.href = data.redirect_url ||
                                '{{ route('payments.success', ['tenant' => $tenantId]) }}';
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
                    // Handle payment modal dismissal
                    window.location.href =
                        '{{ route('filament.admin.pages.settings', ['tenant' => $tenant->slug, 'tab' => 'whatsapp']) }}';
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

        document.getElementById('rzp-button').onclick = function(e) {
            rzp.open();
            e.preventDefault();
        };
    </script>
</body>

</html>
