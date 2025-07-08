<x-filament-panels::page>
    <div class="filament-widget-grid">
        <div class="grid grid-cols-12 gap-6">
            <!-- Server Metrics - Full width -->
            <div class="col-span-12">
                @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseServers::class)
            </div>

            <!-- Usage and Cache - Half width each -->
            <div class="col-span-12 md:col-span-6">
                @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseUsage::class)
            </div>
            <div class="col-span-12 md:col-span-6">
                @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseCache::class)
            </div>

            <!-- Exceptions and Queues - Half width each -->
            <div class="col-span-12 md:col-span-6">
                @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseExceptions::class)
            </div>
            <div class="col-span-12 md:col-span-6">
                @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseQueues::class)
            </div>

            <!-- Slow Queries - Third width -->
            <div class="col-span-12 md:col-span-4">
                @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseSlowQueries::class)
            </div>
            <div class="col-span-12 md:col-span-4">
                @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseSlowRequests::class)
            </div>
            <div class="col-span-12 md:col-span-4">
                @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseSlowOutGoingRequests::class)
            </div>
        </div>
    </div>
</x-filament-panels::page>
