<?php

namespace App\Filament\Admin\Resources\ManagerResource\Pages;

use App\Filament\Admin\Resources\ManagerResource;
use App\Models\EmployeeDetail;
use Filament\Actions;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditManager extends EditRecord
{
    use HasWizard;

    protected static string $resource = ManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon('heroicon-o-eye'),
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Wizard::make(ManagerResource::getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getSubmitFormAction())
                    ->skippable($this->hasSkippableSteps())
                    // ->skippable(true)
                    ->contained(true),
            ])
            ->columns(null);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load the employee details and address data
        $user = $this->record;

        if ($user->employeeDetails) {
            $data['employeeDetails'] = $user->employeeDetails->toArray();
        }

        if ($user->address) {
            $data['address'] = $user->address->toArray();
        }

        return $data;
    }

    protected function handleRecordUpdate($record, array $data): Model
    {
        // Remove password confirmation and nested relationship data from main update
        $userData = collect($data)->except(['passwordConfirmation', 'password',  'employeeDetails', 'address'])->toArray();

        // Update password only if a new value is provided
        if (filled($data['password'])) {
            $userData['password'] = $data['password'];
        }

        // dd($userData);
        // Update the main user record
        $record->update($userData);

        // Update employee details
        if (isset($data['employeeDetails']) && is_array($data['employeeDetails'])) {
            if ($record->employeeDetails) {
                $record->employeeDetails->update($data['employeeDetails']);
            } else {
                $data['employeeDetails']['user_id'] = $record->id;
                EmployeeDetail::create($data['employeeDetails']);
            }
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return ManagerResource::getUrl('index'); // Redirect to index page after create
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Manager Updated')
            ->body('A manager has been updated.')
            ->success()
            ->send();
    }
}
