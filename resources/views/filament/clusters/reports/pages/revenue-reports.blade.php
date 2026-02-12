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

        {{-- Financial Summary & P&L Section --}}
        @php $pnl = $this->getPnLData(); @endphp
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-danger-600">₹{{ number_format($pnl['totals']['expenses']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total Expenses</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">₹{{ number_format($pnl['totals']['net_profit']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Net Profit</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $pnl['totals']['margin'] }}%</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Profit Margin</div>
            </x-filament::section>
        </div>

        {{-- Detailed Breakdown Table --}}
        <x-filament::section>
            <x-slot name="heading">
                Monthly Financial Breakdown
            </x-slot>


            <div class="overflow-x-auto rounded-lg">
                <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">Period</th>
                            <th scope="col" class="px-6 py-3 text-right">Revenue</th>
                            <th scope="col" class="px-6 py-3 text-right">Expenses</th>
                            <th scope="col" class="px-6 py-3 text-right">Net Profit</th>
                            <th scope="col" class="px-6 py-3 text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($pnl['breakdown'] as $row)
                            <tr class="bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800">
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900 dark:text-white">
                                    {{ $row['label'] }}
                                </td>
                                <td class="px-6 py-4 text-right text-success-600 dark:text-success-400">
                                    ₹{{ number_format($row['revenue']) }}
                                </td>
                                <td class="px-6 py-4 text-right text-danger-600 dark:text-danger-400">
                                    ₹{{ number_format($row['expenses']) }}
                                </td>
                                <td class="px-6 py-4 text-right font-semibold {{ $row['net_profit'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                                    ₹{{ number_format($row['net_profit']) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end">
                                        <span @class([
                                            'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            'bg-success-100 text-success-800 dark:bg-success-900/20 dark:text-success-300' => $row['margin'] >= 20,
                                            'bg-warning-100 text-warning-800 dark:bg-warning-900/20 dark:text-warning-300' => $row['margin'] >= 0 && $row['margin'] < 20,
                                            'bg-danger-100 text-danger-800 dark:bg-danger-900/20 dark:text-danger-300' => $row['margin'] < 0,
                                        ])>
                                            {{ $row['margin'] }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
