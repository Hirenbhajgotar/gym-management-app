<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon 
                    icon="phosphor-chart-pie-slice" 
                    class="h-5 w-5 text-gray-500 dark:text-gray-400"
                />
                Budget Overview
            </div>
        </x-slot>

        @php
            $budgets = $this->getBudgets();
        @endphp

        @if($budgets->isEmpty())
            <div class="text-center py-6">
                <x-filament::icon 
                    icon="phosphor-chart-pie-slice" 
                    class="h-12 w-12 mx-auto text-gray-400 dark:text-gray-500 mb-3"
                />
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No active budgets for the current period.
                </p>
                <a 
                    href="{{ route('filament.admin.resources.expense-budgets.create', ['tenant' => \Filament\Facades\Filament::getTenant()->slug]) }}"
                    class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-500 mt-2"
                >
                    <x-filament::icon icon="phosphor-plus" class="h-4 w-4" />
                    Create a budget
                </a>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($budgets as $budget)
                    @php
                        $colorClasses = match($budget['color']) {
                            'success' => 'bg-success-500',
                            'info' => 'bg-info-500',
                            'warning' => 'bg-warning-500',
                            'danger' => 'bg-danger-500',
                            default => 'bg-gray-500',
                        };
                        
                        $textColorClasses = match($budget['color']) {
                            'success' => 'text-success-600',
                            'info' => 'text-info-600',
                            'warning' => 'text-warning-600',
                            'danger' => 'text-danger-600',
                            default => 'text-gray-600',
                        };
                    @endphp
                    
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $budget['name'] }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $budget['category'] }} • {{ $budget['period'] }}
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $textColorClasses }} bg-{{ $budget['color'] }}-50 dark:bg-{{ $budget['color'] }}-900/20">
                                {{ $budget['percentage'] }}%
                            </span>
                        </div>
                        
                        <div class="mb-2">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600 dark:text-gray-400">
                                    Spent: {{ Number::currency($budget['spent'], in: $budget['currency']) }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-500">
                                    of {{ Number::currency($budget['amount'], in: $budget['currency']) }}
                                </span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700">
                                <div 
                                    class="h-full {{ $colorClasses }} transition-all duration-300"
                                    style="width: {{ min($budget['percentage'], 100) }}%"
                                ></div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center text-xs">
                            <span class="{{ $budget['remaining'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                @if($budget['remaining'] >= 0)
                                    {{ Number::currency($budget['remaining'], in: $budget['currency']) }} remaining
                                @else
                                    {{ Number::currency(abs($budget['remaining']), in: $budget['currency']) }} over budget
                                @endif
                            </span>
                            <span class="text-gray-400 dark:text-gray-500">
                                Ends {{ $budget['end_date'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
