<?php

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use App\Enums\DefaultRole;
use App\Enums\UserStatus;
use App\Filament\Admin\Resources\ReceptionistResource;
use App\Models\EmployeeDetail;
use App\Models\User;
use App\Notifications\ReceptionistCreate;
use App\Services\AvatarService;
use App\Traits\HasTenantAccess;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReceptionist extends CreateRecord
{
    use HasTenantAccess, HasWizard;

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

        $data['email_verified_at'] = $data['email_verified_at'] ? now() : null;

        // Activate account on creation
        $data['status'] = UserStatus::ACTIVE;

        if (is_null($data['profile_photo']) || blank($data['profile_photo'])) {
            $data['profile_photo'] = resolve(AvatarService::class)->generate($data['name']);
        }

        $userData = collect($data)->except(['passwordConfirmation', 'employeeDetails', 'address'])->toArray();

        // Create the user first
        $receptionist = User::create($userData);

        // Create employee details if provided
        if (isset($data['employeeDetails']) && is_array($data['employeeDetails'])) {
            $employeeDetailsData = $data['employeeDetails'];
            $employeeDetailsData['user_id'] = $receptionist->id;

            EmployeeDetail::create($employeeDetailsData);
        }

        $receptionistRole = DefaultRole::RECEPTIONIST->value;

        // Assign role
        $receptionist->assignRole($receptionistRole);

        $currentTenant = $this->getGym();
        if ($currentTenant) {
            $receptionist->gyms()->attach($currentTenant->id, [
                'role' => $receptionistRole,
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
