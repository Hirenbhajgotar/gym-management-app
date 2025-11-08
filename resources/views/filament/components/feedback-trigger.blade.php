<x-filament::icon-button
    wire:click="triggerFeedback"
    wire:target="triggerFeedback"
    wire:loading.attr="disabled"
    icon="heroicon-o-chat-bubble-left-ellipsis"
    color="primary"
    label="Give Feedback"
    size="xl"
    tooltip="Give Feedback"
/>
