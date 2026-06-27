<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ManagerResource\Pages;

use App\Filament\Admin\Resources\ManagerResource;
use App\Models\EmployeeDetail;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\EditRecord;

/**
 * Edit Manager Page.
 *
 * Handles the wizard-based editing of existing manager accounts including
 * user details, employee information, and address updates.
 */
class EditManager extends EditRecord
{
    use HasWizard;

    protected static string $resource = ManagerResource::class;

    /**
     * Configure header actions for viewing and deleting managers.
     *
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->icon('heroicon-o-eye'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    /**
     * Configure the form with a wizard interface.
     */
    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Wizard::make(ManagerResource::getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getSubmitFormAction())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(true),
            ])
            ->columns(null);
    }

    /**
     * Mutate form data before filling the form with existing record data.
     *
     * Loads employee details and address data from relationships.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->record;

        // Load employee details if available
        if ($employeeDetails = $user->employeeDetails) {
            $data['employeeDetails'] = $employeeDetails->toArray();
        }

        // Load address if available
        if ($address = $user->address) {
            $data['address'] = $address->toArray();
        }

        return $data;
    }

    /**
     * Handle the update of an existing manager record.
     *
     * Updates user data, employee details, and address information.
     *
     * @param  User  $record  The manager user model
     * @param  array<string, mixed>  $data  Form data
     * @return User The updated manager user model
     */
    protected function handleRecordUpdate($record, array $data): User
    {
        // Update main user record
        $this->updateUserData($record, $data);

        // Update employee details
        $this->updateEmployeeDetails($record, $data);

        // Update address
        $this->updateAddress($record, $data);

        return $record->refresh();
    }

    /**
     * Update main user data excluding nested relationships.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateUserData(User $record, array $data): void
    {
        // Handle email verification
        $data['email_verified_at'] = $data['email_verified_at'] ? now() : null;

        $userData = collect($data)
            ->except(['passwordConfirmation', 'password', 'employeeDetails', 'address'])
            ->toArray();

        // Only update password if a new value is provided
        if (filled($data['password'] ?? null)) {
            $userData['password'] = $data['password'];
        }

        $record->update($userData);
    }

    /**
     * Update or create employee details for the manager.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateEmployeeDetails(User $record, array $data): void
    {
        $employeeData = $data['employeeDetails'] ?? null;

        if (! is_array($employeeData) || blank($employeeData)) {
            return;
        }

        /**
         * @var EmployeeDetail $employeeDetails
         */
        $employeeDetails = $record->employeeDetails;
        if ($employeeDetails) {
            $employeeDetails->update($employeeData);
        } else {
            EmployeeDetail::query()->create([
                ...$employeeData,
                'user_id' => $record->id,
            ]);
        }
    }

    /**
     * Update or create address for the manager.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateAddress(User $record, array $data): void
    {
        $addressData = $data['address'] ?? null;

        if (! is_array($addressData) || blank($addressData)) {
            return;
        }

        if ($record->address) {
            $record->address->update($addressData);
        } else {
            $record->address()->create($addressData);
        }
    }

    /**
     * Redirect to the manager list after successful update.
     */
    protected function getRedirectUrl(): string
    {
        return ManagerResource::getUrl('index');
    }

    /**
     * Display a success notification after manager update.
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Manager Updated')
            ->body('The manager has been successfully updated.')
            ->success()
            ->send();
    }
}
