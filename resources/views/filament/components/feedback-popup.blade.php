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

                        <!-- Rating -->
                        <div x-data="{
                            hoveredRating: 0,
                            selectedRating: @entangle('rating'),
                            setRating(rating) {
                                this.selectedRating = rating;
                                $wire.set('rating', rating);
                            }
                        }">
                            <label class="block text-sm font-medium text-gray-400 dark:text-gray-300 mb-2">
                                How would you rate your experience?
                            </label>
                            <div class="flex space-x-2 justify-center"
                                @mouseleave="hoveredRating = selectedRating || 0">
                                @for ($i = 1; $i <= 5; $i++)
                                    <button type="button" @click="setRating({{ $i }})"
                                        @mouseenter="hoveredRating = {{ $i }}"
                                        class="transition-all duration-200 transform hover:scale-110 focus:outline-none  rounded p-1"
                                        :class="{
                                            'text-primary-400': selectedRating >= {{ $i }} || (
                                                hoveredRating >=
                                                {{ $i }} && hoveredRating > 0),
                                            'text-gray-300 dark:text-gray-600': selectedRating < {{ $i }} &&
                                                (hoveredRating < {{ $i }} || hoveredRating === 0)
                                        }">
                                        <x-filament::icon icon="heroicon-s-star" class="h-8 w-8" />
                                    </button>
                                @endfor
                            </div>

                            @error('rating')
                                <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Feedback Text -->
                        <div>
                            <label class="block text-sm font-medium text-gray-400 dark:text-gray-300 mb-2">
                                Tell us more about your experience
                            </label>
                            <textarea wire:model="feedback" rows="4" placeholder="What did you like? What could we improve?"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm resize-none
                                             focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                             dark:bg-gray-700 dark:border-gray-600 dark:text-white 
                                             dark:placeholder-gray-400 dark:focus:ring-primary-500 dark:focus:border-primary-500
                                             transition-colors duration-200"></textarea>
                            @error('feedback')
                                <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email (Optional) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-400 dark:text-gray-300 mb-2">
                                Email (optional - for follow-up)
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input type="email" wire:model="email" placeholder="your@email.com" />
                            </x-filament::input.wrapper>
                            @error('email')
                                <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

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
