<?php

declare(strict_types=1);

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\Component;

/**
 * HorizontalCarousel
 *
 * A reusable layout component for Filament forms that renders child schema components
 * inside a smooth horizontal scrolling carousel with Alpine.js scroll controls.
 */
/**
 * @phpstan-consistent-constructor
 */
class HorizontalCarousel extends Component
{
    protected string $view = 'forms.components.horizontal-carousel';

    /** @var array<string, int>|int|Closure */
    protected array|int|Closure $visibleItems = [
        'default' => 1,
        'md' => 2,
        'lg' => 3,
    ];

    /**
     * @param  array<int, Component>  $schema
     */
    public static function make(array $schema = []): static
    {
        $static = new static();
        $static->schema($schema);

        return $static;
    }

    /**
     * Configure number of visible items per break-point.
     * E.g. ->visibleItems(3) or ->visibleItems(['default' => 1, 'sm' => 2, 'lg' => 3])
     *
     * @param  array<string, int>|int|Closure  $items
     */
    public function visibleItems(array|int|Closure $items): static
    {
        $this->visibleItems = $items;

        return $this;
    }

    /**
     * Alias for visibleItems.
     *
     * @param  array<string, int>|int|Closure  $items
     */
    public function itemsPerPage(array|int|Closure $items): static
    {
        return $this->visibleItems($items);
    }

    /**
     * Get resolved visible items mapping.
     *
     * @return array<string, int>
     */
    public function getVisibleItems(): array
    {
        $evaluated = $this->evaluate($this->visibleItems);

        if (is_int($evaluated)) {
            return [
                'default' => 1,
                'md' => $evaluated,
                'lg' => $evaluated,
            ];
        }

        /** @var array<string, int> $arrayEvaluated */
        $arrayEvaluated = (array) $evaluated;

        return array_merge([
            'default' => 1,
            'md' => 2,
            'lg' => 3,
        ], $arrayEvaluated);
    }
}
