<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Filament semantic colors mapped to CSS hex values.
 *
 * Use this enum when you need to convert Filament's semantic color names
 * (used in badges, icons, etc.) to actual CSS hex color values for inline styles.
 *
 * Usage:
 *   FilamentColor::toHex('success');           // Returns '#22c55e'
 *   FilamentColor::SUCCESS->hex();             // Returns '#22c55e'
 *   FilamentColor::all();                      // Returns all colors as array
 *   FilamentColor::tryFrom('custom')?->hex(); // Returns null for unknown colors
 */
enum FilamentColor: string implements HasColor, HasLabel
{
    case GRAY = 'gray';
    case PRIMARY = 'primary';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case DANGER = 'danger';
    case INFO = 'info';

    /**
     * Get the CSS hex color value for this color.
     */
    public function hex(): string
    {
        return match ($this) {
            self::GRAY => '#6b7280',
            self::PRIMARY => '#3b82f6',
            self::SUCCESS => '#22c55e',
            self::WARNING => '#eab308',
            self::DANGER => '#ef4444',
            self::INFO => '#06b6d4',
        };
    }

    /**
     * Get the label for display purposes.
     */
    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string|array|null
    {
        return $this->value;
    }

    /**
     * Convert a semantic color name to its CSS hex value.
     * Returns gray if the color is not found.
     */
    public static function toHex(string $semanticColor): string
    {
        $color = self::tryFrom($semanticColor);

        return $color?->hex() ?? self::GRAY->hex();
    }

    /**
     * Get all colors as an array for select options.
     * Format: ['gray' => 'Gray', 'primary' => 'Primary', ...]
     */
    public static function toSelectOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $color) => [$color->value => $color->getLabel()])
            ->all();
    }

    /**
     * Get all colors with their hex values.
     * Format: ['gray' => '#6b7280', 'primary' => '#3b82f6', ...]
     */
    public static function all(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $color) => [$color->value => $color->hex()])
            ->all();
    }
}
