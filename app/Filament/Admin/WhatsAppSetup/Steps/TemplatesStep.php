<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use App\Filament\Admin\Pages\WhatsAppSetup;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

class TemplatesStep
{
    public static function make(WhatsAppSetup $page): Step
    {
        return Step::make('Templates')
            ->visible(fn () => ! $page->isExistWhatsAppTemplates)
            ->schema([
                Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.templates-info')->render())),
                Actions::make([
                    Action::make('createTemplates')
                        ->label('Create Templates')
                        ->color('primary')
                        ->link()
                        ->url(self::templateResourceRoute($page))
                        ->openUrlInNewTab(true)
                        ->disabled(fn () => $page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview()),
                ]),
            ]);
    }

    private static function templateResourceRoute(WhatsAppSetup $page): string
    {
        return route('filament.admin.resources.whats-app-templates.index', ['tenant' => $page->getTenantSlug()]);
    }
}
