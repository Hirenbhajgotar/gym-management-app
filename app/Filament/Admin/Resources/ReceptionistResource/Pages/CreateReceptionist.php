<?php

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use App\Filament\Admin\Resources\ReceptionistResource;
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

class CreateReceptionist extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ReceptionistResource::class;

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

    protected function handleRecordCreation(array $data): Model
    {
        $plainPassword = $data['password'];

        $userData = collect($data)->except(['passwordConfirmation', 'employeeDetails', 'address'])->toArray();

        // Create the user first
        $receptionist = User::create($userData);

        // Create employee details if provided
        if (isset($data['employeeDetails']) && is_array($data['employeeDetails'])) {
            $employeeDetailsData = $data['employeeDetails'];
            $employeeDetailsData['user_id'] = $receptionist->id;

            EmployeeDetail::create($employeeDetailsData);
        }

        // Assign role
        $receptionist->assignRole('receptionist');

        $currentTenant = Filament::getTenant();
        if ($currentTenant) {
            $receptionist->gyms()->attach($currentTenant->id, [
                'role' => 'receptionist',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Send welcome email notification
        $receptionist->notify(new ReceptionistCreate($receptionist, $plainPassword));

        return $receptionist;
    }

    protected function getRedirectUrl(): string
    {
        return ReceptionistResource::getUrl('index'); // Redirect to index page after create
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Receptionist Created')
            ->body('A new receptionist has been created and assigned to the current gym.')
            ->success()
            ->send();
    }
}
