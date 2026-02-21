<x-filament-panels::page>
    @if($whatsappAccount?->hasIndividualWaba() || $whatsappAccount?->hasManualWaba())
        <x-filament::section>
            <div class="flex items-start gap-4 p-4 bg-success-50 dark:bg-success-900/20 rounded-lg border border-success-200 dark:border-success-800 mb-6">
                <div class="p-2 bg-success-100 dark:bg-success-900/40 rounded-full">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-medium text-success-800 dark:text-success-200">WABA Connected</h3>
                    <p class="text-success-700 dark:text-success-300 mt-1">
                        Your WhatsApp Business Account is connected with 
                        <span class="font-semibold">{{ ucfirst($whatsappAccount->waba_mode->value) }}</span> mode.
                    </p>
                    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">WABA ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ $whatsappAccount->waba_id }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">WABA Status</dt>
                            <dd class="mt-1">
                                <x-filament::badge :color="$whatsappAccount->waba_status->getColor()">
                                    {{ $whatsappAccount->waba_status->getLabel() }}
                                </x-filament::badge>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">
            Manual WABA Connection
        </x-slot>
        <x-slot name="description">
            Connect your existing WhatsApp Business Account manually
        </x-slot>

        {{ $this->form }}
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
