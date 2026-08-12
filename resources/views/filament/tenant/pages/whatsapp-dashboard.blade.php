<x-filament-panels::page>
    @unless($hasWhatsAppSetup)
        <x-filament::section class="border-l-4 border-l-danger-500">
            <div class="flex items-start gap-4">
                <div class="p-2 bg-danger-100 dark:bg-danger-900/30 rounded-full">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-danger-600 dark:text-danger-500" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-medium text-gray-950 dark:text-white">WhatsApp Account Not Configured</h3>
                    <p class="text-gray-600 dark:text-gray-400 mt-1 mb-4">
                        You need to set up your WhatsApp account before you can send messages or run campaigns. Complete the setup to connect and configure your WhatsApp Business API.
                    </p>
                    <x-filament::button
                        tag="a"
                        href="{{ route('filament.admin.pages.whats-app-setup', ['tenant' => $this->getTenantSlug()]) }}"
                        color="success"
                        icon="heroicon-m-cog-6-tooth"
                    >
                        Setup WhatsApp Account
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endunless

    @if($this->whatsappAccount?->isSelfManaged())
        <x-filament::section class="border-l-4 border-l-primary-500">
            <div class="flex items-start gap-4">
                <div class="p-2 bg-primary-100 dark:bg-primary-900/30 rounded-full">
                    <x-heroicon-o-information-circle class="w-6 h-6 text-primary-600 dark:text-primary-500" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-medium text-gray-950 dark:text-white">Credits Managed by Meta</h3>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        You're using Self Managed billing. Meta WhatsApp charges are handled directly by you — no credits are deducted from this app.
                    </p>
                </div>
            </div>
        </x-filament::section>
    @elseif($this->creditBalance && $this->creditBalance->remaining_credits > 0)
        <x-filament::section>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">Message Credits</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Monitor your WhatsApp message credit usage</p>
                </div>
                <x-filament::button
                    tag="a"
                    href="{{ route('filament.admin.pages.purchase-credits', ['tenant' => $this->getTenantSlug()]) }}"
                    icon="heroicon-m-shopping-cart"
                >
                    Purchase More
                </x-filament::button>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-200">
                        {{ number_format($this->creditBalance->remaining_credits) }} credits remaining
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ number_format($this->creditBalance->total_credits) }} total
                    </span>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700 overflow-hidden">
                    @php
                        $percentage = 100 - ($this->creditBalance->getUsagePercentage() ?? 0);
                        $colorClass = $percentage < 20 ? 'bg-danger-600' : ($percentage < 50 ? 'bg-warning-500' : 'bg-primary-600');
                    @endphp
                    <div class="{{ $colorClass }} h-3 rounded-full transition-all duration-500 ease-out"
                         style="width: {{ $percentage }}%"></div>
                </div>
                
                <p class="text-xs text-gray-500 dark:text-gray-400 text-right">
                    You have used {{ number_format($this->creditBalance->getUsagePercentage() ?? 0) }}% of your credits
                </p>
            </div>
        </x-filament::section>
    @else
        <x-filament::section class="border-l-4 border-l-warning-500">
            <div class="flex items-start gap-4">
                <div class="p-2 bg-warning-100 dark:bg-warning-900/30 rounded-full">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-warning-600 dark:text-warning-500" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-medium text-gray-950 dark:text-white">No Credits Available</h3>
                    <p class="text-gray-600 dark:text-gray-400 mt-1 mb-4">
                        You need to purchase message credits to start sending WhatsApp messages and campaigns.
                    </p>
                    <x-filament::button
                        tag="a"
                        href="{{ route('filament.admin.pages.purchase-credits', ['tenant' => $this->getTenantSlug()]) }}"
                        color="warning"
                        icon="heroicon-m-shopping-cart"
                    >
                        Purchase Credits
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $stats = [
                [
                    'label' => 'Messages Sent',
                    'value' => number_format($this->usageStats['messages_this_month'] ?? 0),
                    'desc' => 'This Month',
                    'icon' => 'heroicon-o-paper-airplane',
                    'color' => 'text-primary-600 dark:text-primary-400',
                    'bg' => 'bg-primary-50 dark:bg-primary-900/20',
                ],
                [
                    'label' => 'Delivered',
                    'value' => number_format($this->usageStats['delivered'] ?? 0),
                    'desc' => 'Successfully delivered',
                    'icon' => 'heroicon-o-check-circle',
                    'color' => 'text-success-600 dark:text-success-400',
                    'bg' => 'bg-success-50 dark:bg-success-900/20',
                ],
                [
                    'label' => 'Failed',
                    'value' => number_format($this->usageStats['failed'] ?? 0),
                    'desc' => 'Delivery failed',
                    'icon' => 'heroicon-o-x-circle',
                    'color' => 'text-danger-600 dark:text-danger-400',
                    'bg' => 'bg-danger-50 dark:bg-danger-900/20',
                ],
                [
                    'label' => 'Delivery Rate',
                    'value' => ($this->usageStats['delivery_rate'] ?? 0) . '%',
                    'desc' => 'Performance',
                    'icon' => 'heroicon-o-chart-bar',
                    'color' => 'text-info-600 dark:text-info-400',
                    'bg' => 'bg-info-50 dark:bg-info-900/20',
                ],
            ];
        @endphp

        @foreach($stats as $stat)
            <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $stat['bg'] }}">
                        <x-icon name="{{ $stat['icon'] }}" class="h-6 w-6 {{ $stat['color'] }}" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="text-3xl font-semibold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 pl-14">{{ $stat['desc'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-filament::section>
            <x-slot name="heading">
                Quick Actions
            </x-slot>
            <x-slot name="description">
                Manage your WhatsApp communications
            </x-slot>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="{{ route('filament.admin.resources.whats-app-templates.index', ['tenant' => $this->getTenantSlug()]) }}" wire:navigate 
                   class="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-lg dark:hover:shadow-primary-900/10 transition-all duration-300">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 bg-primary-50 dark:bg-primary-900/20 rounded-xl group-hover:bg-primary-600 group-hover:text-white transition-colors duration-300">
                            <x-heroicon-o-document-text class="w-8 h-8 text-primary-600 dark:text-primary-400 group-hover:text-white transition-colors" />
                        </div>
                        <x-heroicon-m-arrow-right class="w-5 h-5 text-gray-400 group-hover:text-primary-500 group-hover:translate-x-1 transition-all duration-300" />
                    </div>
                    <div>
                        <h4 class="font-bold text-lg text-gray-950 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">Templates</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">Create and manage your WhatsApp message templates</p>
                    </div>
                </a>

                <a href="{{ route('filament.admin.pages.broadcast', ['tenant' => $this->getTenantSlug()]) }}" wire:navigate
                   class="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-lg dark:hover:shadow-primary-900/10 transition-all duration-300">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 bg-primary-50 dark:bg-primary-900/20 rounded-xl group-hover:bg-primary-600 group-hover:text-white transition-colors duration-300">
                            <x-heroicon-o-megaphone class="w-8 h-8 text-primary-600 dark:text-primary-400 group-hover:text-white transition-colors" />
                        </div>
                        <x-heroicon-m-arrow-right class="w-5 h-5 text-gray-400 group-hover:text-primary-500 group-hover:translate-x-1 transition-all duration-300" />
                    </div>
                    <div>
                        <h4 class="font-bold text-lg text-gray-950 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">Broadcast</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">Send extensive campaigns to multiple recipients</p>
                    </div>
                </a>
            </div>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
