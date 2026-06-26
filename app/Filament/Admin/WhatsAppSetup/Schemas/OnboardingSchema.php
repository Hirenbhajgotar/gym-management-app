<?php

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\HtmlString;

class OnboardingSchema
{
    public static function make($page): array
    {
        return [
            Section::make('WhatsApp Business Integration')
                ->description('Connect your gym with members through WhatsApp')
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
