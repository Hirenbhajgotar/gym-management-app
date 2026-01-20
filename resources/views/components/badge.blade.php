@props([
    'color' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconPosition' => 'before',
])

@php
    $colorClasses = match ($color) {
        'primary' => 'text-amber-700 bg-amber-50 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400 dark:ring-amber-400/30',
        'success' => 'text-emerald-700 bg-emerald-50 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-400 dark:ring-emerald-400/30',
        'warning' => 'text-orange-700 bg-orange-50 ring-orange-600/20 dark:bg-orange-400/10 dark:text-orange-400 dark:ring-orange-400/30',
        'danger' => 'text-red-700 bg-red-50 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/30',
        'info' => 'text-sky-700 bg-sky-50 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-400 dark:ring-sky-400/30',
        'gray' => 'text-gray-700 bg-gray-50 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/30',
        default => 'text-gray-700 bg-gray-50 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/30',
    };

    $sizeClasses = match ($size) {
        'xs' => 'px-1.5 py-0.5 text-xs',
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-0.5 text-sm',
        'lg' => 'px-3 py-1 text-base',
        default => 'px-2.5 py-0.5 text-sm',
    };

    $iconSizeClasses = match ($size) {
        'xs' => 'h-3 w-3',
        'sm' => 'h-3.5 w-3.5',
        'md' => 'h-4 w-4',
        'lg' => 'h-5 w-5',
        default => 'h-4 w-4',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-x-1.5 rounded-md font-medium ring-1 ring-inset $colorClasses $sizeClasses"]) }}>
    @if ($icon && $iconPosition === 'before')
        <x-icon :name="$icon" class="{{ $iconSizeClasses }}" />
    @endif

    <span>{{ $slot }}</span>

    @if ($icon && $iconPosition === 'after')
        <x-icon :name="$icon" class="{{ $iconSizeClasses }}" />
    @endif
</span>
