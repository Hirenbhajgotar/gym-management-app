<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class ActiveSchema
{
    public static function make(WhatsAppSetup $page): array
    {
        return [
            Section::make('WhatsApp Business Status')
                ->description('Your WhatsApp Business integration is active.')
                ->schema([
                    Grid::make(2)->schema([
                        Placeholder::make('status')->label('Account Status')->content(fn () => self::getActivationBadge($page)),
                        Placeholder::make('verification_status')->label('Verification Status')->content(fn () => self::getVerificationBadge($page)),
                        Placeholder::make('phone_number')->label('WhatsApp Number')->content($page->phoneNumber?->phone_number ?? 'N/A'),
                        Placeholder::make('account_created')->label('Account Created')->content($page->whatsappAccount?->created_at?->format('M d, Y g:i A') ?? 'N/A'),
                        Placeholder::make('credits')->label('Message Credits')->content(($page->creditBalance?->remaining_credits ?? 0) . ' remaining'),
                        Placeholder::make('billing_mode_display')
                            ->label('Billing Mode')
                            ->content(fn () => $page->whatsappAccount?->billing_mode?->getLabel() ?? 'N/A')
                            ->visible(fn () => $page->whatsappAccount?->provider_type === WhatsAppProviderTypeEnum::WABA),
                    ]),
                ]),
        ];
    }

    private static function getVerificationBadge(WhatsAppSetup $page): HtmlString
    {
        $status = $page->verificationStatus;

        if (! $status) {
            return new HtmlString('<span class="text-gray-500">Unknown</span>');
        }

        $color = $status->getColor();

        $icon = $status->getIcon();

        return new HtmlString(Blade::render("
            <span class=\"inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-sm font-medium bg-{$color}-100 dark:bg-{$color}-900/30 text-{$color}-800 dark:text-{$color}-200\">
                <span>
                <x-{$icon} class='w-5 h-5' /></span>
                <span>{$status->getLabel()}</span>
            </span>
        "));
    }

    private static function getActivationBadge(WhatsAppSetup $page): HtmlString
    {
        $status = $page->whatsappAccount?->status;

        if (! $status) {
            return new HtmlString('<span class="text-gray-500">Unknown</span>');
        }

        $color = $status->getColor();

        $icon = $status->getIcon();

        return new HtmlString(Blade::render("
            <span class=\"inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-sm font-medium bg-{$color}-100 dark:bg-{$color}-900/30 text-{$color}-800 dark:text-{$color}-200\">
                <span><x-{$icon} class='w-5 h-5' /></span>
                <span>{$status->getLabel()}</span>
            </span>
        "));
    }
}
