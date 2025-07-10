<div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-4xl bg-white rounded-lg shadow-lg p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Gym Owner Registration</h1>
            <p class="text-gray-600">Join our platform to manage your gym and connect with fitness enthusiasts.</p>
        </div>

        <form wire:submit="submit" class="filament-forms-container">
            {{ $this->form }}
        </form>
    </div>
</div>
{{-- <div>
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div
                class="relative w-full max-w-2xl transform overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 transition-all dark:bg-gray-800 dark:ring-white/10">

                <form wire:submit="submit" class="p-6 space-y-6 bg-white dark:bg-gray-800 filament-forms-container">
                    {{ $this->form }}
                </form>
            </div>
        </div>
    </div>
</div> --}}
