<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6">
                <div class="flex mb-9 gap-3">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-user-circle class="w-8 h-8 text-blue-500" />
                    </div>
                    <div class="ml-3">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            Receptionist Permissions
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">
                            Configure permissions for receptionist role across different resources.
                        </p>
                    </div>
                </div>


                <form wire:submit="save">
                    {{ $this->form }}

                    <div class="mt-6">
                        <x-filament::button type="submit" icon="heroicon-o-key" wire:loading.attr="disabled"
                            wire:target="save">
                            Save
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
