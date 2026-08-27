@php
    $statePath = $getStatePath();
    $name = $getName();
    $templates = $getTemplates();
    $hasError = $errors->has($statePath) || $errors->has($name) || $errors->has("data.{$name}") || $errors->has("data.{$statePath}");

    $visibleItems = $getVisibleItems();
    $defaultN = (int) ($visibleItems['default'] ?? 1);
    $mdN = (int) ($visibleItems['md'] ?? 2);
    $lgN = (int) ($visibleItems['lg'] ?? $mdN);

    // Calc width formulas (gap is 1rem / 16px)
    $wDefault = "calc((100% - " . max(0, $defaultN - 1) . "rem) / " . max(1, $defaultN) . ")";
    $wMd = "calc((100% - " . max(0, $mdN - 1) . "rem) / " . max(1, $mdN) . ")";
    $wLg = "calc((100% - " . max(0, $lgN - 1) . "rem) / " . max(1, $lgN) . ")";
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    class="w-full min-w-0"
>
    <div class="w-full min-w-0">
        <div
            x-data="{
                scrollContainer: null,
                canScrollLeft: false,
                canScrollRight: false,
                init() {
                    this.scrollContainer = $refs.container;
                    this.$nextTick(() => this.checkScroll());
                    if (this.scrollContainer) {
                        this.scrollContainer.addEventListener('scroll', () => this.checkScroll());
                    }
                    window.addEventListener('resize', () => this.checkScroll());
                },
                checkScroll() {
                    if (!this.scrollContainer) return;
                    this.canScrollLeft = this.scrollContainer.scrollLeft > 10;
                    this.canScrollRight = Math.ceil(this.scrollContainer.scrollLeft + this.scrollContainer.clientWidth) < (this.scrollContainer.scrollWidth - 10);
                },
                scrollLeft() {
                    if (this.scrollContainer) {
                        const amount = this.scrollContainer.clientWidth;
                        this.scrollContainer.scrollBy({ left: -amount, behavior: 'smooth' });
                    }
                },
                scrollRight() {
                    if (this.scrollContainer) {
                        const amount = this.scrollContainer.clientWidth;
                        this.scrollContainer.scrollBy({ left: amount, behavior: 'smooth' });
                    }
                }
            }"
            class="relative group/carousel w-full py-1"
        >
            <!-- Carousel Navigation Header Control Bar -->
            <div class="flex items-center justify-between gap-2 mb-2 px-0.5">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Scroll or swipe to explore templates
                </div>

                <!-- Prev / Next Navigation Buttons -->
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        x-show="canScrollLeft"
                        x-cloak
                        @click="scrollLeft()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 active:scale-95 transition shadow-2xs"
                        aria-label="Previous Template"
                        title="Previous"
                    >
                        <x-filament::icon icon="heroicon-o-chevron-left" class="h-4 w-4" />
                    </button>

                    <button
                        type="button"
                        x-show="canScrollRight"
                        x-cloak
                        @click="scrollRight()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 active:scale-95 transition shadow-2xs"
                        aria-label="Next Template"
                        title="Next"
                    >
                        <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Horizontal Scroll Container -->
            <div
                x-ref="container"
                class="flex items-start gap-4 overflow-x-auto pt-0.5 px-0.5 scroll-smooth snap-x snap-mandatory scrollbar-none [scrollbar-width:none] [-ms-overflow-style:none] w-full min-w-0"
                style="-webkit-overflow-scrolling: touch; padding-bottom: 28px; margin-bottom: 4px;"
            >
                @foreach ($templates as $template)
                    @php
                        $id = (string) ($template['id'] ?? $template->id ?? '');
                        $title = (string) ($template['display_name'] ?? $template['name'] ?? 'Template');
                        $body = (string) ($template['body'] ?? '');
                        $categoryEnum = isset($template['category']) ? \App\Enums\TemplateCategoryEnum::tryFrom(is_string($template['category']) ? $template['category'] : ($template['category']->value ?? '')) : null;
                        $badge = $categoryEnum?->getLabel();
                        $color = $categoryEnum?->getColor() ?? 'primary';

                        $palette = \Filament\Support\Colors\Color::Emerald;
                        if ($color === 'info') $palette = \Filament\Support\Colors\Color::Blue;
                        elseif ($color === 'warning') $palette = \Filament\Support\Colors\Color::Amber;
                        elseif ($color === 'danger') $palette = \Filament\Support\Colors\Color::Red;
                        elseif ($color === 'secondary') $palette = \Filament\Support\Colors\Color::Gray;

                        $accentRgb = "rgb(" . ($palette[600] ?? $palette[500] ?? '22, 163, 74') . ")";
                        $accentDarkRgb = "rgb(" . ($palette[400] ?? $palette[300] ?? '74, 222, 128') . ")";

                        $unselectedBorderLight = $hasError ? "rgb(239, 68, 68)" : "rgba(" . ($palette[300] ?? '167, 243, 208') . ", 0.7)";
                        $unselectedBorderDark = $hasError ? "rgb(248, 113, 113)" : "rgba(" . ($palette[600] ?? '5, 150, 105') . ", 0.45)";
                        $unselectedBgLight = $hasError ? "rgba(254, 242, 242, 0.6)" : "rgba(" . ($palette[50] ?? '240, 253, 244') . ", 0.35)";
                        $unselectedBgDark = $hasError ? "rgba(69, 10, 10, 0.4)" : "rgba(31, 41, 55, 0.9)";

                        $selectedBorder = $hasError ? "rgb(239, 68, 68)" : "rgb(" . ($palette[600] ?? $palette[500] ?? '22, 163, 74') . ")";
                        $selectedBgLight = "rgba(" . ($palette[100] ?? '220, 252, 231') . ", 0.75)";
                        $selectedBgDark = "rgba(" . ($palette[950] ?? '6, 78, 59') . ", 0.65)";

                        $selectedBtnBg = "rgb(" . ($palette[600] ?? $palette[700] ?? '22, 163, 74') . ")";
                        $unselectedBtnBorder = $hasError ? "rgb(239, 68, 68)" : "rgb(" . ($palette[600] ?? $palette[500] ?? '22, 163, 74') . ")";
                        $unselectedBtnText = $hasError ? "rgb(220, 38, 38)" : "rgb(" . ($palette[700] ?? $palette[600] ?? '21, 128, 61') . ")";

                        $badgeBgLight = "rgba(" . ($palette[100] ?? '220, 252, 231') . ", 0.95)";
                        $badgeBgDark = "rgba(" . ($palette[900] ?? '6, 78, 59') . ", 0.85)";
                        $badgeTextLight = "rgb(" . ($palette[800] ?? '22, 101, 52') . ")";
                        $badgeTextDark = "rgb(" . ($palette[300] ?? '134, 239, 172') . ")";
                    @endphp

                    <div
                        class="carousel-item-wrapper shrink-0 snap-start min-w-0"
                        style="
                            --item-w-default: {{ $wDefault }};
                            --item-w-md: {{ $wMd }};
                            --item-w-lg: {{ $wLg }};
                        "
                    >
                        <div
                            x-data="{
                                value: $wire.entangle('{{ $statePath }}'),
                                get isCardSelected() {
                                    return String(this.value ?? '') === String(@js($id));
                                }
                            }"
                            @click="value = @js($id); $wire.set('{{ $statePath }}', @js($id))"
                            class="group relative flex w-full min-w-0 flex-col justify-between rounded-xl p-4 transition-all duration-200 cursor-pointer select-none box-border"
                            :class="isCardSelected ? 'shadow-md dark:bg-[var(--card-selected-bg-dark)]' : 'shadow-2xs dark:bg-[var(--card-unselected-bg-dark)] hover:shadow-xs'"
                            :style="isCardSelected
                                ? 'border: 2px solid {{ $selectedBorder }}; background-color: {{ $selectedBgLight }}; --card-selected-bg-dark: {{ $selectedBgDark }}; min-width: 0; width: 100%;'
                                : 'border: 1.5px solid {{ $unselectedBorderLight }}; background-color: {{ $unselectedBgLight }}; --card-unselected-bg-dark: {{ $unselectedBgDark }}; border-color: var(--unselected-border-dark, {{ $unselectedBorderLight }}); --unselected-border-dark: {{ $unselectedBorderDark }}; min-width: 0; width: 100%;'"
                        >
                            <!-- Accessible Hidden Radio Input -->
                            <input
                                type="radio"
                                name="{{ $getName() }}"
                                value="{{ $id }}"
                                :checked="isCardSelected"
                                class="sr-only"
                            />

                            <div class="space-y-2.5 w-full min-w-0">
                                <!-- Header Row: Category Badge & Checkmark -->
                                <div class="flex items-center justify-between gap-2 min-h-[22px] w-full min-w-0">
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
                                    <h4 class="font-bold tracking-tight text-gray-900 dark:text-white text-base truncate w-full min-w-0">
                                        {{ $title }}
                                    </h4>
                                </div>

                                <!-- Body Content Snippet -->
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
                                <div
                                    x-show="isCardSelected"
                                    x-cloak
                                    class="w-full rounded-lg py-2 text-center text-xs font-extrabold text-white shadow-2xs"
                                    style="background-color: {{ $selectedBtnBg }};"
                                >
                                    Selected
                                </div>

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
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-dynamic-component>

<style>
    .carousel-item-wrapper {
        width: var(--item-w-default, 100%);
        flex-shrink: 0;
        min-width: 0;
    }
    @media (min-width: 768px) {
        .carousel-item-wrapper {
            width: var(--item-w-md, 50%);
        }
    }
    @media (min-width: 1024px) {
        .carousel-item-wrapper {
            width: var(--item-w-lg, 50%);
        }
    }
</style>
