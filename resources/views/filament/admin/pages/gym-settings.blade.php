<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Main Form --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-between">
                <div class="space-x-3">
                    <x-filament::button type="submit" icon="heroicon-m-plus-circle" color="success" size="xl"
                        wire:target="save" wire:loading.attr="disabled" outlined>
                        Save
                    </x-filament::button>
                </div>
            </div>
        </form>
    </div>
</x-filament-panels::page>
