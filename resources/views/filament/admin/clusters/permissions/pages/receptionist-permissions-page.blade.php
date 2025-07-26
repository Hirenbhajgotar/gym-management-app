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
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-information-circle
                        class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                    <div>
                        <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">
                            Permission Management Tips
                        </h3>
                        <div class="space-y-2 text-sm text-blue-800 dark:text-blue-200">
                            <p>• <strong>Resources:</strong> Control access to core gym management features like
                                members, memberships, payments, and classes.</p>
                            <p>• <strong>Widgets:</strong> Manage which dashboard widgets receptionists can view for
                                daily operations.</p>
                            <p>• <strong>Reports:</strong> Configure access to various reporting features and data
                                export capabilities.</p>
                            <p>• <strong>Other:</strong> Additional system features including notifications,
                                communication tools, and administrative functions.</p>
                        </div>
                        <div class="mt-4 p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                <strong>Pro tip:</strong> Use "Quick Setup" to apply common permissions for typical
                                receptionist workflows,
                                or "Reset to Default" to restore standard permissions.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
