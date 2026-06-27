<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BlogCategoryEnum: string implements HasLabel
{
    case GYM_MANAGEMENT_SOFTWARE = 'gym_management_software';
    case TIPS = 'tips';
    case FITNESS_TRENDS = 'fitness_trends';
    case BUSINESS_GROWTH = 'business_growth';
    case CASE_STUDIES = 'case_studies';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::GYM_MANAGEMENT_SOFTWARE => 'Gym Management Software',
            self::TIPS => 'Tips',
            self::FITNESS_TRENDS => 'Fitness Trends',
            self::BUSINESS_GROWTH => 'Business Growth',
            self::CASE_STUDIES => 'Case Studies',
        };
    }

    public static function toSelectArray(): array
    {
        $labels = collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->getLabel()]
        )->all();

        return array_merge(
            ['' => 'All'],
            $labels
        );
    }

    /**
     * Get all categories as an array for dropdowns.
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->getLabel()]
        )->all();
    }
}
