<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use App\Filament\Admin\Pages\WhatsAppSetup;

class SetupSchema
{
    public static function make(WhatsAppSetup $page): array
    {
        return match ($page->viewState) {
            'onboarding' => OnboardingSchema::make($page),
            'setup' => SetupWizardSchema::make($page),
            'active' => ActiveSchema::make($page),
            'error' => ErrorSchema::make($page),
            default => [],
        };
    }
}
