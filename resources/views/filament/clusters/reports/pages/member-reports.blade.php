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

        {{-- Charts are rendered via footer widgets --}}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
