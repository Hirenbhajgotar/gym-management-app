<x-filament-panels::page.simple heading="Register Business"
    subheading="Fill in the details to set up your business in the system.">
    @php
        // $currentGymCount = \App\Models\Gym::where('owner_id', auth()->id())->count();
        $currentGymCount = $this->authUserGyms;
        $hasReachedLimit = $currentGymCount >= $totalGyms;
        $remainingGyms = $totalGyms - $currentGymCount;
    @endphp

    @if ($hasReachedLimit)
        <div class="mb-6 p-6 bg-danger-50 border border-danger-200 rounded-lg ">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle"
                        class="h-6 w-6 text-danger-500 dark:text-danger-400 " />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-danger-600 dark:text-danger-400">
                        Business Registration Limit Reached
                    </h3>
                    <div class="mt-2 text-sm">
                        <p>You have already registered {{ $currentGymCount }} business(es), which is the maximum
                            allowed.
                            Please contact our support team if you need to register additional businesses.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <!-- Form Start -->
    <div class="w-full">
        <x-filament-panels::form wire:submit="register">
            {{ $this->form }}

            @if (!$hasReachedLimit)
                <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
            @endif
        </x-filament-panels::form>
    </div>
    <!-- Form End -->

    @if ($hasReachedLimit)
        <div class="mt-6 text-center">
            <div
                class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Registration Disabled - Limit Reached
            </div>
        </div>
    @endif

    <style>
        .fi-simple-main {
            max-width: 50% !important;
        }

        /* Remove container constraints and make everything full width */
        .fi-simple-page {
            max-width: none !important;
            width: 100% !important;
        }

        .fi-form {
            width: 100% !important;
            max-width: none !important;
        }

        .fi-section {
            width: 100% !important;
            max-width: none !important;
        }

        .fi-section-content {
            max-width: none !important;
        }

        /* Make form fields utilize full width within their containers */
        .fi-input,
        .fi-select,
        .fi-textarea {
            width: 100% !important;
        }

        /* Override any max-width constraints */
        .fi-form-component-container {
            max-width: none !important;
        }

        /* Override Filament's default form max-width */
        .fi-form .fi-fo-component-ctn {
            max-width: none !important;
        }

        /* Make the page container full width */
        .fi-page {
            max-width: none !important;
        }

        /* Remove any centered positioning */
        .fi-page-simple {
            max-width: none !important;
        }

        /* Button loading state styling */
        .fi-btn:disabled {
            opacity: 0.75;
            cursor: not-allowed;
        }
    </style>
</x-filament-panels::page.simple>
