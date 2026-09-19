<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Form --}}
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        {{-- Summary Stats --}}
        @php $stats = $this->getSummaryStats(); @endphp
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total Members</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-success-600">{{ number_format($stats['active']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Active Members</div>
                <div class="mt-1 text-xs text-success-600">{{ $stats['active_rate'] }}% of total</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-danger-600">{{ number_format($stats['expired']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Expired Members</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-info-600">{{ number_format($stats['with_trainer']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">With Trainer</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-purple-600">{{ $stats['avg_duration_days'] }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Avg Duration (Days)</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-warning-600">{{ $stats['retention_rate'] }}%</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Retention Rate</div>
            </x-filament::section>
        </div>

        {{-- Churn Risk Analysis --}}
        @php $churnStats = $this->getChurnStats(); @endphp
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
             <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-phosphor-warning-circle class="h-5 w-5 text-danger-500" />
                        Churn Risk Indicator
                    </div>
                </x-slot>
                
                <div class="flex flex-col items-center justify-center py-4">
                     <div class="relative flex h-32 w-32 items-center justify-center rounded-full border-8 border-gray-100 dark:border-gray-700">
                        <div class="absolute inset-0 rounded-full border-8 border-danger-500" 
                             style="clip-path: polygon(0 0, 100% 0, 100% {{ $churnStats['predicted_churn_rate'] }}%, 0 {{ $churnStats['predicted_churn_rate'] }}%); opacity: 0.2">
                        </div>
                        <div class="text-center">
                            <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ $churnStats['predicted_churn_rate'] }}%</span>
                            <span class="block text-xs text-gray-500">Predicted Rate</span>
                        </div>
                    </div>
                    <p class="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
                        <strong class="text-danger-600">{{ $churnStats['at_risk_count'] }} members</strong> are at high risk of leaving in the next 30 days.
                    </p>
                </div>
            </x-filament::section>

            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-phosphor-users class="h-5 w-5 text-gray-500" />
                        High Risk Members (Top 5)
                    </div>
                </x-slot>

                 @if(count($churnStats['top_at_risk']) > 0)
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($churnStats['top_at_risk'] as $member)
                            <div class="flex items-center justify-between py-3">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $member->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $member->email }}</div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex flex-col items-end">
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs font-medium text-danger-600">Risk Score: {{ $member->risk_score }}/100</span>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            Last visit: {{ $member->lastAttendance?->check_in_time?->diffForHumans() ?? 'Never' }}
                                        </div>
                                    </div>
                                    <x-filament::button tag="a" size="xs" color="gray" href="{{ $member->memberResourceUrl($member, 'edit') }}">
                                        View
                                    </x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <x-phosphor-check-circle class="mb-3 h-12 w-12 text-success-500" />
                        <p class="text-gray-900 dark:text-white">Great job!</p>
                        <p class="text-sm text-gray-500">No high-risk members detected at this time.</p>
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Charts are rendered via footer widgets --}}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
