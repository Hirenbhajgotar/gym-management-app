@php
    $percentage = $getRecord()->getUsagePercentage();
    $spent = $getRecord()->getSpentAmount();
    $total = $getRecord()->amount;
    $color = $getRecord()->getStatusColor();
    
    $colorClasses = match($color) {
        'success' => 'bg-success-500',
        'info' => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        default => 'bg-gray-500',
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
            class="h-full {{ $colorClasses }} transition-all duration-300"
            style="width: {{ min($percentage, 100) }}%"
        ></div>
    </div>
    @if($percentage > 100)
        <span class="text-xs text-danger-600 font-medium">
            Over budget by {{ Number::currency($spent - $total, in: $getRecord()->currency) }}
        </span>
    @endif
</div>
