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
