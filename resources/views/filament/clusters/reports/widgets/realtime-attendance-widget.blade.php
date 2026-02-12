<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-phosphor-door-open class="h-5 w-5" />
                Live Gym Activity
                <span class="ml-auto">
                    <span class="relative flex h-3 w-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-success-500"></span>
                    </span>
                </span>
            </div>
        </x-slot>
        
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Currently In -->
            <div class="flex flex-col items-center rounded-lg bg-gradient-to-br from-purple-50 to-purple-100 p-6 dark:from-purple-900/20 dark:to-purple-900/40">
                <div class="mb-2 text-sm font-medium text-purple-700 dark:text-purple-300">Currently In Gym</div>
                <div class="text-5xl font-bold text-purple-600 dark:text-purple-400" wire:poll.5s="refreshAttendance">
                    {{ $currentlyIn }}
                </div>
                <div class="mt-1 text-xs text-purple-600/70 dark:text-purple-400/70">Active Check-ins</div>
            </div>
            
            <!-- Today's Total -->
            <div class="flex flex-col items-center rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 p-6 dark:from-blue-900/20 dark:to-blue-900/40">
                <div class="mb-2 text-sm font-medium text-blue-700 dark:text-blue-300">Today's Total</div>
                <div class="text-5xl font-bold text-blue-600 dark:text-blue-400">
                    {{ $todayTotal }}
                </div>
                <div class="mt-1 text-xs text-blue-600/70 dark:text-blue-400/70">Check-ins</div>
            </div>
            
            <!-- Peak Today -->
            <div class="flex flex-col items-center rounded-lg bg-gradient-to-br from-amber-50 to-amber-100 p-6 dark:from-amber-900/20 dark:to-amber-900/40">
                <div class="mb-2 text-sm font-medium text-amber-700 dark:text-amber-300">Peak Today</div>
                <div class="text-5xl font-bold text-amber-600 dark:text-amber-400">
                    {{ $peakToday }}
                </div>
                <div class="mt-1 text-xs text-amber-600/70 dark:text-amber-400/70">Concurrent</div>
            </div>
        </div>
    </x-filament::section>
    
    @script
    <script>
        // Listen for Laravel Echo events
        if (window.Echo) {
            Echo.channel('{{ $this->getChannelName() }}')
                .listen('AttendanceUpdated', (e) => {
                    @this.refreshAttendance();
                });
        }
    </script>
    @endscript
</x-filament-widgets::widget>
