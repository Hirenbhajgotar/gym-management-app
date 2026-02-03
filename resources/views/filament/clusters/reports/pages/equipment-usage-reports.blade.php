<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Form --}}
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        {{-- Summary Stats --}}
        @php $stats = $this->getUsageStats(); @endphp
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_checkins']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total Check-ins</div>
            </x-filament::section>
            
            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['avg_daily_traffic']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Avg Daily Traffic</div>
            </x-filament::section>
        </div>

        {{-- Peak Hours --}}
        <x-filament::section>
            <x-slot name="heading">Peak Usage Hours</x-slot>
            
            @if($stats['peak_hours']->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
                    @foreach($stats['peak_hours'] as $peak)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-800">
                            <div class="text-xl font-bold text-primary-600 dark:text-primary-400">
                                {{ \Carbon\Carbon::createFromTime($peak->hour)->format('g A') }}
                            </div>
                            <div class="text-sm text-gray-500">{{ $peak->count }} visits</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center text-gray-500">No data available for this period.</div>
            @endif
        </x-filament::section>
        
        <x-filament::section>
            <x-slot name="heading">Note</x-slot>
            <p class="text-sm text-gray-500">
                Detailed equipment-level usage analytics will be available once equipment tracking sensors are integrated. 
                Currently showing overall facility usage trends.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
