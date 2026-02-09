<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats --}}
        @php $stats = $this->getSummaryStats(); @endphp
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_trainers']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total Trainers</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-success-600">{{ number_format($stats['active_trainers']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Active Trainers</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-info-600">{{ number_format($stats['members_with_trainer']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Members with Trainer</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-purple-600">{{ $stats['avg_clients'] }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Avg Clients/Trainer</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-warning-600">{{ $stats['utilization_rate'] }}%</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Utilization Rate</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-primary-600">{{ $stats['member_coverage'] }}%</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Member Coverage</div>
            </x-filament::section>
        </div>

        {{-- Top Trainer Highlight --}}
        @if($stats['top_trainer_name'] !== 'N/A')
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-phosphor-trophy class="h-5 w-5 text-warning-500" />
                        Top Performer
                    </div>
                </x-slot>

                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-900/30">
                        <x-phosphor-barbell class="h-6 w-6 text-warning-600" />
                    </div>
                    <div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $stats['top_trainer_name'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $stats['top_trainer_clients'] }} active clients</div>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Trainer Distribution Table --}}
        <x-filament::section>
            <x-slot name="heading">Client Distribution by Trainer</x-slot>

            @php $distribution = $this->getTrainerDistribution(); @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-3 text-sm font-semibold text-gray-900 dark:text-white">Trainer</th>
                            <th class="py-3 text-sm font-semibold text-gray-900 dark:text-white">Specialization</th>
                            <th class="py-3 text-sm font-semibold text-gray-900 dark:text-white">Active Clients</th>
                            <th class="py-3 text-sm font-semibold text-gray-900 dark:text-white">Load</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($distribution as $trainer)
                            <tr>
                                <td class="py-3 text-sm text-gray-700 dark:text-gray-300">{{ $trainer['name'] }}</td>
                                <td class="py-3 text-sm text-gray-500 dark:text-gray-400">{{ $trainer['specialization'] }}</td>
                                <td class="py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $trainer['clients'] }}</td>
                                <td class="py-3">
                                    @php
                                        $maxClients = max(array_column($distribution, 'clients')) ?: 1;
                                        $percentage = round(($trainer['clients'] / $maxClients) * 100);
                                    @endphp
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-2 rounded-full bg-primary-500" style="width: {{ $percentage }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ $percentage }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-sm text-gray-500">No trainers found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Charts are rendered via footer widgets --}}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
