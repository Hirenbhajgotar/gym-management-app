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
                <div class="text-2xl font-bold text-gray-900 dark:text-white">₹{{ number_format($stats['total_expenses'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total Expenses</div>
                <div class="mt-1 text-xs text-gray-500">{{ $stats['transaction_count'] }} transactions</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-danger-600">₹{{ number_format($stats['this_month'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">This Month</div>
                <div class="mt-1 flex items-center justify-center gap-1 text-xs">
                    @if($stats['monthly_change'] > 0)
                        <x-heroicon-m-arrow-trending-up class="h-3 w-3 text-danger-500" />
                        <span class="text-danger-600">+{{ $stats['monthly_change'] }}%</span>
                    @elseif($stats['monthly_change'] < 0)
                        <x-heroicon-m-arrow-trending-down class="h-3 w-3 text-success-500" />
                        <span class="text-success-600">{{ $stats['monthly_change'] }}%</span>
                    @else
                        <span class="text-gray-500">0% change</span>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-info-600">₹{{ number_format($stats['last_month'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Last Month</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-purple-600">₹{{ number_format($stats['avg_expense'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Avg Expense</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-warning-600">₹{{ number_format($stats['pending_amount'], 0) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pending Approval</div>
                <div class="mt-1 text-xs text-gray-500">{{ $stats['pending_count'] }} items</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-teal-600 truncate" title="{{ $stats['top_category'] }}">{{ Str::limit($stats['top_category'], 12) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Top Category</div>
            </x-filament::section>
        </div>

        {{-- Category Breakdown Table --}}
        @php $breakdown = $this->categoryBreakdown; @endphp
        @if($breakdown->count() > 0)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-phosphor-folder class="h-5 w-5 text-gray-500" />
                        Category Breakdown
                    </div>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="py-3 text-sm font-semibold text-gray-900 dark:text-white">Category</th>
                                <th class="py-3 text-sm font-semibold text-gray-900 dark:text-white text-right">Count</th>
                                <th class="py-3 text-sm font-semibold text-gray-900 dark:text-white text-right">Total</th>
                                <th class="py-3 text-sm font-semibold text-gray-900 dark:text-white">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($breakdown as $category)
                                <tr>
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-3 w-3 rounded-full" style="background-color: {{ $category['color'] }}"></div>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $category['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-right text-sm text-gray-500 dark:text-gray-400">{{ $category['count'] }}</td>
                                    <td class="py-3 text-right text-sm font-medium text-gray-900 dark:text-white">₹{{ number_format($category['total'], 0) }}</td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-24 rounded-full bg-gray-200 dark:bg-gray-700">
                                                <div class="h-2 rounded-full bg-primary-500" style="width: {{ min($category['percentage'], 100) }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-500">{{ $category['percentage'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Charts are rendered via footer widgets --}}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
