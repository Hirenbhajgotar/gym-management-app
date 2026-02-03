<x-filament-panels::page>
    <div class="space-y-6">
        @php $stats = $this->getLtvStats(); @endphp

        {{-- LTV Overview Cards --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                    ₹{{ number_format($stats['altv'], 2) }}
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Avg Lifetime Value (ALTV)</div>
                <p class="mt-2 text-xs text-gray-400">Avg revenue generated per member over their entire lifecycle</p>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-success-600 dark:text-success-400">
                    ₹{{ number_format($stats['arpu'], 2) }}
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monthly ARPU</div>
                <p class="mt-2 text-xs text-gray-400">Average Revenue Per Active User (Monthly)</p>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-info-600 dark:text-info-400">
                    {{ $stats['lifespan_months'] }} Months
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Avg Lifespan</div>
                <p class="mt-2 text-xs text-gray-400">Estimated average time a member stays active</p>
            </x-filament::section>
            
            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">
                    ₹{{ number_format($stats['total_revenue']) }}
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total Lifetime Revenue</div>
                <p class="mt-2 text-xs text-gray-400">Cumulative revenue from all members since inception</p>
            </x-filament::section>
        </div>

        {{-- Detailed Explanation / Placeholder for Charts --}}
        <x-filament::section>
            <x-slot name="heading">Strategies to Increase LTV</x-slot>
            <div class="grid gap-6 md:grid-cols-3">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Increase Retention</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Extend the average member lifespan. Improving retention by just 5% can increase profits by 25-95%.</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Upsell & Cross-sell</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Encourage members to upgrade to premium plans or purchase add-on services like personal training.</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Reduce Acquisition Costs</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Focus on organic growth and referral programs to lower the cost of acquiring new high-value members.</p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
