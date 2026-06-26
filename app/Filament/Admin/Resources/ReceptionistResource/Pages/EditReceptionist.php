<?php

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Models\User;
use App\Filament\Admin\Resources\ReceptionistResource;
use App\Models\EmployeeDetail;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditReceptionist extends EditRecord
{
    use HasWizard;

    protected static string $resource = ReceptionistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->icon('heroicon-o-eye'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Wizard::make(ReceptionistResource::getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getSubmitFormAction())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(true),
            ])
            ->columns(null);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /**
         * Load the employee details and address data
         *
         * @var User $user
         */
        $user = $this->record;

        if ($employeeDetails = $user->employeeDetails) {
            $data['employeeDetails'] = $employeeDetails->toArray();
        }

        if ($address = $user->address) {
            $data['address'] = $address->toArray();
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

        // Update the main user record
        $record->update($userData);

        // Update employee details
        if (isset($data['employeeDetails']) && is_array($data['employeeDetails'])) {
            if ($record->employeeDetails) {
                $record->employeeDetails->update($data['employeeDetails']);
            } else {
                $data['employeeDetails']['user_id'] = $record->id;
                EmployeeDetail::query()->create($data['employeeDetails']);
            }
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return ReceptionistResource::getUrl('index'); // Redirect to index page after create
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Receptionist Updated')
            ->body('A receptionist has been updated.')
            ->success()
            ->send();
    }
}
