<x-filament-panels::page>
    @if($creditBalance && $creditBalance->remaining_credits > 0)
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
                        {{ number_format($creditBalance->remaining_credits) }} credits remaining
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ number_format($creditBalance->total_credits) }} total
                    </span>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700 overflow-hidden">
                    @php
                        $percentage = 100 - ($creditBalance->getUsagePercentage() ?? 0);
                        $colorClass = $percentage < 20 ? 'bg-danger-600' : ($percentage < 50 ? 'bg-warning-500' : 'bg-primary-600');
                    @endphp
                    <div class="{{ $colorClass }} h-3 rounded-full transition-all duration-500 ease-out"
                         style="width: {{ $percentage }}%"></div>
                </div>
                
                <p class="text-xs text-gray-500 dark:text-gray-400 text-right">
                    You have used {{ number_format($creditBalance->getUsagePercentage() ?? 0) }}% of your credits
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
                    'value' => number_format($usageStats['messages_this_month'] ?? 0),
                    'desc' => 'This Month',
                    'icon' => 'heroicon-o-paper-airplane',
                    'color' => 'text-primary-600 dark:text-primary-400',
                    'bg' => 'bg-primary-50 dark:bg-primary-900/20',
                ],
                [
                    'label' => 'Delivered',
                    'value' => number_format($usageStats['delivered'] ?? 0),
                    'desc' => 'Successfully delivered',
                    'icon' => 'heroicon-o-check-circle',
                    'color' => 'text-success-600 dark:text-success-400',
                    'bg' => 'bg-success-50 dark:bg-success-900/20',
                ],
                [
                    'label' => 'Failed',
                    'value' => number_format($usageStats['failed'] ?? 0),
                    'desc' => 'Delivery failed',
                    'icon' => 'heroicon-o-x-circle',
                    'color' => 'text-danger-600 dark:text-danger-400',
                    'bg' => 'bg-danger-50 dark:bg-danger-900/20',
                ],
                [
                    'label' => 'Delivery Rate',
                    'value' => ($usageStats['delivery_rate'] ?? 0) . '%',
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
                <a href="#" 
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

                <a href="{{ route('filament.admin.pages.broadcast', ['tenant' => $this->getTenantSlug()]) }}" 
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

                <button wire:click="mountAction('sendTestMessage')" 
                        class="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-lg dark:hover:shadow-primary-900/10 transition-all duration-300 text-left sm:col-span-2 md:col-span-1 lg:col-span-2">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl group-hover:bg-gray-900 dark:group-hover:bg-gray-700 transition-colors duration-300">
                            <x-heroicon-o-beaker class="w-8 h-8 text-gray-400 dark:text-gray-500 group-hover:text-white transition-colors" />
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-lg text-gray-950 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">Test Integration</h4>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Send a test message to verify your setup</p>
                        </div>
                        <x-heroicon-m-arrow-right class="w-5 h-5 text-gray-400 group-hover:text-primary-500 group-hover:translate-x-1 transition-all duration-300" />
                    </div>
                </button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Recent Messages
            </x-slot>
            <x-slot name="headerEnd">
                <x-filament::link href="#" size="sm" color="gray">
                    View All
                </x-filament::link>
            </x-slot>

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($recentMessages as $message)
                    <div class="py-3 flex justify-between items-center first:pt-0 last:pb-0">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                <x-heroicon-s-user class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $message['recipient'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $message['sent_at']->diffForHumans() }}</p>
                            </div>
                        </div>
                        
                        @php
                            $statusColor = match($message['status']) {
                                'delivered' => 'success',
                                'read' => 'info',
                                'failed' => 'danger',
                                default => 'gray',
                            };
                            $statusIcon = match($message['status']) {
                                'delivered' => 'heroicon-m-check',
                                'read' => 'heroicon-m-check-badge',
                                'failed' => 'heroicon-m-exclamation-circle',
                                default => 'heroicon-m-clock',
                            };
                        @endphp

                        <x-filament::badge :color="$statusColor" :icon="$statusIcon" size="sm">
                            {{ ucfirst($message['status']) }}
                        </x-filament::badge>
                    </div>
                @empty
                    <div class="text-center py-6">
                        <div class="inline-flex items-center justify-center p-3 rounded-full bg-gray-100 dark:bg-gray-800 mb-2">
                             <x-heroicon-o-inbox class="w-6 h-6 text-gray-400" />
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No messages sent yet</p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
