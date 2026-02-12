<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Gym Health Score
        </x-slot>
        
        @php
            $healthData = $this->getHealthData();
            $score = $healthData['overall_score'];
            $grade = $healthData['grade'];
            $status = $healthData['status'];
            $breakdown = $healthData['breakdown'];
            
            $scoreColor = match($status) {
                'excellent' => 'success',
                'good' => 'info',
                'fair' => 'warning',
                'poor' => 'danger',
                default => 'gray',
            };
        @endphp
        
        <div class="grid gap-6 md:grid-cols-2">
            {{-- Score Gauge --}}
            <div class="flex flex-col items-center justify-center rounded-lg bg-gradient-to-br from-gray-50 to-gray-100 p-8 dark:from-gray-800 dark:to-gray-900">
                <div class="relative h-48 w-48">
                    <svg class="h-full w-full -rotate-90 transform origin-center" viewBox="0 0 192 192">
                        <!-- Background circle -->
                        <circle
                            cx="96"
                            cy="96"
                            r="80"
                            stroke="currentColor"
                            stroke-width="12"
                            fill="none"
                            class="text-gray-200 dark:text-gray-700"
                        />
                        <!-- Progress circle -->
                        <circle
                            cx="96"
                            cy="96"
                            r="80"
                            stroke="currentColor"
                            stroke-width="12"
                            fill="none"
                            stroke-linecap="round"
                            class="text-{{ $scoreColor }}-500"
                            style="stroke-dasharray: {{ 2 * pi() * 80 }}; stroke-dashoffset: {{ 2 * pi() * 80 * (1 - $score / 100) }};"
                        />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <div class="text-5xl font-bold text-{{ $scoreColor }}-600 dark:text-{{ $scoreColor }}-400">
                            {{ $score }}
                        </div>
                        <div class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                            out of 100
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <div class="text-3xl font-bold text-{{ $scoreColor }}-600 dark:text-{{ $scoreColor }}-400">
                        Grade {{ $grade }}
                    </div>
                    <div class="mt-1 text-sm capitalize text-gray-600 dark:text-gray-400">
                        {{ $status }} Health
                    </div>
                </div>
            </div>
            
            {{-- Breakdown --}}
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Performance Breakdown</h3>
                
                @foreach([
                    ['label' => 'Member Retention', 'key' => 'retention', 'weight' => '25%'],
                    ['label' => 'Revenue Growth', 'key' => 'revenue_growth', 'weight' => '25%'],
                    ['label' => 'Lead Conversion', 'key' => 'lead_conversion', 'weight' => '20%'],
                    ['label' => 'Attendance Rate', 'key' => 'attendance', 'weight' => '15%'],
                    ['label' => 'Payment Collection', 'key' => 'payment_collection', 'weight' => '15%'],
                ] as $metric)
                    <div>
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">
                                {{ $metric['label'] }}
                                <span class="text-xs text-gray-500">({{ $metric['weight'] }})</span>
                            </span>
                            <span class="font-semibold text-{{ $scoreColor }}-600 dark:text-{{ $scoreColor }}-400">
                                {{ $breakdown[$metric['key']] }}
                            </span>
                        </div>
                        <x-progress-bar color="{{ $scoreColor }}" :progress="$breakdown[$metric['key']]" > 
                            {{ $breakdown[$metric['key']] }}%
                        </x-progress-bar>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
