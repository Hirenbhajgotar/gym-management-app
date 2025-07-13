<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseServers::class)
        </div>

        <!-- Usage, Cache, Requests - One-third width each -->
        <div class="col-span-12 md:col-span-4 row-span-2">
            @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseUsage::class)
        </div>
        <div class="col-span-12 md:col-span-8">
            @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseSlowRequests::class)
        </div>
        <div class="col-span-12 md:col-span-8">
            @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseQueues::class)
        </div>


        <!-- Exceptions and Queues - Half width each -->
        <div class="col-span-12 md:col-span-6">
            @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseExceptions::class)
        </div>
        <div class="col-span-12 md:col-span-6">
            @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseCache::class)
        </div>

        <!-- Slow Queries and Outgoing Requests - Half width each -->
        <div class="col-span-12 md:col-span-6">
            @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseSlowQueries::class)
        </div>
        <div class="col-span-12 md:col-span-6">
            @livewire(\Dotswan\FilamentLaravelPulse\Widgets\PulseSlowOutGoingRequests::class)
        </div>
    </div>
</x-filament-panels::page>
