<?php

namespace App\Filament\Admin\Resources\LeadResource\Pages;

use App\Enums\LeadStatus;
use App\Filament\Admin\Resources\LeadResource;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\LeadConversion;
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
                    // 1. Create the GymMember
                    $gymMember = GymMember::create([
                        'gym_id' => $record->gym_id,
                        'name' => $record->name,
                        'email' => $record->email,
                        'phone' => $record->phone,
                        'membership_plan_id' => $data['membership_plan_id'],
                        'joined_at' => $data['conversion_date'],
                    ]);

                    // 2. Create the LeadConversion record
                    LeadConversion::create([
                        'lead_id' => $record->id,
                        'gym_member_id' => $gymMember->id,
                        'membership_plan_id' => $data['membership_plan_id'],
                        'conversion_date' => $data['conversion_date'],
                        'notes' => $data['notes'],
                    ]);

                    // 3. Update the lead's status
                    $record->status = LeadStatus::CONVERTED;
                    $record->save();

                    Notification::make()
                        ->title('Lead converted successfully')
                        ->success()
                        ->send();
                })
                ->visible(fn (Lead $record) => $record->status !== LeadStatus::CONVERTED),
        ];
    }
}
