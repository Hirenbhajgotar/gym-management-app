<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppBillingModeEnum: string implements HasColor, HasDescription, HasLabel
{
    case SELF_MANAGED = 'self_managed';
    case PAY_VIA_APP = 'pay_via_app';

    public function getLabel(): string
    {
        return match ($this) {
            self::SELF_MANAGED => 'Self Managed',
            self::PAY_VIA_APP => 'Pay via App',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SELF_MANAGED => 'gray',
            self::PAY_VIA_APP => 'success',
        };
    }

    public function getDescription(): string
    {
        $appName = ucfirst(config()->string('app.name', 'Fluxfitness'));

        return match ($this) {
            self::SELF_MANAGED => 'You manage billing directly with Meta. No credits will be deducted by ' . $appName . '.',
            self::PAY_VIA_APP => $appName . ' handles billing. Credits will be deducted for each message sent.',
        };
    }

    /**
     * Get an array of cases with their descriptions
     *
     * @return array<string, string>
     */
    public static function casesToDescriptionsArray(): array
    {
        return [
            self::SELF_MANAGED->value => self::SELF_MANAGED->getDescription(),
            self::PAY_VIA_APP->value => self::PAY_VIA_APP->getDescription(),
        ];
    }
}
