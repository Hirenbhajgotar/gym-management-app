<?php

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

class TemplatesStep
{
    public static function make($page): Step
    {
        return Step::make('Templates')
            ->visible(fn () => ! $page->isExistWhatsAppTemplates)
            ->schema([
                Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.templates-info'))),
                \Filament\Forms\Components\Actions::make([
                    Action::make('createTemplates')->label('Create Templates')->color('primary')
                        ->url(route('filament.admin.resources.whats-app-templates.index', ['tenant' => $page->getTenantSlug()]))
                        ->openUrlInNewTab(true)->icon('heroicon-o-check-circle')
                        ->disabled(fn () => $page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview()),
                ]),
            ]);
    }
}
