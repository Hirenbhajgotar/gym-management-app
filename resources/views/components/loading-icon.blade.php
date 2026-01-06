@props(['target', 'name' => 'loading', 'variant' => 'icon'])

<div wire:loading wire:target="{{ $target }}" class=" items-center">
    <div class="inline-flex">
        <svg {{ $attributes->merge(['class' => 'w-5 h-5 animate-spin']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        @if($variant === 'text')
            <span class="ml-2">{{ $name }}...</span>
        @endif
    </div>
</div>
