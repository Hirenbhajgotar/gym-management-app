<x-filament-panels::page>
    @if(!$useIndividualWaba)
        <x-filament::section>
            <div class="flex items-start gap-4 p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg border border-warning-200 dark:border-warning-800">
                <div class="p-2 bg-warning-100 dark:bg-warning-900/40 rounded-full">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div>
                    <h3 class="text-lg font-medium text-warning-800 dark:text-warning-200">Feature Not Enabled</h3>
                    <p class="text-warning-700 dark:text-warning-300 mt-1">
                        Individual WABA mode is not enabled in your configuration. 
                        Set <code class="px-1 py-0.5 bg-warning-100 dark:bg-warning-900/60 rounded">TWILIO_USE_INDIVIDUAL_WABA=true</code> in your environment.
                    </p>
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">
                WABA Migration Status
            </x-slot>
            <x-slot name="description">
                Migrate from shared WABA to individual WABA for complete isolation
            </x-slot>

            @if($whatsappAccount && $whatsappAccount->isWabaProvider() && $whatsappAccount->isEmbeddedSignupComplete())
                <div class="flex items-start gap-4 p-4 bg-success-50 dark:bg-success-900/20 rounded-lg border border-success-200 dark:border-success-800">
                    <div class="p-2 bg-success-100 dark:bg-success-900/40 rounded-full">
                        <x-heroicon-o-check-circle class="w-6 h-6 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-success-800 dark:text-success-200">Already on Individual WABA</h3>
                        <p class="text-success-700 dark:text-success-300 mt-1">
                            Your account is already using individual WABA. No migration needed.
                        </p>
                    </div>
                </div>
            @elseif($pendingMigration)
                <div class="flex items-start gap-4 p-4 bg-info-50 dark:bg-info-900/20 rounded-lg border border-info-200 dark:border-info-800 mb-6">
                    <div class="p-2 bg-info-100 dark:bg-info-900/40 rounded-full">
                        <x-heroicon-o-arrow-path class="w-6 h-6 text-info-600 dark:text-info-400 animate-spin" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-medium text-info-800 dark:text-info-200">Migration In Progress</h3>
                        <p class="text-info-700 dark:text-info-300 mt-1">
                            A migration is currently pending or in progress. Complete the Embedded Signup flow to finish.
                        </p>
                        <div class="mt-4">
                            <x-filament::button
                                tag="a"
                                href="{{ route('whatsapp.embedded-signup.redirect', ['tenant' => filament()->getTenant()->slug]) }}"
                                icon="heroicon-o-arrow-path"
                            >
                                Continue Embedded Signup
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-full">
                        <x-heroicon-o-arrow-path class="w-6 h-6 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Shared WABA Detected</h3>
                        <p class="text-gray-600 dark:text-gray-400 mt-1 mb-4">
                            Your account is currently using a shared WABA. Migrate to individual WABA for complete isolation.
                        </p>
                        <x-filament::button
                            wire:click="startMigration"
                            icon="heroicon-o-arrow-path"
                        >
                            Start Migration
                        </x-filament::button>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                @php
                    $stats = [
                        ['label' => 'Total Migrations', 'value' => $migrationStats['total'] ?? 0, 'color' => 'text-gray-600 dark:text-gray-400'],
                        ['label' => 'Completed', 'value' => $migrationStats['completed'] ?? 0, 'color' => 'text-success-600 dark:text-success-400'],
                        ['label' => 'Pending', 'value' => $migrationStats['pending'] ?? 0, 'color' => 'text-warning-600 dark:text-warning-400'],
                        ['label' => 'Failed', 'value' => $migrationStats['failed'] ?? 0, 'color' => 'text-danger-600 dark:text-danger-400'],
                    ];
                @endphp

                @foreach($stats as $stat)
                    <div class="p-4 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-bold {{ $stat['color'] }}">{{ $stat['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Migration History
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
