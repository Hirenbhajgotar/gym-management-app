<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

class SetupSchema
{
    public static function make($page): array
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
