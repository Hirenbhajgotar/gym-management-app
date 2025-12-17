<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }
        .container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 40px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .logo {
            display: table-cell;
            font-size: 24px;
            font-weight: bold;
            color: #4F46E5; /* Indigo 600 */
        }
        .invoice-details {
            display: table-cell;
            text-align: right;
        }
        .invoice-title {
            font-size: 28px;
            color: #111;
            margin-bottom: 5px;
        }
        .invoice-meta {
            color: #666;
            font-size: 12px;
        }
        .addresses {
            display: table;
            width: 100%;
            margin-bottom: 40px;
        }
        .address-box {
            display: table-cell;
            width: 48%;
            vertical-align: top;
        }
        .address-title {
            font-weight: bold;
            color: #888;
            text-transform: uppercase;
            font-size: 11px;
            margin-bottom: 10px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table th {
            background-color: #f9fafb;
            padding: 12px 10px;
            text-align: left;
            font-weight: bold;
            color: #555;
            border-bottom: 1px solid #ddd;
        }
        .table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            width: 40%;
            margin-left: auto;
        }
        .totals-row {
            display: table;
            width: 100%;
            padding: 5px 0;
        }
        .totals-label {
            display: table-cell;
            text-align: right;
            padding-right: 20px;
            font-weight: bold;
        }
        .totals-value {
            display: table-cell;
            text-align: right;
            width: 100px;
        }
        .grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #4F46E5;
            border-top: 2px solid #eee;
            padding-top: 10px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 60px;
            border-top: 1px solid #eee;
            padding-top: 20px;
            text-align: center;
            color: #888;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-paid {
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
        <div class="header">
            <div class="logo">
                {{ config('app.name') }}
            </div>
            <div class="invoice-details">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">#{{ $invoice_number }}</div>
                <div class="invoice-meta">Issued: {{ $date }}</div>
            </div>
        </div>

        <div class="addresses">
            <div class="address-box">
                <div class="address-title">Billed To</div>
                @if($gym)
                    <strong>{{ $gym->name }}</strong><br>
                @endif
                <strong>{{ $user->name }}</strong><br>
                {{ $user->email }}<br>
                {{ $user->phone }}
                @if($gym && $gym->address)
                    <br>{{ $gym->address->street }}<br>
                    {{ $gym->address->city->name }}, {{ $gym->address->state->name }} {{ $gym->address->postal_code }}
                @endif
            </div>
            <div class="address-box text-right">
                <div class="address-title">From</div>
                <strong>{{ config('app.name') }}</strong><br>
                <!-- Add platform address here if available -->
                support@fluxfitness.com
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Reference</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        @if($payment->payment_type == 'whatsapp_package')
                            WhatsApp Message Package
                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                {{ $payment->metadata['package_name'] ?? 'Message Package' }} 
                                ({{ $payment->metadata['message_count'] ?? 0 }} messages)
                            </div>
                        @elseif(str_starts_with($payment->payment_type, 'subscription'))
                            Subscription Payment
                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}
                            </div>
                        @else
                            {{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}
                        @endif
                    </td>
                    <td>{{ $payment->razorpay_payment_id ?? 'N/A' }}</td>
                    {{-- <td class="text-right">{{ Number::currency($payment->amount) }}</td> --}}
                    <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Subtotal</div>
                <div class="totals-value">{{ number_format($payment->amount, 2) }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Total</div>
                <div class="totals-value grand-total">
                    {{ number_format($payment->amount, 2) }}
                </div>
            </div>
            <div class="totals-row" style="margin-top: 10px;">
                <div class="totals-label">Status</div>
                <div class="totals-value">
                    @if($payment->status->value == 'completed')
                        <span class="badge badge-paid">PAID</span>
                    @elseif($payment->status->value == 'pending')
                        <span class="badge badge-pending">PENDING</span>
                    @else
                        <span class="badge badge-failed">{{ strtoupper($payment->status->value) }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="footer">
            Thank you for your business!<br>
            If you have any questions about this invoice, please contact support.
        </div>
    </div>
</body>
</html>
