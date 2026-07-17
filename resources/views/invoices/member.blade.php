<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif; font-size: 14px; color: #1f2937; line-height: 1.5; margin: 0; padding: 0; }
        .container { width: 100%; margin: 0 auto; background: #fff; }
        .w-full { width: 100%; }
        .w-half { width: 50%; }
        .align-top { vertical-align: top; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-gray { color: #6b7280; }
        .text-gray-900 { color: #111827; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mt-4 { margin-top: 1rem; }
        .header { padding-bottom: 30px; border-bottom: 1px solid #e5e7eb; margin-bottom: 30px; }
        .logo-text { font-size: 24px; font-weight: 800; color: #4f46e5; }
        .invoice-label { font-size: 12px; font-weight: 600; color: #9ca3af; text-transform: uppercase; margin-bottom: 5px; }
        .info-label { font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 8px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { background-color: #f3f4f6; color: #374151; font-weight: 600; text-transform: uppercase; font-size: 11px; padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .items-table td { padding: 16px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 8px 0; text-align: right; }
        .totals-label { fontWeight: 600; color: #6b7280; padding-right: 20px; }
        .grand-total-row td { padding-top: 15px; border-top: 2px solid #e5e7eb; }
        .grand-total-value { font-size: 20px; font-weight: 700; color: #4f46e5; }
        .params-list { margin: 0; padding-left: 20px; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 9999px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-completed { background-color: #d1fae5; color: #065f46; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-failed { background-color: #fee2e2; color: #b91c1c; }
        .footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <table class="w-full header">
            <tr>
                <td class="w-half align-top">
                    <div class="logo-text">
                        @if ($gym->logo)
                            <img src="{{ storage_path('app/public/' . $gym->logo) }}" alt="{{ $gym->name }}" style="height: 50px; width: auto;">
                        @else
                            {{ $gym->name }}
                        @endif
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
        <table class="w-full mb-4">
            <tr>
                <td class="w-half align-top">
                    <div class="info-label">Billed To</div>
                    @if($user)
                        <div class="font-bold text-gray-900 mb-2">{{ $user->name }}</div>
                        <div class="text-gray-600">{{ $user->email }}</div>
                        <div class="text-gray-600">{{ $user->phone }}</div>
                        @if($user->address)
                            <div class="mt-4 text-gray-600">
                                {{ $user->address->street }}<br>
                                {{ $user->address->city?->name }}, {{ $user->address->state?->name }} {{ $user->address->postal_code }}
                            </div>
                        @endif
                    @else
                        <div class="font-bold text-gray-900 mb-2">{{ $gym->name ?? 'Gym' }}</div>
                    @endif
                </td>
                <td class="w-half align-top text-right">
                    <div class="info-label">Pay To</div>
                    <div class="font-bold text-gray-900 mb-2">{{ $gym->name }}</div>
                    @if($gym->owner_email) <div class="text-gray-600">{{ $gym->owner_email }}</div> @endif
                    @if($gym->phone) <div class="text-gray-600">{{ $gym->phone }}</div> @endif
                    @if($gym->address)
                        <div class="mt-4 text-gray-600">
                            {{ $gym->address->street }}<br>
                            {{ $gym->address->city?->name }}, {{ $gym->address->state?->name }} {{ $gym->address->postal_code }}
                        </div>
                    @endif
                    
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
                        <div class="font-bold mb-1">{{ $payment->membershipPlan->name ?? 'Membership' }} Plan</div>
                        <div class="text-sm text-gray-600">Membership Fee</div>
                    </td>
                    <td class="text-gray-600 font-mono text-xs">{{ $payment->razorpay_payment_id ?? 'N/A' }}</td>
                    <td class="text-right font-bold">{!! $payment->formatted_amount !!}</td>
                </tr>
            </tbody>
        </table>

         <!-- Totals -->
        <table class="w-full">
            <tr>
                <td style="width: 60%;">
                    <div style="padding-right: 40px;">
                        <div class="info-label">Note</div>
                        <div class="text-sm text-gray-500">
                            Thank you for your business. This is a computer-generated invoice from {{ $gym->name }}.
                        </div>
                    </div>
                </td>
                <td style="width: 40%;">
                    <table class="totals-table">
                        <tr>
                            <td class="totals-label">Subtotal</td>
                            <td class="totals-value">{!! $payment->formatted_amount !!}</td>
                        </tr>
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
            &copy; {{ date('Y') }} {{ $gym->name }}. All rights reserved.
        </div>
    </div>
</body>
</html>
