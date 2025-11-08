<div>
    <button
        wire:click="triggerFeedback"
        wire:target="triggerFeedback"
        wire:loading.attr="disabled"
        type="button"
        class="group relative flex items-center justify-center h-14 rounded-full text-white transition-all duration-300 ease-in-out overflow-hidden w-14 hover:w-56 disabled:opacity-75 disabled:cursor-not-allowed"
        aria-label="Give Feedback"
    >
        <div class="absolute inset-0 bg-primary-600 rounded-full"></div>
        <div class="absolute inset-0 bg-primary-500 rounded-full pulse-ring"></div>
        <div class="absolute -inset-2 bg-primary-400 rounded-full opacity-20 blur-lg group-hover:opacity-40 transition-opacity duration-300"></div>

        <div class="relative flex items-center justify-center gap-3 z-10">
            <x-filament::icon
                icon="heroicon-o-chat-bubble-left-ellipsis"
                class="h-7 w-7 flex-shrink-0 transition-all duration-300 group-hover:scale-125 group-hover:-translate-y-1"
                wire:loading.remove
                wire:target="triggerFeedback"
            />

            <x-filament::loading-indicator
                class="h-5 w-5"
                wire:loading
                wire:target="triggerFeedback"
            />

            <span class="absolute left-12 whitespace-nowrap font-bold opacity-0 group-hover:opacity-100 transition-all duration-300" wire:loading.remove wire:target="triggerFeedback">
                Share Feedback
            </span>
        </div>
    </button>

    <style>
        @keyframes pulse-ring {
            0%, 100% {
                transform: scale(0.95);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.7;
            }
        }

        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</div>
