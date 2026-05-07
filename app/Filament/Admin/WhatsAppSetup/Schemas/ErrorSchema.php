<?php

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\HtmlString;

class ErrorSchema
{
    public static function make($page): array
    {
        return [
            Section::make('Setup Incomplete')
                ->description('There was an issue with your WhatsApp setup.')
                ->schema([
                    Placeholder::make('error_message')->hiddenLabel()->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.error-state'))),
                    \Filament\Forms\Components\Actions::make([
                        Action::make('retry')->label('Retry Setup')->color('primary')->action('startSetupAction'),
                    ])->alignCenter(),
                ]),
        ];
    }
}
