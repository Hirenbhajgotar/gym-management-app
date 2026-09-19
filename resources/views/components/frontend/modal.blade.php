@props(['size' => 'md', 'triggerClass' => 'inline-block'])

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
    <div @click="open = true" class="{{ $triggerClass }}">
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
                    class="relative transform overflow-hidden rounded-2xl bg-surface-container-low border border-outline-variant/40 text-left shadow-2xl transition-all sm:my-8 sm:w-full {{ $maxWidth }}"
                    @click.away="open = false"
                    @keydown.escape.window="open = false"
                >
                    <div class="absolute top-0 right-0 pt-4 pr-4 z-10">
                        <button @click="open = false" type="button" class="text-on-surface-variant hover:text-primary transition-colors bg-surface-container-high rounded-full p-1.5 hover:bg-surface-container-highest cursor-pointer">
                            <span class="sr-only">Close</span>
                            <span class="material-symbols-outlined text-lg leading-none">close</span>
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
