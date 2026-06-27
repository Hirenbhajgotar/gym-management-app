<?php

declare(strict_types=1);

namespace App\Filament\Admin\Components;

use Closure;
use Filament\Forms\Components\Component;

/**
 * Reusable date presets component for quick date range selection.
 */
class DatePresets extends Component
{
    protected string $view = 'filament.components.date-presets';

    protected string $startDateField = 'start_date';

    protected string $endDateField = 'end_date';

    /**
     * Available presets configuration.
     *
     * @var array<string, array{label: string, start: Closure, end: Closure}>
     */
    protected array $presets = [];

    public static function make(): static
    {
        return resolve(static::class)
            ->configure()
            ->registerDefaultPresets();
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Register all default date presets.
     */
    protected function registerDefaultPresets(): static
    {
        $this->presets = [
            'today' => [
                'label' => 'Today',
                'icon' => 'phosphor-calendar-check',
                'start' => fn () => now()->format('Y-m-d'),
                'end' => fn () => now()->format('Y-m-d'),
            ],
            'yesterday' => [
                'label' => 'Yesterday',
                'icon' => 'phosphor-calendar-minus',
                'start' => fn () => now()->subDay()->format('Y-m-d'),
                'end' => fn () => now()->subDay()->format('Y-m-d'),
            ],
            'last_7_days' => [
                'label' => 'Last 7 Days',
                'icon' => 'phosphor-calendar',
                'start' => fn () => now()->subDays(6)->format('Y-m-d'),
                'end' => fn () => now()->format('Y-m-d'),
            ],
            'last_30_days' => [
                'label' => 'Last 30 Days',
                'icon' => 'phosphor-calendar',
                'start' => fn () => now()->subDays(29)->format('Y-m-d'),
                'end' => fn () => now()->format('Y-m-d'),
            ],
            'this_week' => [
                'label' => 'This Week',
                'icon' => 'phosphor-calendar-blank',
                'start' => fn () => now()->startOfWeek()->format('Y-m-d'),
                'end' => fn () => now()->format('Y-m-d'),
            ],
            'this_month' => [
                'label' => 'This Month',
                'icon' => 'phosphor-calendar-dots',
                'start' => fn () => now()->startOfMonth()->format('Y-m-d'),
                'end' => fn () => now()->format('Y-m-d'),
            ],
            'last_month' => [
                'label' => 'Last Month',
                'icon' => 'phosphor-calendar-x',
                'start' => fn () => now()->subMonth()->startOfMonth()->format('Y-m-d'),
                'end' => fn () => now()->subMonth()->endOfMonth()->format('Y-m-d'),
            ],
            'this_quarter' => [
                'label' => 'This Quarter',
                'icon' => 'phosphor-chart-bar',
                'start' => fn () => now()->startOfQuarter()->format('Y-m-d'),
                'end' => fn () => now()->format('Y-m-d'),
            ],
            'this_year' => [
                'label' => 'YTD',
                'icon' => 'phosphor-chart-line-up',
                'start' => fn () => now()->startOfYear()->format('Y-m-d'),
                'end' => fn () => now()->format('Y-m-d'),
            ],
            'last_year' => [
                'label' => 'Last Year',
                'icon' => 'phosphor-clock-counter-clockwise',
                'start' => fn () => now()->subYear()->startOfYear()->format('Y-m-d'),
                'end' => fn () => now()->subYear()->endOfYear()->format('Y-m-d'),
            ],
        ];

        return $this;
    }

    /**
     * Set the start date field name.
     */
    public function startDateField(string $field): static
    {
        $this->startDateField = $field;

        return $this;
    }

    /**
     * Set the end date field name.
     */
    public function endDateField(string $field): static
    {
        $this->endDateField = $field;

        return $this;
    }

    /**
     * Limit available presets.
     *
     * @param  array<string>  $keys  Preset keys to include
     */
    public function only(array $keys): static
    {
        $this->presets = array_intersect_key($this->presets, array_flip($keys));

        return $this;
    }

    /**
     * Exclude specific presets.
     *
     * @param  array<string>  $keys  Preset keys to exclude
     */
    public function except(array $keys): static
    {
        $this->presets = array_diff_key($this->presets, array_flip($keys));

        return $this;
    }

    /**
     * Get all registered presets.
     */
    public function getPresets(): array
    {
        return $this->presets;
    }

    public function getStartDateField(): string
    {
        return $this->startDateField;
    }

    public function getEndDateField(): string
    {
        return $this->endDateField;
    }
}
