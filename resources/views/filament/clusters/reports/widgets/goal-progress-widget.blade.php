<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-phosphor-target class="h-5 w-5 text-primary-500" />
                Goals Progress
            </div>
        </x-slot>

        <x-slot name="headerEnd">
            <a href="{{ \App\Filament\Admin\Resources\ReportGoalResource::getUrl('index') }}"
               class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                Manage Goals
            </a>
        </x-slot>

        @php $goals = $this->getActiveGoals(); @endphp

        @if(count($goals) === 0)
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <x-phosphor-target class="mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No active goals set</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Create goals to track your gym's performance</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($goals as $goal)
                    <!-- Debug: Color={{ $goal['color'] }}, Progress={{ $goal['progress'] }} -->
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        {{-- Header --}}
                        <div class="mb-3 flex items-start justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">
                                    {{ $goal['metric_label'] }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $goal['period_start'] }} - {{ $goal['period_end'] }}
                                </p>
                            </div>
                            <x-filament::badge color="{{ $goal['color'] }}">
                                {{ ucfirst(str_replace('_', ' ', $goal['status'])) }}
                            </x-filament::badge>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="mb-2">
                             <x-progress-bar 
                                :progress="$goal['progress']" 
                                :color="$goal['color']" 
                            />
                        </div>

                        {{-- Stats --}}
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    @if($goal['metric_type'] === 'revenue')
                                        ₹{{ number_format($goal['current'], 0) }}
                                    @elseif($goal['metric_type'] === 'retention')
                                        {{ $goal['current'] }}%
                                    @else
                                        {{ number_format($goal['current'], 0) }}
                                    @endif
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    / 
                                    @if($goal['metric_type'] === 'revenue')
                                        ₹{{ number_format($goal['target'], 0) }}
                                    @elseif($goal['metric_type'] === 'retention')
                                        {{ $goal['target'] }}%
                                    @else
                                        {{ number_format($goal['target'], 0) }}
                                    @endif
                                </span>
                            </div>
                            <span class="font-medium" style="{{ $goal['progress'] >= 100 ? 'color: #16a34a;' : '' }}">
                                {{ $goal['progress'] }}%
                            </span>
                        </div>

                        {{-- Days Remaining --}}
                        @if($goal['days_left'] > 0 && $goal['progress'] < 100)
                            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                <x-phosphor-clock class="inline h-3 w-3" />
                                {{ $goal['days_left'] }} days remaining
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
