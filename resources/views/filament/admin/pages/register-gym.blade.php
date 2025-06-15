<x-filament-panels::page.simple heading="Register Gym" subheading="Fill in the details to set up your gym in the system.">
    <!-- Form Start -->
    <div class="w-full">
        <x-filament-panels::form wire:submit="register">
            {{ $this->form }}

            <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
        </x-filament-panels::form>
    </div>
    <!-- Form End -->

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
