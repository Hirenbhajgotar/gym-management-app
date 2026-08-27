@php
    $cardValue = (string) $getCardValue();
    $title = $getTitle();
    $body = $getBody();
    $badge = $getBadge();
    $icon = $getIcon();
    $statePath = $getStatePath();
    $name = $getName();
    $palette = $getPaletteForColor();

    $hasError = $hasValidationError() || $errors->has($statePath) || $errors->has($name) || $errors->has("data.{$name}") || $errors->has("data.{$statePath}");

    $accentRgb = "rgb(" . ($palette[600] ?? $palette[500] ?? '22, 163, 74') . ")";
    $accentDarkRgb = "rgb(" . ($palette[400] ?? $palette[300] ?? '74, 222, 128') . ")";

    // Unselected Card Styles (Highlighted with red border on validation error)
    $unselectedBorderLight = $hasError ? "rgb(239, 68, 68)" : "rgba(" . ($palette[300] ?? '167, 243, 208') . ", 0.7)";
    $unselectedBorderDark = $hasError ? "rgb(248, 113, 113)" : "rgba(" . ($palette[600] ?? '5, 150, 105') . ", 0.45)";
    $unselectedBgLight = $hasError ? "rgba(254, 242, 242, 0.6)" : "rgba(" . ($palette[50] ?? '240, 253, 244') . ", 0.35)";
    $unselectedBgDark = $hasError ? "rgba(69, 10, 10, 0.4)" : "rgba(31, 41, 55, 0.9)"; // Dark mode gray-800 card

    // Selected Card Styles
    $selectedBorder = $hasError ? "rgb(239, 68, 68)" : "rgb(" . ($palette[600] ?? $palette[500] ?? '22, 163, 74') . ")";
    $selectedBgLight = "rgba(" . ($palette[100] ?? '220, 252, 231') . ", 0.75)";
    $selectedBgDark = "rgba(" . ($palette[950] ?? '6, 78, 59') . ", 0.65)";

    // Button Styles
    $selectedBtnBg = "rgb(" . ($palette[600] ?? $palette[700] ?? '22, 163, 74') . ")";
    $unselectedBtnBorder = $hasError ? "rgb(239, 68, 68)" : "rgb(" . ($palette[600] ?? $palette[500] ?? '22, 163, 74') . ")";
    $unselectedBtnText = $hasError ? "rgb(220, 38, 38)" : "rgb(" . ($palette[700] ?? $palette[600] ?? '21, 128, 61') . ")";

    // Badge Styles
    $badgeBgLight = "rgba(" . ($palette[100] ?? '220, 252, 231') . ", 0.95)";
    $badgeBgDark = "rgba(" . ($palette[900] ?? '6, 78, 59') . ", 0.85)";
    $badgeTextLight = "rgb(" . ($palette[800] ?? '22, 101, 52') . ")";
    $badgeTextDark = "rgb(" . ($palette[300] ?? '134, 239, 172') . ")";
@endphp

<div
    x-data="{
        value: $wire.entangle('{{ $statePath }}'),
        get isCardSelected() {
            return String(this.value ?? '') === String(@js($cardValue));
        }
    }"
    @click="value = @js($cardValue); $wire.set('{{ $statePath }}', @js($cardValue))"
    {{
        $attributes
            ->merge($getExtraAttributes(), escape: false)
            ->class([
                'group relative flex w-full min-w-0 flex-col justify-between rounded-xl p-4 transition-all duration-200 cursor-pointer select-none box-border',
            ])
    }}
    :class="isCardSelected ? 'shadow-md dark:bg-[var(--card-selected-bg-dark)]' : 'shadow-2xs dark:bg-[var(--card-unselected-bg-dark)] hover:shadow-xs'"
    :style="isCardSelected
        ? 'border: 2px solid {{ $selectedBorder }}; background-color: {{ $selectedBgLight }}; --card-selected-bg-dark: {{ $selectedBgDark }}; min-width: 0; width: 100%;'
        : 'border: 1.5px solid {{ $unselectedBorderLight }}; background-color: {{ $unselectedBgLight }}; --card-unselected-bg-dark: {{ $unselectedBgDark }}; border-color: var(--unselected-border-dark, {{ $unselectedBorderLight }}); --unselected-border-dark: {{ $unselectedBorderDark }}; min-width: 0; width: 100%;'"
>
    <!-- Accessible Hidden Radio Input -->
    <input
        type="radio"
        name="{{ $getName() }}"
        value="{{ $cardValue }}"
        :checked="isCardSelected"
        class="sr-only"
    />

    <div class="space-y-2.5 w-full min-w-0">
        <!-- Header Row: Category Badge (above title) & Checkmark -->
        <div class="flex items-center justify-between gap-2 min-h-[22px] w-full min-w-0">
            <!-- Category Badge (above title) -->
            <div>
                @if ($badge)
                    <span
                        class="inline-block rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider dark:bg-[var(--badge-bg-dark)] dark:text-[var(--badge-text-dark)]"
                        style="
                            background-color: {{ $badgeBgLight }};
                            color: {{ $badgeTextLight }};
                            --badge-bg-dark: {{ $badgeBgDark }};
                            --badge-text-dark: {{ $badgeTextDark }};
                        "
                    >
                        {{ $badge }}
                    </span>
                @endif
            </div>

            <!-- Checkmark Icon when selected -->
            <div
                x-show="isCardSelected"
                x-cloak
                class="flex shrink-0 items-center justify-center dark:text-[var(--accent-dark)]"
                style="color: {{ $accentRgb }}; --accent-dark: {{ $accentDarkRgb }};"
            >
                <x-filament::icon
                    icon="heroicon-s-check-circle"
                    class="h-6 w-6"
                />
            </div>
        </div>

        <!-- Title Row -->
        <div class="flex items-center gap-2 w-full min-w-0">
            @if ($icon)
                <div class="shrink-0 dark:text-[var(--accent-dark)]" style="color: {{ $accentRgb }}; --accent-dark: {{ $accentDarkRgb }};">
                    <x-filament::icon
                        :icon="$icon"
                        class="h-5 w-5"
                    />
                </div>
            @endif

            @if ($title)
                <h4 class="font-bold tracking-tight text-gray-900 dark:text-white text-base truncate w-full min-w-0">
                    {{ $title }}
                </h4>
            @endif
        </div>

        <!-- Body Content Snippet (Raw Inline CSS 110px Fixed Height Box - Bypasses Tailwind Compilation) -->
        @if ($body)
            <div
                class="w-full min-w-0 break-words whitespace-normal rounded-lg border border-gray-200/80 dark:border-gray-700/60 bg-white/80 dark:bg-gray-900/70 p-3 mt-2 text-xs leading-relaxed text-gray-700 dark:text-gray-200"
                style="height: 110px; min-height: 110px; max-height: 110px; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none;"
            >
                {{ $body }}
            </div>
        @endif
    </div>

    <!-- Footer Action Button -->
    <div class="mt-4 w-full">
        <!-- Selected State Button -->
        <div
            x-show="isCardSelected"
            x-cloak
            class="w-full rounded-lg py-2 text-center text-xs font-extrabold text-white shadow-2xs"
            style="background-color: {{ $selectedBtnBg }};"
        >
            Selected
        </div>

        <!-- Unselected State Button (High Contrast Light & Dark Mode) -->
        <div
            x-show="! isCardSelected"
            class="w-full rounded-lg py-2 text-center text-xs font-bold transition duration-150 shadow-2xs hover:bg-gray-50 dark:bg-gray-800/90 dark:hover:bg-gray-700/70 dark:text-[var(--unselected-btn-text-dark)] dark:border-[var(--unselected-btn-border-dark)]"
            style="
                border: 1.5px solid {{ $unselectedBtnBorder }};
                color: {{ $unselectedBtnText }};
                background-color: rgba(255, 255, 255, 0.9);
                --unselected-btn-text-dark: {{ $accentDarkRgb }};
                --unselected-btn-border-dark: {{ $accentDarkRgb }};
            "
        >
            Select
        </div>
    </div>
</div>
