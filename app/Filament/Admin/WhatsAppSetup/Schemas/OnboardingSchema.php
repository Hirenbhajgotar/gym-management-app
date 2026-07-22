<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use App\Filament\Admin\Pages\WhatsAppSetup;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\HtmlString;

class OnboardingSchema
{
    public static function make(WhatsAppSetup $page): array
    {
        return [
            Section::make('')
                ->schema([
                    Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.onboarding-hero')->render())),
                    Actions::make([
                        Action::make('start_setup')
                            ->label('Start Setup')
                            ->color('primary')
                            ->size('lg')
                            ->icon('heroicon-o-rocket-launch')
                            ->action('startSetupAction'),
                    ])->alignCenter(),
                ]),
        ];
    }
}
