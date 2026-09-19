<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice_number }}</title>
    @include('invoices.partials.styles')
</head>
<body>
    <div class="container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="w-55 align-top">
                    <!-- Brand Identity -->
                    <table class="brand-logo-table">
                        <tr>
                            @if(file_exists(public_path('fluxfitness-icon.png')))
                                <td class="brand-icon-cell">
                                    <img src="{{ public_path('fluxfitness-icon.png') }}" class="brand-icon-img" alt="{{ config('app.name', 'FluxFitness') }}">
                                </td>
                            @endif
                            <td style="padding: 0; vertical-align: middle;">
                                <div class="brand-name">
                                    <span class="brand-dark">{{ strtoupper(substr(config('app.name', 'FluxFitness'), 0, 4)) }}</span><span class="brand-cyan">{{ strtoupper(substr(config('app.name', 'FluxFitness'), 4)) }}</span>
                                </div>
                                <div class="brand-tagline">Gym Operating System & Automation Platform</div>
                            </td>
                        </tr>
                    </table>
                    <div class="brand-contact">{{ config('app.contact_email', 'contact.fluxfitness@gmail.com') }} &bull; {{ parse_url(config('app.url', 'https://fluxfitness.com'), PHP_URL_HOST) ?? 'fluxfitness.com' }}</div>
                </td>
                <td class="w-45 align-top text-right">
                    <div><span class="invoice-badge">INVOICE</span></div>
                    <div class="invoice-number">#{{ $invoice_number }}</div>
                    <div class="invoice-date">Date: <strong>{{ $date }}</strong></div>
                    <div>
                        @php
                            $statusVal = $payment->status?->value ?? 'completed';
                        @endphp
                        @if($statusVal === 'completed')
                            <span class="badge badge-completed">PAID</span>
                        @elseif($statusVal === 'pending')
                            <span class="badge badge-pending">PENDING</span>
                        @else
                            <span class="badge badge-failed">{{ strtoupper($statusVal) }}</span>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Subtle Cyan Accent Bar matching Homepage -->
        <div class="accent-bar"></div>

        <!-- Billing Info Cards Grid -->
        <table class="info-grid-table">
            <tr>
                <!-- Billed To -->
                <td class="w-half card-box">
                    <div class="card-label">Billed To</div>
                    @if($owner ?? $user)
                        <div class="card-title">{{ ($owner ?? $user)->name }}</div>
                        <div class="card-text">{{ ($owner ?? $user)->email }}</div>
                        @if(($owner ?? $user)->phone)
                            <div class="card-text">{{ ($owner ?? $user)->phone }}</div>
                        @endif
                    @else
                        <div class="card-title">{{ $gym->name ?? 'Gym Owner' }}</div>
                        <div class="card-text">{{ $payment->metadata['owner_email'] ?? '' }}</div>
                    @endif

                    @if($gym && $gym->name && ($owner ?? $user))
                        <div class="card-row" style="margin-top: 3px;"><strong>Gym:</strong> {{ $gym->name }}</div>
                    @endif

                    @if($gym?->address)
                        <div class="card-row" style="margin-top: 2px;">
                            {{ $gym->address->street ?? '' }}
                            @if($gym->address->city)
                                <br>{{ $gym->address->city->name }}, {{ $gym->address->state?->name ?? '' }} {{ $gym->address->postal_code ?? '' }}
                            @endif
                        </div>
                    @endif
                </td>

                <!-- Pay To / Payment Details -->
                <td class="w-half card-box">
                    <div class="card-label">Payment & Issuer Details</div>
                    <div class="card-title">{{ config('app.name', 'FluxFitness') }}</div>
                    <div class="card-row"><strong>Merchant Email:</strong> {{ config('app.contact_email', 'contact.fluxfitness@gmail.com') }}</div>
                    
                    <div class="card-row" style="margin-top: 3px;">
                        <strong>Reference ID:</strong> 
                        <span class="mono-ref">{{ $payment->razorpay_payment_id ?? 'N/A' }}</span>
                    </div>
                    
                    <div class="card-row">
                        <strong>Payment Type:</strong> 
                        @if($payment->payment_type === 'whatsapp_package')
                            WhatsApp Package
                        @else
                            {{ ucfirst(str_replace('_', ' ', $payment->payment_type ?? 'Subscription')) }}
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 52%;">Description</th>
                    <th style="width: 24%;">Reference ID</th>
                    <th style="width: 24%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        @if($payment->payment_type === 'whatsapp_package')
                            <div class="item-main-title">WhatsApp Message Package</div>
                            <div class="item-sub-desc">
                                {{ $payment->metadata['package_name'] ?? 'Message Package' }} 
                                &bull; {{ number_format($payment->metadata['message_count'] ?? 0) }} Automated Messages
                            </div>
                        @elseif(str_starts_with($payment->payment_type ?? '', 'subscription') || $payment->payment_type === 'gym_owner_registration')
                            <div class="item-main-title">
                                {{ $payment->metadata['plan_name'] ?? 'Pro' }} Plan Subscription
                            </div>
                            <div class="item-sub-desc">
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_type ?? 'subscription')) }}
                                @if(isset($payment->metadata['billing_cycle']))
                                    &bull; {{ ucfirst($payment->metadata['billing_cycle']) }} Billing
                                @endif
                                &bull; Gym Operations, Billing & Member Management
                            </div>
                        @else
                            <div class="item-main-title">{{ ucfirst(str_replace('_', ' ', $payment->payment_type ?? 'Subscription Payment')) }}</div>
                            <div class="item-sub-desc">{{ config('app.name', 'FluxFitness') }} Platform Service</div>
                        @endif
                    </td>
                    <td>
                        <span class="mono-ref">{{ $payment->razorpay_payment_id ?? 'N/A' }}</span>
                    </td>
                    <td class="item-amount-val">
                        {{ $payment->formatted_amount }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Totals & Notes Section -->
        <table class="summary-table">
            <tr>
                <td class="w-55 align-top">
                    <div class="note-box">
                        <div class="note-heading">Official Note & Verification</div>
                        <div class="note-content">
                            Thank you for your business. This is an electronically generated invoice and requires no physical signature.<br>
                            For inquiries, please contact our support team at <strong>{{ config('app.contact_email', 'contact.fluxfitness@gmail.com') }}</strong>.
                        </div>
                    </div>
                </td>
                <td class="w-45 align-top">
                    <table class="totals-table">
                        <tr>
                            <td class="totals-label">Subtotal:</td>
                            <td class="totals-val">{{ $payment->formatted_amount }}</td>
                        </tr>
                        <tr class="grand-total-divider">
                            <td class="totals-label grand-total-label">
                                {{ $payment->status === \App\Enums\PaymentStatus::COMPLETED ? 'Total Paid:' : 'Total Amount:' }}
                            </td>
                            <td class="totals-val grand-total-amount">{{ $payment->formatted_amount }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name', 'FluxFitness') }}. All rights reserved.<br>
            Platform Service Invoice &bull; Generated for gym management services &bull; {{ parse_url(config('app.url', 'https://fluxfitness.com'), PHP_URL_HOST) ?? 'fluxfitness.com' }}
        </div>
    </div>
</body>
</html>
