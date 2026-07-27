<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use App\Filament\Admin\Pages\WhatsAppSetup;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\HtmlString;

class ErrorSchema
{
    public static function make(WhatsAppSetup $page): array
    {
        return [
            Section::make('Setup Incomplete')
                ->description('There was an issue with your WhatsApp setup.')
                ->schema([
                    Placeholder::make('error_message')->hiddenLabel()->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.error-state', ['whatsappAccount' => $page->whatsappAccount])->render())),
                    Actions::make([
                        Action::make('retry')->label('Retry Setup')->color('primary')->action('startSetupAction'),
                    ])->alignCenter(),
                ]),
        ];
    }
}
