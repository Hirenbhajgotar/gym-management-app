<?php

namespace App\Filament\Admin\Resources\LeadResource\Pages;

use App\Enums\LeadStatus;
use App\Filament\Admin\Resources\LeadResource;
use App\Models\Lead;
use App\Services\LeadConversionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

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
                ->form([
                    Forms\Components\Select::make('membership_plan_id')
                        ->relationship('gymMember.membershipPlan', 'name') // Adjusted relationship path
                        ->required(),
                    Forms\Components\DatePicker::make('conversion_date')
                        ->default(now())
                        ->required(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Conversion Notes'),
                ])
                ->action(function (array $data, Lead $record): void {

                    $member = app(LeadConversionService::class)->convert($record, $data);

                    $member->notify(new \App\Notifications\Member\WelcomeNotification($member));

                    Notification::make()
                        ->title('Lead converted successfully')
                        ->success()
                        ->send();
                })
                ->visible(fn (Lead $record) => $record->status !== LeadStatus::CONVERTED),
        ];
    }
}
