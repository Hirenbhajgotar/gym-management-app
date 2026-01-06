<div>
    <!-- Toast Messages -->
    @livewire('toast')

    <!-- Hero Section -->
    <section class="min-h-screen flex items-center pt-24">
        <div class="container mx-auto px-6">
            <div class="max-w-8xl mx-auto">
                <!-- Profile Header -->
                <div class="text-center mb-12 animate-fade-in">
                    {{-- <div
                        class="w-24 h-24 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-3xl font-bold text-white">{{ substr($user->name, 0, 1) }}</span>
                    </div> --}}
                    <h1 class="text-4xl lg:text-5xl font-bold mb-4">
                        Welcome back,
                        <span class="bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                            {{ $user->name }}
                        </span>
                    </h1>
                    <p class="text-xl text-gray-300">Manage your gym and subscription details</p>
                </div>

                <div class="grid lg:grid-cols-3 gap-8">
                    <!-- Profile Information -->
                    <div class="lg:col-span-1">
                        <div class="feature-card rounded-2xl p-6 mb-6">
                            <h3 class="text-2xl font-bold mb-6">Profile Information</h3>
                            <div class="space-y-4 pb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Name</label>
                                    <p class="text-white">{{ $user->name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                                    <p class="text-white">{{ $user->email }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Phone</label>
                                    <p class="text-white">{{ $user->phone ?? 'Not provided' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Status</label>
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                        {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Dashboard Button -->
                            @php
                                $userGyms = $user->gyms->filter(function ($gym) {
                                    return $gym->pivot->active;
                                });
                                $defaultGym = $userGyms->first();
                                $dashboardUrl = '#';
                                $dashboardText = 'Dashboard';

                                // Check roles first, fallback to pivot role logic
                                if ($user->hasRole('gym_owner') && $defaultGym) {
                                    $dashboardUrl = route('filament.admin.pages.dashboard', [
                                        'tenant' => $defaultGym->slug,
                                    ]);
                                    $dashboardText = 'Admin Dashboard';
                                } elseif ($user->hasAnyRole(['manager', 'receptionist']) && $defaultGym) {
                                    $dashboardUrl = route('filament.staff.pages.dashboard', [
                                        'tenant' => $defaultGym->slug,
                                    ]);
                                    $dashboardText = 'Staff Dashboard';
                                } elseif ($defaultGym) {
                                    // Fallback: check pivot role
                                    $pivotRole = $defaultGym->pivot->role ?? null;

                                    if ($pivotRole === 'gym_owner') {
                                        $dashboardUrl = route('filament.admin.pages.dashboard', [
                                            'tenant' => $defaultGym->slug,
                                        ]);
                                        $dashboardText = 'Admin Dashboard';
                                    } elseif (in_array($pivotRole, ['manager', 'receptionist'])) {
                                        $dashboardUrl = route('filament.staff.pages.dashboard', [
                                            'tenant' => $defaultGym->slug,
                                        ]);
                                        $dashboardText = 'Staff Dashboard';
                                    } else {
                                        $dashboardUrl = '#';
                                        $dashboardText = 'Dashboard';
                                    }
                                }
                            @endphp

                            @if ($dashboardUrl !== '#')
                                <div class="pt-4 border-t border-white/10">
                                    <a href="{{ $dashboardUrl }}"
                                        class="w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 text-sm font-medium group mb-3">
                                        <svg class="w-4 h-4 mr-2 group-hover:scale-110 transition-transform duration-300"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        {{ $dashboardText }}
                                    </a>
                                </div>
                            @endif

                            <!-- Logout Button -->
                            <div class="pt-4 {{ $dashboardUrl !== '#' ? '' : 'border-t border-white/10' }}">
                                <button wire:click="logout" wire:loading.attr="disabled"
                                    class="w-full flex items-center justify-center px-4 py-3 bg-red-500/20 border border-red-500/30 text-red-400 rounded-lg hover:bg-red-500/30 hover:border-red-500/50 transition-all duration-300 text-sm font-medium group disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.flex wire:target="logout"
                                        class="hidden-- w-full flex items-center justify-center">
                                        <svg class="w-4 h-4 animate-spin mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                            </path>
                                        </svg>
                                        Logging out...
                                    </span>
                                    <span wire:loading.remove wire:target="logout"
                                        class="w-full flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2 group-hover:rotate-12 transition-transform duration-300"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        Logout
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Gym Information -->
                        @if ($user->gyms->count() > 0)
                            <div class="feature-card rounded-2xl p-6">
                                <h3 class="text-2xl font-bold mb-6">Your Gyms</h3>
                                <div class="space-y-4">
                                    @foreach ($user->gyms as $gym)
                                        <div class="border border-white/10 rounded-lg p-4">
                                            <h4 class="font-semibold text-white">{{ $gym->name }}</h4>
                                            <p class="text-sm text-gray-400">
                                                {{ $gym->address->city->name ?? 'Location not set' }}</p>
                                            {{-- <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        {{ $gym->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ $gym->is_active ? 'Active' : 'Inactive' }}
                                            </span> --}}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Subscription & Plans -->
                    <div class="lg:col-span-2">
                        <!-- Current Subscription Status -->
                        <div class="feature-card rounded-2xl p-6 mb-6">
                            <h3 class="text-2xl font-bold mb-6">Current Subscription</h3>

                            @if ($subscription && $subscription->isActive())
                                <div
                                    class="bg-gradient-to-r from-green-500/20 to-emerald-500/20 border border-green-500/30 rounded-lg p-6 mb-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-xl font-semibold text-green-400">{{ $currentPlan->name }}</h4>
                                        <span
                                            class="px-3 py-1 bg-green-500/20 text-green-400 rounded-full text-sm font-medium">
                                            {{ $subscription->status->value }}
                                        </span>
                                    </div>

                                    <div class="grid md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-400 mb-1">Billing
                                                Cycle</label>
                                            <p class="text-white capitalize">{{ $subscription->billing_cycle }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-400 mb-1">Price</label>
                                            <p class="text-white">{{ $subscription->currency }}
                                                {{ number_format($subscription->price, 2) }}</p>
                                        </div>
                                    </div>

                                    @if ($subscription->isOnTrial())
                                        <div class="bg-purple-500/20 border border-purple-500/30 rounded-lg p-4 mb-4">
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-purple-400 mr-2" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-purple-400 font-medium">Trial Active</span>
                                            </div>
                                            <p class="text-sm text-gray-300 mt-1">{{ $subscription->trialDaysLeft() }}
                                                days remaining</p>
                                        </div>
                                    @else
                                        <div>
                                            <label class="block text-sm font-medium text-gray-400 mb-1">Next Billing
                                                Date</label>
                                            <p class="text-white">
                                                {{ $subscription->current_period_end?->format('M d, Y') }}</p>
                                        </div>
                                    @endif

                                    <div class="flex gap-3">
                                        <button wire:click="renewPlan" wire:loading.attr="disabled"
                                            class="px-4 py-2 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                            <span wire:loading wire:target="renewPlan" class="hidden">
                                                <svg class="w-4 h-4 animate-spin inline mr-2" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                    </path>
                                                </svg>
                                                Renewing...
                                            </span>
                                            <span wire:loading.remove wire:target="renewPlan">
                                                Renew Plan
                                            </span>
                                        </button>
                                        {{-- <a href="{{ route('payments.checkout') }}"
                                            class="px-4 py-2 border border-purple-400 text-purple-400 rounded-lg hover:bg-purple-400 hover:text-white transition-all duration-300 text-sm font-medium">
                                            Update Payment Method
                                        </a> --}}
                                    </div>
                                </div>
                            @elseif($subscription && $subscription->isExpired())
                                <div
                                    class="bg-gradient-to-r from-red-500/20 to-orange-500/20 border border-red-500/30 rounded-lg p-6 mb-6">
                                    <div class="flex items-center mb-4">
                                        <svg class="w-6 h-6 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <h4 class="text-xl font-semibold text-red-400">Subscription Expired</h4>
                                    </div>
                                    <p class="text-gray-300 mb-4">Your subscription has expired. Renew now to continue
                                        using {{config('app.name')}}.</p>
                                    <button wire:click="renewPlan" wire:loading.attr="disabled"
                                        class="px-6 py-3 bg-gradient-to-r from-red-500 to-orange-500 rounded-lg hover:from-red-600 hover:to-orange-600 transition-all duration-300 text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span wire:loading wire:target="renewPlan" class="hidden">
                                            <svg class="w-4 h-4 animate-spin inline mr-2" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                </path>
                                            </svg>
                                            Renewing...
                                        </span>
                                        <span wire:loading.remove wire:target="renewPlan">
                                            Renew Subscription
                                        </span>
                                    </button>
                                </div>
                            @else
                                <div
                                    class="bg-gradient-to-r from-yellow-500/20 to-orange-500/20 border border-yellow-500/30 rounded-lg p-6 mb-6">
                                    <div class="flex items-center mb-4">
                                        <svg class="w-6 h-6 text-yellow-400 mr-3" fill="currentColor"
                                            viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <h4 class="text-xl font-semibold text-yellow-400">No Active Subscription</h4>
                                    </div>
                                    <p class="text-gray-300 mb-4">You don't have an active subscription. Choose a plan
                                        to get started.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Payment History -->
                        <div class="feature-card rounded-2xl p-6 mb-6">
                            <h3 class="text-2xl font-bold mb-6">Payment History</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="text-gray-400 text-sm border-b border-white/10">
                                            <th class="pb-3">Date</th>
                                            <th class="pb-3">Plan</th>
                                            <th class="pb-3">Type</th>
                                            <th class="pb-3">Amount</th>
                                            <th class="pb-3">Status</th>
                                            <th class="pb-3 text-right">Invoice</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/10">
                                        @forelse ($latestPayments as $payment)
                                            <tr class="text-sm">
                                                <td class="py-4 text-white">{{ $payment->created_at->format('M d, Y') }}</td>
                                                <td class="py-4 text-white">{{ $payment->metadata['plan_name'] ?? '' }}</td>
                                                <td class="py-4 text-gray-300">
                                                    @if($payment->payment_type == 'whatsapp_package')
                                                        WhatsApp Package
                                                    @else
                                                        {{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}
                                                    @endif
                                                </td>
                                                <td class="py-4 text-white">
                                                    {{ $payment->formatted_amount }}
                                                </td>
                                                <td class="py-4">
                                                    <span class="px-2 py-1 rounded text-xs font-medium
                                                        {{ $payment->status->value === 'completed' ? 'bg-green-500/20 text-green-400' : 
                                                           ($payment->status->value === 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400') }}">
                                                        {{ ucfirst($payment->status->value) }}
                                                    </span>
                                                </td>
                                                <td class="py-4 text-right">
                                                    @if($payment->status->value === 'completed')
                                                        <button wire:click="downloadInvoice({{ $payment->id }})" 
                                                                wire:loading.attr="disabled"
                                                                class="text-purple-400 hover:text-purple-300 transition-colors">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                                            </svg>
                                                        </button>
                                                    @else
                                                        <span class="text-gray-600">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="py-4 text-center text-gray-500">No payment history found</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Available Plans -->
                        <div class="feature-card rounded-2xl p-6">
                            <h3 class="text-2xl font-bold mb-6">Available Plans</h3>
                            <div class="grid md:grid-cols-2 gap-6">
                                @foreach ($allPlans as $plan)
                                    <div
                                        class="border border-white/10 rounded-xl p-6 hover:border-purple-400/50 transition-all duration-300">
                                        @if (!empty($plan->badge))
                                            <div class="flex justify-center mb-4">
                                                <span
                                                    class="bg-gradient-to-r from-purple-500 to-cyan-500 px-3 py-1 rounded-full text-sm font-semibold">
                                                    {{ $plan->badge }}
                                                </span>
                                            </div>
                                        @endif

                                        <h4 class="text-xl font-bold mb-2">{{ $plan->name }}</h4>

                                        @if ($plan->isFree())
                                            <div class="mb-4">
                                                <span class="text-3xl font-bold text-green-400">Free</span>
                                            </div>
                                        @else
                                            <div class="mb-4">
                                                <span class="text-3xl font-bold">
                                                    {{ $plan->currency->getSymbol() }}{{ number_format($plan->price_yearly) }}
                                                </span>
                                                <span class="text-gray-400">/Year</span>
                                            </div>
                                        @endif

                                        @if ($plan->hasTrial())
                                            <div class="mb-4 text-sm text-green-400 font-semibold">
                                                {{ $plan->trial_days }} days free trial
                                            </div>
                                        @endif

                                        <p class="text-gray-300 text-sm mb-4">{{ $plan->brief_description }}</p>

                                        <div class="space-y-2 mb-6">
                                            @foreach ($plan->features->take(3) as $feature)
                                                <div class="flex items-center text-sm">
                                                    <svg class="w-4 h-4 text-green-400 mr-2 flex-shrink-0"
                                                        fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="text-gray-300">{!! $feature->brief_description !!}</span>
                                                </div>
                                            @endforeach
                                        </div>

                                        @if ($currentPlan && $currentPlan->id === $plan->id)
                                            @if ($subscription->isOnTrial())
                                                <button wire:click="renewPlan" wire:loading.attr="disabled"
                                                    class="w-full px-4 py-2 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                                    <span wire:loading wire:target="renewPlan" class="hidden">
                                                        <svg class="w-4 h-4 animate-spin inline mr-2" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                            </path>
                                                        </svg>
                                                        Active Trial...
                                                    </span>
                                                    <span wire:loading.remove wire:target="renewPlan">
                                                        Active Trial
                                                    </span>
                                                </button>
                                            @else
                                                <button
                                                    class="w-full py-2 px-4 bg-gray-600 text-gray-300 rounded-lg cursor-not-allowed text-sm font-medium"
                                                    disabled>
                                                    Current Plan
                                                </button>
                                            @endif
                                        @elseif($subscription && $subscription->isActive())
                                            <button wire:click="upgradePlan({{ $plan->id }})"
                                                wire:loading.attr="disabled"
                                                class="w-full py-2 px-4 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg hover:from-green-600 hover:to-emerald-600 transition-all duration-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                                <span wire:loading wire:target="upgradePlan({{ $plan->id }})"
                                                    class="hidden">
                                                    <svg class="w-4 h-4 animate-spin inline mr-2" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                        </path>
                                                    </svg>
                                                    Upgrading...
                                                </span>
                                                <span wire:loading.remove
                                                    wire:target="upgradePlan({{ $plan->id }})">
                                                    Upgrade Plan
                                                </span>
                                            </button>
                                        @else
                                            @php
                                                $buttonText = 'Choose Plan';
                                                if ($plan->isFree()) {
                                                    $buttonText = 'Get Started';
                                                } elseif ($plan->hasTrial() && !$user->hasUsedTrial()) {
                                                    $buttonText = 'Start Free Trial';
                                                }
                                            @endphp
                                            <button wire:click="selectPlan({{ $plan->id }})"
                                                wire:loading.attr="disabled"
                                                class="w-full py-2 px-4 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-lg hover:from-purple-600 hover:to-cyan-600 transition-all duration-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                                <span wire:loading wire:target="selectPlan({{ $plan->id }})"
                                                    class="hidden">
                                                    <svg class="w-4 h-4 animate-spin inline mr-2" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                        </path>
                                                    </svg>
                                                    Processing...
                                                </span>
                                                <span wire:loading.remove
                                                    wire:target="selectPlan({{ $plan->id }})">
                                                    {{ $buttonText }}
                                                </span>
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
