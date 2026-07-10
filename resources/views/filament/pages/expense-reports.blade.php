<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Expenses</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ Number::currency($this->totalAmount, in: 'INR') }}
                </p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Number of Expenses</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $this->expenses->count() }}
                </p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Categories Used</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $this->categoryBreakdown->count() }}
                </p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Average per Expense</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    @if($this->expenses->count() > 0)
                        {{ Number::currency($this->totalAmount / $this->expenses->count(), in: 'INR') }}
                    @else
                        ₹0
                    @endif
                </p>
            </div>
        </x-filament::section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Category Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="phosphor-chart-pie" class="h-5 w-5" />
                    Expenses by Category
                </div>
            </x-slot>

            @if($this->categoryBreakdown->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                    No expenses found for the selected filters.
                </p>
            @else
                <div class="space-y-3">
                    @foreach($this->categoryBreakdown as $category)
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $category['name'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ Number::currency($category['total'], in: 'INR') }}
                                    ({{ $category['percentage'] }}%)
                                </span>
                            </div>
                            <x-progress-bar 
                                    :progress="$category['percentage']" 
                                    :color="$category['color']" 
                                    height="h-2" 
                                />
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Payment Method Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="phosphor-wallet" class="h-5 w-5" />
                    Expenses by Payment Method
                </div>
            </x-slot>

            @if($this->paymentMethodBreakdown->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                    No expenses found for the selected filters.
                </p>
            @else
                <div class="space-y-3">
                    @foreach($this->paymentMethodBreakdown as $method)
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ $method['name'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                    ({{ $method['count'] }} expenses)
                                </span>
                            </div>
                            <span class="font-bold text-gray-900 dark:text-white">
                                {{ Number::currency($method['total'], in: 'INR') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Monthly Trend --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="phosphor-chart-line-up" class="h-5 w-5" />
                Monthly Expense Trend
            </div>
        </x-slot>

        @if($this->monthlyTrend->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                No expenses found for the selected filters.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-4 text-left font-medium text-gray-500 dark:text-gray-400">Month</th>
                            <th class="py-2 px-4 text-right font-medium text-gray-500 dark:text-gray-400">Expenses</th>
                            <th class="py-2 px-4 text-right font-medium text-gray-500 dark:text-gray-400">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->monthlyTrend as $month)
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="py-3 px-4 text-gray-900 dark:text-white">{{ $month['month'] }}</td>
                                <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">{{ $month['count'] }}</td>
                                <td class="py-3 px-4 text-right font-medium text-gray-900 dark:text-white">
                                    {{ Number::currency($month['total'], in: 'INR') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 dark:bg-gray-800 font-bold">
                            <td class="py-3 px-4 text-gray-900 dark:text-white">Total</td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ $this->expenses->count() }}</td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-white">
                                {{ Number::currency($this->totalAmount, in: 'INR') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- Recent Expenses Table --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="phosphor-list" class="h-5 w-5" />
                Expense Details ({{ $this->expenses->count() }} records)
            </div>
        </x-slot>

        @if($this->expenses->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                No expenses found for the selected filters.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="py-2 px-3 text-left font-medium text-gray-500 dark:text-gray-400">Date</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-500 dark:text-gray-400">Title</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-500 dark:text-gray-400">Category</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-500 dark:text-gray-400">Submitted By</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-500 dark:text-gray-400">Method</th>
                            <th class="py-2 px-3 text-right font-medium text-gray-500 dark:text-gray-400">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->expenses->take(50) as $expense)
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                                    {{ $expense->expense_date->format('d M Y') }}
                                </td>
                                <td class="py-2 px-3 text-gray-900 dark:text-white font-medium">
                                    {{ Str::limit($expense->title, 40) }}
                                </td>
                                <td class="py-2 px-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-{{ $expense->category?->color ?? 'gray' }}-100 text-{{ $expense->category?->color ?? 'gray' }}-700 dark:bg-{{ $expense->category?->color ?? 'gray' }}-900/20 dark:text-{{ $expense->category?->color ?? 'gray' }}-400">
                                        {{ $expense->category?->name ?? 'Uncategorized' }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                                    {{ $expense->user?->name ?? '-' }}
                                </td>
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                                    {{ $expense->payment_method?->getLabel() ?? '-' }}
                                </td>
                                <td class="py-2 px-3 text-right font-medium text-gray-900 dark:text-white">
                                    {{ Number::currency((float) $expense->amount, in: $expense->currency) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                
                @if($this->expenses->count() > 50)
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-3">
                        Showing first 50 of {{ $this->expenses->count() }} expenses. Export to Excel to see all.
                    </p>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
