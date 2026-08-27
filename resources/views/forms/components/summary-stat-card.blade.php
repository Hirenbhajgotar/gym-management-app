@php
    $palette = $getPaletteForColor();
    $icon = $getIcon();
    $stats = $getStats();
    $divider = $getDivider();

    $bgLight = "rgba({$palette[50]}, 0.85)";
    $borderLight = "rgba(" . ($palette[200] ?? $palette[300]) . ", 0.8)";
    $iconBgLight = "rgba(" . ($palette[100] ?? $palette[200]) . ", 0.95)";
    $iconFgLight = "rgb(" . ($palette[600] ?? $palette[700]) . ")";

    $bgDark = "rgba(" . ($palette[950] ?? $palette[900]) . ", 0.45)";
    $borderDark = "rgba(" . ($palette[800] ?? $palette[700]) . ", 0.6)";
    $iconBgDark = "rgba(" . ($palette[900] ?? $palette[800]) . ", 0.7)";
    $iconFgDark = "rgb(" . ($palette[400] ?? $palette[300]) . ")";
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        {{
            $attributes
                ->merge($getExtraAttributes(), escape: false)
                ->class([
                    'w-full rounded-xl border p-4 shadow-2xs transition duration-150 ease-in-out dark:bg-[var(--bg-dark)] dark:border-[var(--border-dark)]',
                ])
        }}
        style="
            background-color: {{ $bgLight }};
            border-color: {{ $borderLight }};
            --bg-dark: {{ $bgDark }};
            --border-dark: {{ $borderDark }};
        "
    >
        <div class="flex flex-wrap items-center gap-4">
            @if ($icon)
                <div
                    class="flex shrink-0 items-center justify-center rounded-xl p-3 shadow-2xs dark:bg-[var(--icon-bg-dark)] dark:text-[var(--icon-fg-dark)]"
                    style="
                        background-color: {{ $iconBgLight }};
                        color: {{ $iconFgLight }};
                        --icon-bg-dark: {{ $iconBgDark }};
                        --icon-fg-dark: {{ $iconFgDark }};
                    "
                >
                    <x-filament::icon
                        :icon="$icon"
                        class="h-6 w-6"
                    />
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                @foreach ($stats as $index => $stat)
                    @if ($index > 0 && $divider !== false)
                        @php
                            $dividerVal = (string) $divider;
                            $isIconDivider = str_starts_with($dividerVal, 'heroicon-') || str_starts_with($dividerVal, 'phosphor-');
                            $isLineDivider = in_array($dividerVal, ['|', 'line', 'bar'], true);
                        @endphp

                        @if ($isLineDivider)
                            <!-- Vertical Divider Line (|) -->
                            <div
                                class="shrink-0 self-stretch my-1 dark:bg-white/20"
                                style="width: 1.5px; min-width: 1.5px; flex-shrink: 0; background-color: rgba(0, 0, 0, 0.2);"
                            ></div>
                        @elseif ($isIconDivider)
                            <!-- Icon Divider -->
                            <div class="shrink-0 text-gray-400 dark:text-gray-500 self-center">
                                <x-filament::icon
                                    :icon="$dividerVal"
                                    class="h-5 w-5"
                                />
                            </div>
                        @else
                            <!-- Character / Text Divider (e.g. '/', '•', '-') -->
                            <div class="shrink-0 text-base font-semibold text-gray-400 dark:text-gray-500 self-center select-none">
                                {{ $dividerVal }}
                            </div>
                        @endif
                    @endif

                    <div class="flex flex-col justify-center min-w-max">
                        @if (! empty($stat['label']))
                            <span class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $stat['label'] }}
                            </span>
                        @endif

                        <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                            {{ $stat['value'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-dynamic-component>
