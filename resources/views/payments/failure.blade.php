<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-6">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Failed</h1>
            <p class="text-gray-600 mb-6">Your payment could not be processed. Please try again.</p>

            @if ($payment)
                <div class="bg-red-50 rounded-lg p-4 mb-6">
                    <h2 class="text-lg font-semibold text-red-900 mb-2">Payment Details</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-red-700">Order ID:</span>
                            <span class="font-medium">{{ $payment->razorpay_order_id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-red-700">Amount:</span>
                            <span class="font-medium">₹{{ number_format($payment->amount, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-red-700">Status:</span>
                            <span class="font-medium capitalize">{{ $payment->status->getLabel() }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-3">
                <a href="{{ route('filament.admin.pages.settings', ['tenant' => $tenant->slug, 'tab' => 'whatsapp']) }}"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Try Again
                </a>

                <a href="{{ route('filament.admin.pages.dashboard', ['tenant' => $tenant->slug]) }}"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Go to Dashboard
                </a>
            </div>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-500">
                    Need help? Contact our support team.
                </p>
            </div>
        </div>
    </div>
</body>

</html>
