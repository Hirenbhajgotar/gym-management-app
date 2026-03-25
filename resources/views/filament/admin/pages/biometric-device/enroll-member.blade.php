<x-filament-panels::page>
    <div class="fi-page-content">
        {{ $this->form }}

        <div class="mt-6 flex justify-between">
            <div class="space-x-3">
                <x-filament::button type="button" icon="heroicon-o-finger-print" color="primary" size="xl"
                    wire:click="enroll" wire:loading.attr="disabled">
                    Enroll
                </x-filament::button>
            </div>
        </div>

        <x-filament-actions::modals />
    </div>
</x-filament-panels::page>
