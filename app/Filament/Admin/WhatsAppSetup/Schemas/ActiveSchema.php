<?php

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\HtmlString;

class ActiveSchema
{
    public static function make($page): array
    {
        return [
            Section::make('WhatsApp Business Status')
                ->description('Your WhatsApp Business integration is active.')
                ->schema([
                    Grid::make(2)->schema([
                        Placeholder::make('status')->label('Account Status')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.active-status'))),
                        Placeholder::make('phone_number')->label('WhatsApp Number')->content($page->phoneNumber?->phone_number ?? 'N/A'),
                        Placeholder::make('account_created')->label('Account Created')->content($page->whatsappAccount?->created_at?->format('M d, Y g:i A') ?? 'N/A'),
                        Placeholder::make('credits')->label('Message Credits')->content(($page->creditBalance?->remaining_credits ?? 0) . ' remaining'),
                    ]),
                ]),
        ];
    }
}
