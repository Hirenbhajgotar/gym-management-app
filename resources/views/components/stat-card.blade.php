@props([
    'icon' => 'chart-bar',
    'iconColor' => 'text-gray-500',
    'iconBg' => 'bg-gray-100 dark:bg-gray-800',
    'title',
    'value',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10']) }}>
    <div class="flex items-center gap-x-4">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconBg }}">
            <x-icon :name="$icon" class="h-6 w-6 {{ $iconColor }}" />
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</p>
            <p class="text-3xl font-semibold text-gray-950 dark:text-white">{{ $value }}</p>
        </div>
    </div>

    @if ($description)
        <p class="mt-2 pl-14 text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif
</div>
