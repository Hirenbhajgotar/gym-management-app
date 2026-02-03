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
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_check_ins']) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total Check-ins</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-info-600">{{ number_format($stats['unique_members']) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Unique Members</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-purple-600">{{ $stats['avg_daily'] }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Avg Daily</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-success-600">{{ number_format($stats['today_check_ins']) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Today</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-primary-600">{{ number_format($stats['currently_in']) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Currently In</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-warning-600">{{ $stats['peak_hour'] }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Peak Hour</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-teal-600">{{ $stats['peak_day'] }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Peak Day</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-orange-600">{{ $stats['avg_duration_minutes'] }}m</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Avg Session</div>
            </x-filament::section>
        </div>

        {{-- Today's Hourly Distribution --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-phosphor-clock class="h-5 w-5 text-gray-500" />
                    Today's Hourly Distribution
                </div>
            </x-slot>

            @php $hourly = $this->getHourlyDistribution(); @endphp
            <div class="flex items-end gap-1">
                @foreach($hourly['hours'] as $index => $hour)
                    @php
                        $count = $hourly['counts'][$index];
                        $maxCount = max($hourly['counts']) ?: 1;
                        $height = $count > 0 ? max(20, round(($count / $maxCount) * 100)) : 8;
                    @endphp
                    <div class="group relative flex flex-1 flex-col items-center">
                        <div 
                            class="w-full rounded-t transition-all {{ $count > 0 ? 'bg-primary-500 hover:bg-primary-600' : 'bg-gray-200 dark:bg-gray-700' }}"
                            style="height: {{ $height }}px"
                        ></div>
                        <span class="mt-1 text-[10px] text-gray-500 dark:text-gray-400 {{ $index % 2 === 0 ? '' : 'hidden md:inline' }}">
                            {{ $hour }}
                        </span>
                        
                        {{-- Tooltip --}}
                        <div class="absolute bottom-full mb-2 hidden rounded bg-gray-900 px-2 py-1 text-xs text-white group-hover:block">
                            {{ $count }} check-ins
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Charts are rendered via footer widgets --}}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
