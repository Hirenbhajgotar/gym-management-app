{{-- filament.admin.clusters.permissions.pages.receptionist-permissions-page --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Main Form Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <form wire:submit="save" class="space-y-6">
                    <!-- Form with Tabs -->
                    <div class="w-full">
                        {{ $this->form }}
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <x-filament::button type="submit" icon="heroicon-o-check-circle" wire:loading.attr="disabled"
                                wire:target="save" color="primary" size="lg">
                                Save Permissions
                            </x-filament::button>

                            {{ $this->getResetPermissionAction() }}
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Help Section -->
        <x-permission-tips />
    </div>
</x-filament-panels::page>
