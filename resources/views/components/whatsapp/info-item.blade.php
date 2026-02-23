@props([
    'color' => 'success',
    'title',
    'description',
])

@php
    /**
     * Filament registers semantic colors as raw RGB channel CSS variables:
     *   --success-50: 240 253 244  (NOT a full color, just the channels)
     *
     * Tailwind v4 has no --color-success-* tokens registered (the @theme block
     * in app.css is commented out), so bg-success-50 etc. generate no output.
     *
     * Standard Tailwind palette colors (blue, purple, gray) work fine via
     * Tailwind's own built-in theme.
     *
     * For Filament semantic colors (success, warning, danger, info, primary)
     * we use inline style with rgb(var(--<color>-<shade>)) directly.
     */
    $styleMap = [
        'success' => [
            'bg'     => 'background-color: rgb(var(--success-50));',
            'iconBg' => 'background-color: rgb(var(--success-100));',
            'iconFg' => 'color: rgb(var(--success-600));',
            'darkBg'     => '',
            'darkIconBg' => '',
            'darkIconFg' => '',
        ],
        'warning' => [
            'bg'     => 'background-color: rgb(var(--warning-50));',
            'iconBg' => 'background-color: rgb(var(--warning-100));',
            'iconFg' => 'color: rgb(var(--warning-600));',
        ],
        'danger' => [
            'bg'     => 'background-color: rgb(var(--danger-50));',
            'iconBg' => 'background-color: rgb(var(--danger-100));',
            'iconFg' => 'color: rgb(var(--danger-600));',
        ],
        'info' => [
            'bg'     => 'background-color: rgb(var(--info-50));',
            'iconBg' => 'background-color: rgb(var(--info-100));',
            'iconFg' => 'color: rgb(var(--info-600));',
        ],
        'primary' => [
            'bg'     => 'background-color: rgb(var(--primary-50));',
            'iconBg' => 'background-color: rgb(var(--primary-100));',
            'iconFg' => 'color: rgb(var(--primary-600));',
        ],
        // Standard Tailwind palette colors work natively — use classes.
        'blue' => [
            'bg'     => 'background-color: rgb(var(--blue-50));',
            'iconBg' => 'background-color: rgb(var(--blue-100));',
            'iconFg' => 'color: rgb(var(--blue-600));',
            'bgClass'     => 'bg-blue-50 dark:bg-blue-900/20',
            'iconBgClass' => 'bg-blue-100 dark:bg-blue-900/40',
            'iconFgClass' => 'text-blue-600 dark:text-blue-400',
        ],
        'purple' => [
            'bg'     => 'background-color: rgb(var(--purple-50));',
            'iconBg' => 'background-color: rgb(var(--purple-100));',
            'iconFg' => 'color: rgb(var(--purple-600));',
            'bgClass'     => 'bg-purple-50 dark:bg-purple-900/20',
            'iconBgClass' => 'bg-purple-100 dark:bg-purple-900/40',
            'iconFgClass' => 'text-purple-600 dark:text-purple-400',
        ],
    ];

    $c = $styleMap[$color] ?? $styleMap['success'];

    $bgStyle     = $c['bg']     ?? null;
    $iconBgStyle = $c['iconBg'] ?? null;
    $iconFgStyle = $c['iconFg'] ?? null;

    $bgClass     = $c['bgClass']     ?? '';
    $iconBgClass = $c['iconBgClass'] ?? '';
    $iconFgClass = $c['iconFgClass'] ?? '';
@endphp

<div
    {{ $attributes->merge(['class' => "flex items-start gap-3 p-3 rounded-lg {$bgClass}"]) }}
    @if($bgStyle) style="{{ $bgStyle }}" @endif
>
    <div
        class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center {{ $iconBgClass }}"
        @if($iconBgStyle) style="{{ $iconBgStyle }}" @endif
    >
        <span
            class="w-4 h-4 {{ $iconFgClass }}"
            @if($iconFgStyle) style="{{ $iconFgStyle }}" @endif
        >
            {{ $icon }}
        </span>
    </div>
    <div>
        <h4 class="font-medium text-sm text-gray-900 dark:text-gray-900">{{ $title }}</h4>
        <p class="text-xs text-gray-600 dark:text-gray-400">{{ $description }}</p>
    </div>
</div>
