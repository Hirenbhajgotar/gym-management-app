<?php

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

class WelcomeCreditsStep
{
    public static function make($page): Step
    {
        return Step::make('Welcome Credits')
            ->schema([
                Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.welcome-credits')->render())),
                Actions::make([
                    Action::make('claimWelcome')
                        ->label($page->welcomeCreditsClaimed ? 'Credits Claimed' : 'Claim Welcome Credits')
                        ->color('success')
                        ->action('claimWelcomeCreditsAction')
                        ->disabled($page->welcomeCreditsClaimed || ($page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview()))
                        ->icon($page->welcomeCreditsClaimed ? 'heroicon-o-check-circle' : null),
                ])->alignCenter(),
            ]);
    }
}
