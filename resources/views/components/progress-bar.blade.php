@props([
    'progress' => 0,
    'color' => 'gray',
    'height' => 'h-2',
])

@php
    $progress = min(max($progress, 0), 100);

    $colorHex = match($color) {
        'success' => '#22c55e', // green-500
        'warning' => '#f59e0b', // amber-500
        'danger' => '#ef4444',  // red-500
        'info' => '#3b82f6',    // blue-500
        'primary' => '#3b82f6', // blue-500
        'gray' => '#6b7280',    // gray-500 (default)
        default => $color,
    };
@endphp

<div class="{{ $height }} w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
    <div 
        class="h-full rounded-full transition-all duration-500" 
        style="width: {{ $progress }}%; background-color: {{ $colorHex }};"
    ></div>
</div>
