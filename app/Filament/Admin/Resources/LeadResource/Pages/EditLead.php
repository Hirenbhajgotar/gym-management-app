<?php

namespace App\Filament\Admin\Resources\LeadResource\Pages;

use App\Enums\LeadStatus;
use App\Filament\Admin\Resources\LeadResource;
use App\Models\Lead;
use App\Services\LeadConversionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Notification as FacadesNotification;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),

            Actions\Action::make('convertToMember')
                ->label('Convert to Member')
                ->color('success')
                ->icon('phosphor-user-plus')
                ->form(LeadResource::getConvertToMemberFormSchema())
                ->action(function (array $data, Lead $record): void {

                    $member = app(LeadConversionService::class)->convert($record, $data);

                    FacadesNotification::send($member, new \App\Notifications\Member\WelcomeNotification($member));

                    Notification::make()
                        ->title('Lead converted successfully')
                        ->success()
                        ->send();
                })
                ->visible(fn(Lead $record) => $record->status !== LeadStatus::CONVERTED),
        ];
    }
}
