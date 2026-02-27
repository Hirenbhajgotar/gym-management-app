<x-filament-panels::page>
    <style>
        /* Custom CSS for WhatsApp Credits Page */
        .wa-credits-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .wa-hero {
            text-align: center;
            margin-bottom: 80px;
        }

        .wa-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 24px;
            color: var(--gray-950);
        }

        .dark .wa-hero h1 {
            color: white;
        }

        .wa-hero .gradient-text {
            background: linear-gradient(135deg, #36e27b 0%, #2bc46a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wa-hero p {
            font-size: 1.25rem;
            line-height: 1.8;
            color: #6b7280;
            max-width: 700px;
            margin: 0 auto 40px;
        }

        .dark .wa-hero p {
            color: #9ca3af;
        }

        .wa-cta-group {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .wa-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 32px;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #36e27b 0%, #2bc46a 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(54, 226, 123, 0.3);
            text-decoration: none;
        }

        .wa-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(54, 226, 123, 0.4);
        }

        .wa-btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 32px;
            font-size: 1rem;
            font-weight: 700;
            color: #36e27b;
            background: transparent;
            border: 2px solid #36e27b;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .wa-btn-outline:hover {
            background: rgba(54, 226, 123, 0.05);
        }

        .wa-section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .wa-section-title h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 16px;
            color: var(--gray-950);
        }

        .dark .wa-section-title h2 {
            color: white;
        }

        .wa-section-title p {
            font-size: 1.125rem;
            color: #6b7280;
            /* max-width: 700px; */
            margin: 0 auto;
        }

        .dark .wa-section-title p {
            color: #9ca3af;
        }

        .wa-pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 32px;
            margin-bottom: 80px;
        }

        .wa-pricing-card {
            position: relative;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 24px;
            padding: 32px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .dark .wa-pricing-card {
            background: #1f2937;
            border-color: #374151;
        }

        .wa-pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .wa-pricing-card.popular {
            border-color: #36e27b;
            box-shadow: 0 0 0 4px rgba(54, 226, 123, 0.2);
        }

        .wa-popular-badge {
            position: absolute;
            top: 24px;
            right: 24px;
            background: #36e27b;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .wa-pricing-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--gray-950);
        }

        .dark .wa-pricing-card h3 {
            color: white;
        }

        .wa-pricing-card .description {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 24px;
        }

        .dark .wa-pricing-card .description {
            color: #9ca3af;
        }

        .wa-price {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-950);
            margin-bottom: 16px;
        }

        .dark .wa-price {
            color: white;
        }

        .wa-message-info {
            background: #f9fafb;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .dark .wa-message-info {
            background: #111827;
        }

        .wa-message-info .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }

        .wa-message-info .row + .row {
            border-top: 1px solid #e5e7eb;
            margin-top: 8px;
            padding-top: 12px;
        }

        .dark .wa-message-info .row + .row {
            border-color: #374151;
        }

        .wa-message-info .label {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .dark .wa-message-info .label {
            color: #9ca3af;
        }

        .wa-message-info .value {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--gray-950);
        }

        .dark .wa-message-info .value {
            color: white;
        }

        .wa-message-info .per-message {
            font-size: 0.75rem;
            font-weight: 600;
            color: #36e27b;
        }

        .wa-features {
            list-style: none;
            padding: 0;
            margin: 0 0 24px 0;
            flex: 1;
        }

        .wa-features li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 0.875rem;
            color: #374151;
        }

        .dark .wa-features li {
            color: #d1d5db;
        }

        .wa-features li svg {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            color: #36e27b;
        }

        .wa-btn-select {
            width: 100%;
            padding: 14px 24px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .wa-btn-select.primary {
            background: linear-gradient(135deg, #36e27b 0%, #2bc46a 100%);
            color: white;
        }

        .wa-btn-select.secondary {
            background: white;
            color: var(--gray-950);
            border: 2px solid #e5e7eb;
        }

        .dark .wa-btn-select.secondary {
            background: #1f2937;
            color: white;
            border-color: #374151;
        }

        .wa-btn-select:hover {
            transform: scale(1.02);
        }

        .wa-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 48px;
            margin-bottom: 80px;
        }

        .wa-step {
            text-align: center;
        }

        .wa-step-number {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #36e27b 0%, #2bc46a 100%);
            border-radius: 50%;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 25px rgba(54, 226, 123, 0.3);
        }

        .wa-step h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--gray-950);
        }

        .dark .wa-step h3 {
            color: white;
        }

        .wa-step p {
            color: #6b7280;
            line-height: 1.6;
        }

        .dark .wa-step p {
            color: #9ca3af;
        }

        .wa-stats-card {
            background: linear-gradient(135deg, #f9fafb 0%, white 100%);
            border: 2px solid #e5e7eb;
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 80px;
        }

        .dark .wa-stats-card {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            border-color: #374151;
        }

        .wa-stats-card h3 {
            text-align: center;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 40px;
            color: var(--gray-950);
        }

        .dark .wa-stats-card h3 {
            color: white;
        }

        .wa-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .wa-stat-box {
            background: white;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .dark .wa-stat-box {
            background: #1f2937;
        }

        .wa-stat-box.highlight {
            background: linear-gradient(135deg, #36e27b 0%, #2bc46a 100%);
        }

        .wa-stat-box .label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .wa-stat-box.highlight .label {
            color: rgba(255, 255, 255, 0.8);
        }

        .wa-stat-box .value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-950);
        }

        .dark .wa-stat-box .value {
            color: white;
        }

        .wa-stat-box.highlight .value {
            color: white;
        }

        .wa-progress {
            margin-top: 32px;
        }

        .wa-progress .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.875rem;
        }

        .wa-progress .progress-label .label {
            color: #6b7280;
            font-weight: 500;
        }

        .dark .wa-progress .progress-label .label {
            color: #9ca3af;
        }

        .wa-progress .progress-label .value {
            color: var(--gray-950);
            font-weight: 700;
        }

        .dark .wa-progress .progress-label .value {
            color: white;
        }

        .wa-progress-bar {
            height: 12px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .dark .wa-progress-bar {
            background: #374151;
        }

        .wa-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #36e27b 0%, #2bc46a 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .wa-link {
            color: #36e27b;
            text-decoration: underline;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .wa-link:hover {
            color: #2bc46a;
        }

        @media (max-width: 768px) {
            .wa-hero h1 {
                font-size: 2.5rem;
            }

            .wa-hero p {
                font-size: 1rem;
            }

            .wa-section-title h2 {
                font-size: 2rem;
            }

            .wa-cta-group {
                flex-direction: column;
            }

            .wa-pricing-grid {
                grid-template-columns: 1fr;
            }

            .wa-steps {
                grid-template-columns: 1fr;
            }

            .wa-steps-flow {
                flex-direction: column !important;
                align-items: center !important;
            }

            .wa-steps-flow .wa-step {
                max-width: 100% !important;
                padding: 0 0 24px 0 !important;
            }

            .wa-steps-flow .wa-flow-arrow {
                transform: rotate(90deg);
                padding: 0 !important;
                margin: -8px 0;
            }
        }
    </style>

    {{-- Hero Section --}}
    <div class="wa-credits-container--">
        <div class="wa-hero">
            <h1>
                Buy WhatsApp Credits 
                <span class="gradient-text">Instantly</span>
            </h1>
            <p>
                Affordable, fast and secure credit system for high-volume messaging. Connect with your customers globally without breaking the bank.
            </p>
            <div class="wa-cta-group">
                {{-- <a href="#pricing" class="wa-btn-primary">
                    Buy Credits Now
                </a> --}}
                
                @if($this->canClaimWelcomeCredits())
                    <button wire:click="claimWelcomeCredits" wire:loading.attr="disabled" class="wa-btn-outline">
                        <span wire:target="claimWelcomeCredits" wire:loading.remove>
                            <x-heroicon-m-gift style="width: 20px; height: 20px;" />
                        </span>
                        <span wire:target="claimWelcomeCredits" wire:loading class="animate-spin">
                            <x-heroicon-m-arrow-path style="width: 20px; height: 20px;" />
                        </span>
                        <span wire:target="claimWelcomeCredits" wire:loading>Claiming...</span>
                        <span wire:target="claimWelcomeCredits" wire:loading.remove>Claim Free Credits</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Pricing Section --}}
        <div id="pricing">
            <div class="wa-section-title">
                <h2>Simple & Transparent Pricing</h2>
                <p>Choose the perfect plan for your business needs. All plans include instant delivery and no expiration.</p>
            </div>

            <div class="wa-pricing-grid">
                @foreach($packages as $package)
                    @php
                        $isPopular = $package['is_popular'] ?? false;
                    @endphp
                    
                    <div class="wa-pricing-card {{ $isPopular ? 'popular' : '' }}">
                        @if($isPopular)
                            <div class="wa-popular-badge">Most Popular</div>
                        @endif

                        <h3>{{ $package['name'] }}</h3>
                        @if($package['description'])
                            <p class="description">{{ $package['description'] }}</p>
                        @endif

                        <div class="wa-price">₹{{ number_format($package['selling_price'], 0) }}</div>

                        <div class="wa-message-info">
                            <div class="row">
                                <span class="label">Messages</span>
                                <span class="value">{{ number_format($package['message_count']) }}</span>
                            </div>
                            @if($package['message_count'] > 0)
                                <div class="row">
                                    <span class="label">Per message</span>
                                    <span class="per-message">₹{{ number_format($package['selling_price'] / $package['message_count'], 2) }}</span>
                                </div>
                            @endif
                        </div>

                        <ul class="wa-features">
                            @if($package['features'] && is_array($package['features']))
                                @foreach($package['features'] as $feature)
                                    <li>
                                        <x-heroicon-s-check-circle />
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            @else
                                <li>
                                    <x-heroicon-s-check-circle />
                                    <span>Basic API Access</span>
                                </li>
                                <li>
                                    <x-heroicon-s-check-circle />
                                    <span>Email Support</span>
                                </li>
                                <li>
                                    <x-heroicon-s-check-circle />
                                    <span>Standard Delivery Speed</span>
                                </li>
                            @endif
                        </ul>

                        <button id="rzp-purchase-credits-button"
                            wire:click="selectPackage({{ $package['id'] }})"
                            class="wa-btn-select {{ $isPopular ? 'primary' : 'secondary' }}">
                            <x-loading-icon target="selectPackage({{ $package['id'] }})" variant="text" name="Loading" />

                            <span wire:loading.remove wire:target="selectPackage({{ $package['id'] }})">
                                Select {{ $package['name'] }}
                            </span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- How It Works Section --}}
        <div id="how-it-works">
            <div class="wa-section-title">
                <h2>How It Works</h2>
                <p>Get started in minutes with our simple 4-step process</p>
            </div>

            <div class="wa-steps-flow" style="display: flex; align-items: flex-start; justify-content: center; gap: 0; margin-bottom: 80px; flex-wrap: wrap;">
                <div class="wa-step" style="flex: 1; min-width: 200px; max-width: 280px; text-align: center; padding: 0 16px; position: relative;">
                    <div class="wa-step-number">1</div>
                    <h3>Setup</h3>
                    <p>Simply <a href="{{ route('filament.admin.pages.whats-app-setup', ['tenant' => $this->getTenantSlug()]) }}" class="wa-link">connect your WhatsApp Business account</a> in just a few clicks.</p>
                </div>
                
                <div class="wa-step" style="flex: 1; min-width: 200px; max-width: 280px; text-align: center; padding: 0 16px; position: relative;">
                    <div class="wa-step-number">2</div>
                    <h3>Create Template</h3>
                    <p>Create WhatsApp message templates that comply with Meta's guidelines for approval.</p>
                </div>
                
                <div class="wa-step" style="flex: 1; min-width: 200px; max-width: 280px; text-align: center; padding: 0 16px; position: relative;">
                    <div class="wa-step-number">3</div>
                    <h3>Purchase Credits</h3>
                    <p>Select a package that fits your needs. Payment is secure and credits are added instantly.</p>
                </div>
                
                <div class="wa-step" style="flex: 1; min-width: 200px; max-width: 280px; text-align: center; padding: 0 16px; position: relative;">
                    <div class="wa-step-number">4</div>
                    <h3>Send Messages</h3>
                    <p>Start sending WhatsApp messages immediately using your approved templates.</p>
                </div>
            </div>
        </div>

        {{-- Stats Section --}}
        @if($creditBalance)
            <div class="wa-stats-card">
                <h3>Your Credit Balance</h3>
                
                <div class="wa-stats-grid">
                    <div class="wa-stat-box">
                        <div class="label">Total Purchased</div>
                        <div class="value">{{ number_format($creditBalance->total_credits) }}</div>
                    </div>
                    
                    <div class="wa-stat-box highlight">
                        <div class="label">Available Now</div>
                        <div class="value">{{ number_format($creditBalance->remaining_credits) }}</div>
                    </div>
                    
                    <div class="wa-stat-box">
                        <div class="label">Used</div>
                        <div class="value">{{ number_format($creditBalance->used_credits) }}</div>
                    </div>
                </div>

                @if($creditBalance->total_credits > 0)
                    <div class="wa-progress">
                        <div class="progress-label">
                            <span class="label">Usage</span>
                            <span class="value">{{ number_format($creditBalance->getUsagePercentage(), 1) }}%</span>
                        </div>
                        <div class="wa-progress-bar">
                            <div class="wa-progress-fill" style="width: {{ $creditBalance->getUsagePercentage() }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Razorpay Script --}}
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-razorpay', (data) => {
                // Check if Razorpay is loaded
                if (typeof Razorpay !== 'undefined') {
                    const razorpayData = data[0];
                    const options = {
                        key: razorpayData.key,
                        amount: razorpayData.amount * 100,
                        currency: 'INR',
                        name: 'WhatsApp Credits',
                        description: razorpayData.package_name + ' - ' + razorpayData.message_count + ' messages',
                        order_id: razorpayData.order_id,
                        prefill: {
                            name: '{{ auth()->user()->name ?? "" }}',
                            email: '{{ auth()->user()->email ?? "" }}',
                        },
                        theme: {
                            color: '#36e27b'
                        },
                        handler: function (response) {
                            Livewire.dispatch('handle-payment-verify', {
                                razorpayPaymentId: response.razorpay_payment_id,
                                razorpaySignature: response.razorpay_signature,
                                razorpayOrderId: response.razorpay_order_id
                            });
                        },
                        modal: {
                            ondismiss: function() {
                                console.log('Payment modal closed by user');
                            }
                        },
                        
                    };
    
                    const rzp = new Razorpay(options);
                    rzp.open();
                } else {
                    console.error('Razorpay script not loaded');
                }
            });

            // Listen for payment verification completion
            Livewire.on('payment-verified', () => {
                console.log('Payment verification completed');
            });
        });
    </script>

</x-filament-panels::page>
