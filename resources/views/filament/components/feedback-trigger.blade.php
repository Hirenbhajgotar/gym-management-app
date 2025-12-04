<div>
    <button wire:click="triggerFeedback" wire:target="triggerFeedback" wire:loading.attr="disabled" type="button"
        class="group relative flex items-center justify-center bg-primary-600 hover:bg-primary-700 h-14 rounded-full text-white transition-all duration-300 ease-in-out overflow-hidden w-14  disabled:opacity-75 disabled:cursor-not-allowed"
        aria-label="Give Feedback">

        <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis"
            class="h-7 w-7 flex-shrink-0 transition-all duration-300 group-hover:scale-110" wire:loading.remove
            wire:target="triggerFeedback" />

        <!-- Loading indicator -->
        <x-filament::loading-indicator class="h-5 w-5" wire:loading wire:target="triggerFeedback" />
    </button>
</div>
