<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LeadResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Illuminate\Http\RedirectResponse;
use App\Notifications\Member\WelcomeNotification;
use Filament\Actions\DeleteAction;
use App\Enums\LeadStatus;
use App\Filament\Admin\Resources\LeadResource;
use App\Models\Lead;
use App\Services\LeadConversionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Notification as FacadesNotification;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getRedirectUrl(): string
    {
        return LeadResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->icon('phosphor-eye'),

            Action::make('convertToMember')
                ->label('Convert to Member')
                ->color('success')
                ->icon('phosphor-user-plus')
                ->form(LeadResource::getConvertToMemberFormSchema())
                ->action(function (array $data, Lead $record): RedirectResponse {

                    $member = resolve(LeadConversionService::class)->convert($record, $data);

                    FacadesNotification::send($member, new WelcomeNotification($member));

                    Notification::make()
                        ->title('Lead converted successfully')
                        ->success()
                        ->send();

                    return redirect(static::getResource()::getUrl('index'));
                })
                ->visible(fn (Lead $record) => $record->status !== LeadStatus::CONVERTED),

            DeleteAction::make(),

        ];
    }
}
