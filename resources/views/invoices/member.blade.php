<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Receipt {{ $invoice_number }}</title>
    @include('invoices.partials.styles')
</head>
<body>
    <div class="container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="w-55 align-top">
                    <table style="border-collapse: collapse; margin: 0; padding: 0;">
                        <tr>
                            @if(isset($gym->logo) && $gym->logo && file_exists(storage_path('app/public/' . $gym->logo)))
                                <td style="padding: 0 10px 0 0; vertical-align: middle;">
                                    <img src="{{ storage_path('app/public/' . $gym->logo) }}" alt="{{ $gym->name ?? 'Gym' }}" style="max-height: 36px; max-width: 100px;">
                                </td>
                            @else
                                <td style="padding: 0 10px 0 0; vertical-align: middle;">
                                    <div class="gym-initials-badge">
                                        {{ mb_strtoupper(mb_substr($gym->name ?? 'G', 0, 1, 'UTF-8')) }}
                                    </div>
                                </td>
                            @endif
                            <td style="padding: 0; vertical-align: middle;">
                                <div class="brand-name">{{ $gym->name ?? 'Gym' }}</div>
                                <div class="brand-tagline">Official Member Payment Receipt</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="w-45 align-top text-right">
                    <div><span class="invoice-badge">RECEIPT</span></div>
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

        <!-- Subtle Cyan Accent Bar -->
        <div class="accent-bar"></div>

        <!-- Billing Info Cards Grid -->
        <table class="info-grid-table">
            <tr>
                <!-- Billed To -->
                <td class="w-half card-box">
                    <div class="card-label">Billed To (Member)</div>
                    @if($owner ?? $user)
                        <div class="card-title">{{ ($owner ?? $user)->name }}</div>
                        @if(($owner ?? $user)->email)
                            <div class="card-text">{{ ($owner ?? $user)->email }}</div>
                        @endif
                        @if(($owner ?? $user)->phone)
                            <div class="card-text">{{ ($owner ?? $user)->phone }}</div>
                        @endif
                        @if(($owner ?? $user)->address)
                            <div class="card-row" style="margin-top: 2px;">
                                {{ ($owner ?? $user)->address->street ?? '' }}
                                @if(($owner ?? $user)->address->city)
                                    <br>{{ ($owner ?? $user)->address->city->name }}, {{ ($owner ?? $user)->address->state?->name ?? '' }} {{ ($owner ?? $user)->address->postal_code ?? '' }}
                                @endif
                            </div>
                        @endif
                    @else
                        <div class="card-title">Member</div>
                    @endif
                </td>

                <!-- Pay To / Gym Info -->
                <td class="w-half card-box">
                    <div class="card-label">Pay To / Gym Details</div>
                    <div class="card-title">{{ $gym->name ?? 'Gym' }}</div>
                    @if($gym_owner_email ?? $gym?->owner_email)
                        <div class="card-row"><strong>Email:</strong> {{ $gym_owner_email ?? $gym->owner_email }}</div>
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
                        <div class="item-main-title">{{ $payment->membershipPlan->name ?? 'Gym Membership' }} Plan</div>
                        <div class="item-sub-desc">
                            Membership Subscription &bull; {{ $gym->name ?? 'Gym' }}
                        </div>
                    </td>
                    <td>
                        <span class="mono-ref">#{{ $payment->id }}</span>
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
                        <div class="note-heading">Official Note</div>
                        <div class="note-content">
                            Thank you for your business. This is an electronically generated payment receipt from <strong>{{ $gym->name ?? 'your gym' }}</strong> and requires no physical signature.
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
            &copy; {{ date('Y') }} {{ $gym->name ?? 'Gym' }}. All rights reserved.<br>
            Powered by {{ config('app.name', 'FluxFitness') }} Gym Management Platform
        </div>
    </div>
</body>
</html>
