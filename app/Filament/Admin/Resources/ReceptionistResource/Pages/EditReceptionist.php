<?php

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use App\Filament\Admin\Resources\ReceptionistResource;
use App\Models\EmployeeDetail;
use Filament\Actions;
use Filament\Forms\Components\Section;
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
            Actions\DeleteAction::make(),
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
            $userData['password'] =  $data['password'];
        }

        if (isset($data['email_verified']) && $data['email_verified']) {
            $userData['email_verified_at'] =  now();
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
        return ReceptionistResource::getUrl('index'); // Redirect to index page after create
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Receptionist Updated')
            ->body('A receptionist has been updated.')
            ->success()
            ->send();
    }
}
