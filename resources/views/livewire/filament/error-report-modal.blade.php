<div>
    <x-filament::modal id="error-report-modal" :visible="$isOpen" width="4xl" :close-by-clicking-away="false">
        <x-slot name="heading">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/20 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-bug-ant class="w-6 h-6 text-red-600 dark:text-red-400" />
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Report Error
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Help us improve by reporting this issue
                    </p>
                </div>
            </div>
        </x-slot>


        <div class="space-y-6">
            <!-- Progress indicator -->
            <div class="flex items-center justify-center space-x-2 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="w-2 h-2 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                    <div class="w-8 h-0.5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                    <div class="w-2 h-2 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                    <div class="w-8 h-0.5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                    <div class="w-2 h-2 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                </div>
            </div>

            <!-- Alert banner -->
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-information-circle class="h-5 w-5 text-amber-400" />
                    </div>
                    <div class="ml-3">
                        <h4 class="text-sm font-medium text-amber-800 dark:text-amber-200">
                            Before you submit
                        </h4>
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                            Please check if refreshing the page or trying again resolves the issue.
                            If the problem persists, we'd appreciate your detailed report.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Main form -->
            <form wire:submit.prevent="submitReport" class="space-y-6">
                <div
                    class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl">
                    {{ $this->form }}
                </div>

                <!-- Additional help text -->
                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                        💡 Tips for better error reports:
                    </h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <li>• Be specific about what you were trying to accomplish</li>
                        <li>• Include the sequence of actions that led to the error</li>
                        <li>• Mention if this is the first time you've encountered this issue</li>
                        <li>• Include any error messages or unusual behavior you noticed</li>
                    </ul>
                </div>
            </form>
        </div>

        <x-slot name="footerActions">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400 gap-3">
                    <x-heroicon-o-shield-check class="w-4 h-4" />
                    <span>Your report is confidential and helps improve the system</span>
                </div>

                <div class="flex space-x-3 gap-3">
                    <x-filament::button wire:click="closeModal" wire:target="closeModal" color="gray"
                        icon="heroicon-o-x-mark">
                        Cancel
                    </x-filament::button>

                    <x-filament::button wire:click="submitReport" wire:target="submitReport" color="primary"
                        icon="heroicon-o-paper-airplane">
                        Submit Report
                    </x-filament::button>
                </div>
            </div>
        </x-slot>
    </x-filament::modal>
</div>
