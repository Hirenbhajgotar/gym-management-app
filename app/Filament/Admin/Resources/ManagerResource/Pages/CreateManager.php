<?php

namespace App\Filament\Admin\Resources\ManagerResource\Pages;

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\ManagerResource;
use App\Models\EmployeeDetail;
use App\Models\User;
use App\Notifications\ReceptionistCreate;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateManager extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ManagerResource::class;

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

    protected function getRedirectUrl(): string
    {
        return ManagerResource::getUrl('index'); // Redirect to index page after create
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Manager Created')
            ->body('A new manager has been created and assigned to the current gym.')
            ->success()
            ->send();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $plainPassword = $data['password'];

        $userData = collect($data)->except(['passwordConfirmation', 'employeeDetails', 'address'])->toArray();

        if (isset($data['email_verified']) && $data['email_verified']) {
            $userData['email_verified_at'] =  now();
        }

        // Create the user first
        $manager = User::create($userData);

        // Create employee details if provided
        if (isset($data['employeeDetails']) && is_array($data['employeeDetails'])) {
            $employeeDetailsData = $data['employeeDetails'];
            $employeeDetailsData['user_id'] = $manager->id;

            EmployeeDetail::create($employeeDetailsData);
        }

        $managerRole = DefaultRole::MANAGER->value ?? 'manager';

        // Assign role
        $manager->assignRole($managerRole);

        $currentTenant = Filament::getTenant();
        if ($currentTenant) {
            $manager->gyms()->attach($currentTenant->id, [
                'role' => $managerRole,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Send welcome email notification
        $manager->notify(new ReceptionistCreate($manager, $plainPassword));

        return $manager;
    }
}
