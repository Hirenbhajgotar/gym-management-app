@props(['size' => 'md'])

@php
    $maxWidth = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ][$size] ?? 'sm:max-w-md';
@endphp

<div x-data="{ open: false }">
    <!-- Trigger -->
    <div @click="open = true" class="inline-block">
        {{ $trigger }}
    </div>

    <!-- Modal Portal -->
    <template x-teleport="body">
        <div
            x-show="open"
            style="display: none;"
            class="fixed inset-0 z-[100] overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <!-- Backdrop -->
            <div
                x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
                @click="open = false"
            ></div>

            <!-- Panel -->
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div
                    x-show="open"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative transform overflow-hidden rounded-2xl bg-gray-900 border border-purple-500/20 text-left shadow-2xl transition-all sm:my-8 sm:w-full {{ $maxWidth }}"
                    @click.away="open = false"
                    @keydown.escape.window="open = false"
                >
                    <div class="absolute top-0 right-0 pt-4 pr-4 z-10">
                        <button @click="open = false" type="button" class="text-gray-400 hover:text-white transition-colors bg-gray-800/50 rounded-full p-1 hover:bg-gray-700">
                            <span class="sr-only">Close</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-8 text-center">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
