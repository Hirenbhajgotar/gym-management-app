<div>
    @if ($showModal)
        <div x-data="{ show: @entangle('showModal') }" x-show="show" x-transition.opacity.duration.300ms
            class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">

            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"></div>

            <!-- Modal -->
            <div class="flex min-h-screen items-center justify-center p-4">
                <div
                    class="relative w-full max-w-2xl transform overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 transition-all dark:bg-gray-800 dark:ring-white/10">

                    <!-- Header -->
                    <div
                        class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-700 dark:to-gray-800">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="h-7 w-7" />
                                We'd love your feedback!
                            </h3>
                            <button wire:click="closeFeedback"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Form -->
                    <form wire:submit.prevent="submitFeedback" class="p-6 space-y-6 bg-white dark:bg-gray-800">
                        {{ $this->form }}

                        <!-- Buttons -->
                        <div class="flex space-x- gap-x-4 pt-6 border-t border-gray-100 dark:border-gray-700">
                            <x-filament::button wire:click="closeFeedback" wire:target="submitFeedback" color="gray"
                                outlined="true">
                                Maybe Later
                            </x-filament::button>

                            <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled"
                                wire:target="submitFeedback">
                                Submit Feedback
                            </x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
