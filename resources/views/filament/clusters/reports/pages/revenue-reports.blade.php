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
                <div class="text-2xl font-bold text-gray-900 dark:text-white">₹{{ number_format($stats['total_revenue'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total Revenue</div>
                <div class="mt-1 text-xs text-gray-500">{{ $stats['transaction_count'] }} transactions</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-success-600">₹{{ number_format($stats['this_month'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">This Month</div>
                <div class="mt-1 flex items-center justify-center gap-1 text-xs">
                    @if($stats['monthly_change'] > 0)
                        <x-heroicon-m-arrow-trending-up class="h-3 w-3 text-success-500" />
                    @elseif($stats['monthly_change'] < 0)
                        <x-heroicon-m-arrow-trending-down class="h-3 w-3 text-danger-500" />
                    @endif
                    <span class="{{ $stats['monthly_change_color'] === 'success' ? 'text-success-600' : ($stats['monthly_change_color'] === 'danger' ? 'text-danger-600' : 'text-gray-500') }}">
                        {{ abs($stats['monthly_change']) }}% vs last month
                    </span>
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-info-600">₹{{ number_format($stats['today_collection'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Today's Collection</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-purple-600">₹{{ number_format($stats['avg_transaction'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Avg Transaction</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-warning-600">₹{{ number_format($stats['arpm'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">ARPM</div>
                <div class="mt-1 text-xs text-gray-500">Avg Revenue/Member</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-danger-600">₹{{ number_format($stats['pending_payments'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pending Payments</div>
            </x-filament::section>
        </div>

        {{-- Charts are rendered via footer widgets --}}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
