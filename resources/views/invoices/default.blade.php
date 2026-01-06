<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            font-size: 14px;
            color: #1f2937; /* Gray 800 */
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            margin: 0 auto;
            background: #fff;
        }
        /* Layout Helpers */
        .w-full { width: 100%; }
        .w-half { width: 50%; }
        .align-top { vertical-align: top; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mt-4 { margin-top: 1rem; }
        
        /* Typography */
        h1, h2, h3, h4 { margin: 0; font-weight: 700; }
        .text-sm { font-size: 12px; }
        .text-xs { font-size: 10px; }
        .text-lg { font-size: 18px; }
        .text-xl { font-size: 24px; }
        .font-bold { font-weight: bold; }
        .text-gray { color: #6b7280; }
        .text-primary { color: #4f46e5; }
        .uppercase { text-transform: uppercase; }
        
        /* Header */
        .header {
            padding-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 30px;
        }
        .logo-text {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #4f46e5;
        }
        .invoice-label {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #9ca3af;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        /* Info Section */
        .info-table {
            width: 100%;
            margin-bottom: 40px;
        }
        .info-label {
            font-size: 11px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .info-content {
            font-size: 13px;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .items-table td {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .item-description {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .item-subtext {
            font-size: 12px;
            color: #6b7280;
        }
        
        /* Totals */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 0;
            text-align: right;
        }
        .totals-label {
            font-weight: 600;
            color: #6b7280;
            padding-right: 20px;
        }
        .totals-value {
            color: #111827;
            width: 150px;
        }
        .grand-total-row td {
            padding-top: 15px;
            padding-bottom: 15px;
            border-top: 2px solid #e5e7eb;
        }
        .grand-total-label {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .grand-total-value {
            font-size: 20px;
            font-weight: 700;
            color: #4f46e5;
        }
        
        /* Footer */
        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
        }
        
        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-completed {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-failed {
            background-color: #fee2e2;
            color: #b91c1c;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <table class="w-full header">
            <tr>
                <td class="w-half align-top">
                    <div class="logo-text">
                        <img src="{{ public_path('fluxfitness-logo.svg') }}" alt="{{ config('app.name') }}" style="height: 50px; width: auto;">
                    </div>
                </td>
                <td class="w-half align-top text-right">
                    <div class="invoice-label">Invoice</div>
                    <div class="text-xl font-bold text-gray-900">#{{ $invoice_number }}</div>
                    <div class="text-sm text-gray mt-4">
                        Date: <span class="text-gray-900 font-bold">{{ $date }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Billing Info -->
        <table class="info-table">
            <tr>
                <td class="w-half align-top">
                    <div class="info-label">Billed To</div>
                    <div class="info-content">
                        <div class="font-bold text-gray-900 mb-2" style="font-size: 15px;">{{ $user->name }}</div>

                        <div class="text-gray-600">{{ $user->email }}</div>
                        <div class="text-gray-600">{{ $user->phone }}</div>

                        @if($gym && $gym->address)
                            <div class="mt-4 text-gray-600">
                                {{ $gym->address->street }}<br>
                                {{ $gym->address->city?->name }}, {{ $gym->address->state?->name }} {{ $gym->address->postal_code }}
                            </div>
                        @endif
                    </div>
                </td>
                <td class="w-half align-top text-right">
                    <div class="info-label">Pay To</div>
                    <div class="info-content">
                        {{-- For System Payments: Pay to the Platform --}}
                        <div class="font-bold text-gray-900 mb-2" style="font-size: 15px;">{{ config('app.name') }}</div>
                        <div class="text-gray-600">{{ config('app.contact_email') }}</div>
                    </div>

                    <div class="mt-4">
                         <div class="info-label">Payment Status</div>
                        @if($payment->status->value == 'completed')
                            <span class="badge badge-completed">PAID</span>
                        @elseif($payment->status->value == 'pending')
                            <span class="badge badge-pending">PENDING</span>
                        @else
                            <span class="badge badge-failed">{{ strtoupper($payment->status->value) }}</span>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th style="width: 25%;">Reference ID</th>
                    <th style="width: 25%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        @if($payment->payment_type == 'whatsapp_package')
                            <div class="item-description">WhatsApp Message Package</div>
                            <div class="item-subtext">
                                {{ $payment->metadata['package_name'] ?? 'Message Package' }} 
                                &bull; {{ $payment->metadata['message_count'] ?? 0 }} messages
                            </div>
                        @elseif(str_starts_with($payment->payment_type, 'subscription') || $payment->payment_type == 'gym_owner_registration')
                            <div class="item-description">
                                {{ $payment->metadata['plan_name'] ?? 'Subscription' }} Plan
                            </div>
                            <div class="item-subtext">
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}
                            </div>
                        @else
                            <div class="item-description">{{ ucfirst(str_replace('_', ' ', $payment->payment_type ?? 'Payment')) }}</div>
                        @endif
                    </td>
                    <td class="text-gray-600 font-mono text-xs">
                        {{ $payment->razorpay_payment_id ?? 'N/A' }}
                    </td>
                    <td class="text-right font-bold">
                        {!! $payment->formatted_amount !!}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Totals -->
        <table class="w-full">
            <tr>
                <td style="width: 60%;">
                    <!-- Optional Notes Section -->
                    <div style="padding-right: 40px;">
                        <div class="info-label">Note</div>
                        <div class="text-sm text-gray-500">
                            Thank you for your business. This is a computer-generated invoice and requires no signature.
                        </div>
                    </div>
                </td>
                <td style="width: 40%;">
                    <table class="totals-table">
                        <tr>
                            <td class="totals-label">Subtotal</td>
                            <td class="totals-value">{!! $payment->formatted_amount !!}</td>
                        </tr>
                        <!-- Tax rows can be added here if needed -->
                        <tr class="grand-total-row">
                            <td class="totals-label grand-total-label">Total</td>
                            <td class="totals-value grand-total-value">{!! $payment->formatted_amount !!}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
            For any inquiries, please contact us at support@fluxfitness.com
        </div>
    </div>
</body>
</html>
