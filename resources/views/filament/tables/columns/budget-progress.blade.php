@php
    $percentage = $getRecord()->getUsagePercentage();
    $spent = $getRecord()->getSpentAmount();
    $total = $getRecord()->amount;
    $color = $getRecord()->getStatusColor();
    
    $colorClasses = match($color) {
        'success' => '#22c55e',  // green-500
        'info' => '#3b82f6',     // blue-500
        'warning' => '#eab308',  // yellow-500
        'danger' => '#ef4444',   // red-500
        default => '#6b7280',    // gray-500
    };
@endphp

<div class="flex flex-col gap-1">
    <div class="flex items-center justify-between text-xs">
        <span class="font-medium">
            {{ Number::currency($spent, in: $getRecord()->currency) }}
        </span>
        <span class="text-gray-500">
            {{ $percentage }}%
        </span>
    </div>
    <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700">
        <div 
            class="h-full transition-all duration-300"
            style="width: {{ min($percentage, 100) }}%; background-color: {{ $colorClasses }};"
        ></div>
    </div>
    @if($percentage > 100)
        <span class="text-xs text-danger-600 font-medium">
            Over budget by {{ Number::currency($spent - $total, in: $getRecord()->currency) }}
        </span>
    @endif
</div>
