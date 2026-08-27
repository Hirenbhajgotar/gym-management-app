@php
    $components = $getChildComponentContainer()->getComponents();
    $visibleItems = $getVisibleItems();

    $defaultN = (int) ($visibleItems['default'] ?? 1);
    $mdN = (int) ($visibleItems['md'] ?? $visibleItems['sm'] ?? $defaultN);
    $lgN = (int) ($visibleItems['lg'] ?? $mdN);

    // Calc width formulas (gap is 1rem / 16px)
    $wDefault = "calc((100% - " . max(0, $defaultN - 1) . "rem) / " . max(1, $defaultN) . ")";
    $wMd = "calc((100% - " . max(0, $mdN - 1) . "rem) / " . max(1, $mdN) . ")";
    $wLg = "calc((100% - " . max(0, $lgN - 1) . "rem) / " . max(1, $lgN) . ")";
@endphp

<div
    x-data="{
        scrollContainer: null,
        canScrollLeft: false,
        canScrollRight: false,
        init() {
            this.scrollContainer = $refs.container;
            this.$nextTick(() => this.checkScroll());
            this.scrollContainer.addEventListener('scroll', () => this.checkScroll());
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

    <!-- Horizontal Scroll Container (Raw Inline CSS padding-bottom: 28px for clean scrollbar separation) -->
    <div
        x-ref="container"
        class="flex items-start gap-4 overflow-x-auto pt-0.5 px-0.5 scroll-smooth snap-x snap-mandatory scrollbar-none [scrollbar-width:none] [-ms-overflow-style:none] w-full min-w-0"
        style="-webkit-overflow-scrolling: touch; padding-bottom: 28px; margin-bottom: 4px;"
    >
        @foreach ($components as $component)
            <div
                class="carousel-item-wrapper shrink-0 snap-start min-w-0"
                style="
                    --item-w-default: {{ $wDefault }};
                    --item-w-md: {{ $wMd }};
                    --item-w-lg: {{ $wLg }};
                "
            >
                {{ $component }}
            </div>
        @endforeach
    </div>
</div>

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
            width: var(--item-w-lg, 33.333%);
        }
    }
</style>
