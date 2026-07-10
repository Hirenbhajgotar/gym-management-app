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
                        $bgColor = match($budget['color']) {
                            'success' => 'rgb(34 197 94)',   // green-500
                            'info' => 'rgb(59 130 246)',     // blue-500
                            'warning' => 'rgb(245 158 11)',  // amber-500
                            'danger' => 'rgb(239 68 68)',    // red-500
                            default => 'rgb(107 114 128)',   // gray-500
                        };
                        
                        $textColor = match($budget['color']) {
                            'success' => 'rgb(22 163 74)',    // green-600
                            'info' => 'rgb(37 99 235)',       // blue-600
                            'warning' => 'rgb(217 119 6)',    // amber-600
                            'danger' => 'rgb(220 38 38)',     // red-600
                            default => 'rgb(75 85 99)',       // gray-600
                        };
                        
                        $badgeBgColor = match($budget['color']) {
                            'success' => 'rgb(240 253 244)',  // green-50
                            'info' => 'rgb(239 246 255)',     // blue-50
                            'warning' => 'rgb(255 251 235)',  // amber-50
                            'danger' => 'rgb(254 242 242)',   // red-50
                            default => 'rgb(249 250 251)',    // gray-50
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
                            <span 
                                class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                style="color: {{ $textColor }}; background-color: {{ $badgeBgColor }}"
                            >
                                {{ $budget['percentage'] }}%
                            </span>
                        </div>
                        
                        <div class="mb-2">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600 dark:text-gray-400">
                                    Spent: {{ Number::currency((float) $budget['spent'], in: $budget['currency']) }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-500">
                                    of {{ Number::currency((float) $budget['amount'], in: $budget['currency']) }}
                                </span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700">
                                <div 
                                    class="h-full transition-all duration-300"
                                    style="width: {{ min($budget['percentage'], 100) }}%; background-color: {{ $bgColor }}"
                                ></div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center text-xs">
                            <span class="{{ $budget['remaining'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                @if($budget['remaining'] >= 0)
                                    {{ Number::currency((float) $budget['remaining'], in: $budget['currency']) }} remaining
                                @else
                                    {{ Number::currency(abs((float) $budget['remaining']), in: $budget['currency']) }} over budget
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
