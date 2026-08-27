<?php

declare(strict_types=1);

namespace App\Forms\Components;

use App\Forms\Components\Concerns\HasColorPalette;
use Closure;
use Filament\Forms\Components\Field;

/**
 * SummaryStatCard
 *
 * A reusable, dynamic summary stat card placeholder field for Filament forms.
 *
 * Usage examples:
 *
 * 1. Bar-separated string in state/stats:
 * ```php
 * SummaryStatCard::make('summary')
 *     ->icon('heroicon-o-user-group')
 *     ->color('success')
 *     ->stats('Total Filtered Members: 1,240 | Expected Cost: $12.40');
 * ```
 *
 * 2. Key-value or array stats:
 * ```php
 * SummaryStatCard::make('summary')
 *     ->icon('heroicon-o-user-group')
 *     ->color('success')
 *     ->stats([
 *         'Total Filtered Members' => '1,240',
 *         'Expected Cost' => '$12.40',
 *     ]);
 * ```
 *
 * 3. Fluent stat methods:
 * ```php
 * SummaryStatCard::make('summary')
 *     ->icon('heroicon-o-user-group')
 *     ->color('success')
 *     ->stat('Total Filtered Members', '1,240')
 *     ->stat('Expected Cost', '$12.40');
 * ```
 */
class SummaryStatCard extends Field
{
    use HasColorPalette;

    protected string $view = 'forms.components.summary-stat-card';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }

    /** Dynamic icon for the main card banner (e.g. 'heroicon-o-user-group'). */
    protected string|Closure|null $icon = null;

    /** Dynamic color scheme ('success', 'primary', 'warning', 'danger', 'info', 'gray'). */
    protected string|Closure|null $color = 'success';

    /** Card stats array, bar-separated string, or Closure returning stats. */
    /** @var array<int|string, mixed>|string|Closure|null */
    protected array|string|Closure|null $stats = null;

    /** Items added fluently via ->stat(). */
    /** @var array<int, array<string, mixed>> */
    protected array $addedStats = [];

    /** Dynamic divider character, icon name, bool, or Closure (e.g. '|', '/', '•', 'heroicon-o-chevron-right', false). */
    protected string|bool|Closure|null $divider = '|';

    /**
     * Set a dynamic icon for the card container.
     */
    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the Filament color theme.
     * Options: 'success', 'primary', 'warning', 'danger', 'info', 'gray'.
     */
    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Set stat items via array, bar-separated string, or Closure.
     *
     * @param  array<int|string, mixed>|string|Closure|null  $stats
     */
    public function stats(array|string|Closure|null $stats): static
    {
        $this->stats = $stats;

        return $this;
    }

    /**
     * Add an individual stat item fluently.
     */
    public function stat(
        string|Closure $label,
        mixed $value,
        string|Closure|null $color = null,
        string|Closure|null $icon = null
    ): static {
        $this->addedStats[] = [
            'label' => $label,
            'value' => $value,
            'color' => $color,
            'icon' => $icon,
        ];

        return $this;
    }

    /**
     * Set the divider between stat items.
     * Accepts:
     * - '|' or 'line' or true (vertical divider line, default)
     * - string character e.g. '/', '•', '-'
     * - icon name e.g. 'heroicon-o-chevron-right'
     * - false (no divider)
     */
    public function divider(string|bool|Closure|null $divider): static
    {
        $this->divider = $divider;

        return $this;
    }

    /**
     * Get the resolved card icon.
     */
    public function getIcon(): ?string
    {
        $icon = $this->evaluate($this->icon);

        return is_string($icon) && filled($icon) ? $icon : null;
    }

    /**
     * Get the resolved divider.
     */
    public function getDivider(): string|bool
    {
        $resolved = $this->evaluate($this->divider);

        if ($resolved === null || $resolved === true) {
            return '|';
        }

        return is_string($resolved) || is_bool($resolved) ? $resolved : '|';
    }

    /**
     * Get the resolved color theme.
     */
    public function getColor(): string
    {
        $color = $this->evaluate($this->color);

        return is_string($color) && filled($color) ? $color : 'success';
    }

    /**
     * Get resolved stats array containing normalized stat items.
     *
     * @return array<int, array{label: string, value: string, icon: string|null, color: string|null}>
     */
    public function getStats(): array
    {
        $stats = [];

        // 1. Process fluently added stats
        foreach ($this->addedStats as $item) {
            $labelVal = $this->evaluate($item['label'] ?? '');
            $valVal = $this->evaluate($item['value'] ?? '');
            $iconVal = isset($item['icon']) ? $this->evaluate($item['icon']) : null;
            $colorVal = isset($item['color']) ? $this->evaluate($item['color']) : null;

            $stats[] = [
                'label' => is_scalar($labelVal) ? (string) $labelVal : '',
                'value' => is_scalar($valVal) ? (string) $valVal : '',
                'icon' => is_scalar($iconVal) ? (string) $iconVal : null,
                'color' => is_scalar($colorVal) ? (string) $colorVal : null,
            ];
        }

        // 2. Process configured stats property
        $configuredStats = $this->evaluate($this->stats);
        if (filled($configuredStats)) {
            $stats = array_merge($stats, $this->parseStatsInput($configuredStats));
        }

        // 3. Fallback to state if no explicit stats property/method used
        if (blank($stats)) {
            $state = $this->getState();
            if (filled($state)) {
                $stats = $this->parseStatsInput($state);
            }
        }

        /** @var array<int, array{label: string, value: string, icon: string|null, color: string|null}> $stats */
        return $stats;
    }

    /**
     * Parse mixed stats input (array or string) into normalized stat items array.
     *
     * @return array<int, array{label: string, value: string, icon: string|null, color: string|null}>
     */
    protected function parseStatsInput(mixed $input): array
    {
        if (is_string($input)) {
            return $this->parseBarSeparatedString($input);
        }

        if (is_array($input)) {
            $parsed = [];
            foreach ($input as $key => $value) {
                if (is_array($value)) {
                    $lbl = $value['label'] ?? $value['name'] ?? (is_int($key) ? '' : $key);
                    $val = $value['value'] ?? $value['state'] ?? '';
                    $icn = isset($value['icon']) ? $value['icon'] : null;
                    $clr = isset($value['color']) ? $value['color'] : null;

                    $parsed[] = [
                        'label' => is_scalar($lbl) ? (string) $lbl : '',
                        'value' => is_scalar($val) ? (string) $val : '',
                        'icon' => is_scalar($icn) ? (string) $icn : null,
                        'color' => is_scalar($clr) ? (string) $clr : null,
                    ];
                } elseif (is_string($value) && str_contains($value, '|')) {
                    $parsed = array_merge($parsed, $this->parseBarSeparatedString($value));
                } else {
                    $label = is_int($key) ? '' : $key;
                    $valStr = is_scalar($value) ? (string) $value : '';

                    if (blank($label) && str_contains($valStr, ':')) {
                        [$label, $valStr] = explode(':', $valStr, 2);
                    }

                    $parsed[] = [
                        'label' => trim($label),
                        'value' => trim($valStr),
                        'icon' => null,
                        'color' => null,
                    ];
                }
            }

            return $parsed;
        }

        return [];
    }

    /**
     * Parse a bar-separated string like "Total Filtered Members: 1,240 | Expected Cost: $12.40".
     *
     * @return array<int, array{label: string, value: string, icon: string|null, color: string|null}>
     */
    protected function parseBarSeparatedString(string $input): array
    {
        $results = [];
        $segments = array_filter(array_map('trim', explode('|', $input)), fn ($item) => $item !== '');

        foreach ($segments as $segment) {
            if (str_contains($segment, ':')) {
                [$label, $val] = explode(':', $segment, 2);
                $results[] = [
                    'label' => trim($label),
                    'value' => trim($val),
                    'icon' => null,
                    'color' => null,
                ];
            } elseif (str_contains($segment, '=>')) {
                [$label, $val] = explode('=>', $segment, 2);
                $results[] = [
                    'label' => trim($label),
                    'value' => trim($val),
                    'icon' => null,
                    'color' => null,
                ];
            } else {
                $results[] = [
                    'label' => '',
                    'value' => trim($segment),
                    'icon' => null,
                    'color' => null,
                ];
            }
        }

        return $results;
    }
}
