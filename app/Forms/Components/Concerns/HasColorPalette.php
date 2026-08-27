<?php

declare(strict_types=1);

namespace App\Forms\Components\Concerns;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

trait HasColorPalette
{
    abstract public function getColor(): string;

    /**
     * @return array<int|string, string>
     */
    public function getPaletteForColor(?string $colorName = null): array
    {
        $colorName = $colorName ?: $this->getColor();

        /** @var array<string, mixed> $allColors */
        $allColors = FilamentColor::getColors();

        $palette = null;
        if (isset($allColors[$colorName])) {
            $palette = $allColors[$colorName];
        } else {
            $palette = match ($colorName) {
                'emerald', 'green', 'success' => Color::Emerald,
                'blue', 'sky', 'cyan', 'info' => Color::Sky,
                'amber', 'yellow', 'warning' => Color::Amber,
                'red', 'rose', 'danger' => Color::Rose,
                'indigo', 'violet', 'purple', 'primary' => Color::Indigo,
                'gray', 'slate', 'zinc' => Color::Gray,
                default => $allColors['success'] ?? $allColors['emerald'] ?? Color::Emerald,
            };
        }

        if (is_string($palette)) {
            $palette = Color::hex($palette);
        }

        /** @var array<int|string, string> $result */
        $result = is_array($palette) ? $palette : [];

        return $result;
    }
}
