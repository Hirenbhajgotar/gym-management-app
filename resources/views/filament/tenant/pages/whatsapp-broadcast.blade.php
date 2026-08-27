<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}
    </div>

    @push('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.on('broadcast-sent', (event) => {
                    // You can add confetti or other success animations here
                });
            });
        </script>
    @endpush

    <x-filament-actions::modals />
</x-filament-panels::page>
