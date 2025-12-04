<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ManagerResource\Pages;

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\ManagerResource;
use App\Models\EmployeeDetail;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\ManagerCreate;
use App\Services\AvatarService;
use App\Traits\HasTenantAccess;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Create Manager Page.
 *
 * Handles the wizard-based creation of new manager accounts with
 * employee details, role assignment, and gym association.
 */
class CreateManager extends CreateRecord
{
    use HasTenantAccess, HasWizard;

    protected static string $resource = ManagerResource::class;

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
     * Redirect to the manager list after successful creation.
     */
    protected function getRedirectUrl(): string
    {
        return ManagerResource::getUrl('index');
    }

    /**
     * Display a success notification after manager creation.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Manager Created')
            ->body('A new manager has been created and assigned to the current gym.')
            ->success()
            ->send();
    }

    /**
     * Handle the creation of a new manager record.
     *
     * @param  array<string, mixed>  $data  Form data
     * @return Model The created manager user model
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Preserve plain password for notification before hashing
        $plainPassword = $data['password'];

        // Prepare manager user data
        $userData = $this->prepareUserData($data);

        // Create the manager user
        $manager = User::create($userData);

        // Create associated employee details if provided
        $this->createEmployeeDetails($manager, $data);

        // Assign manager role to user
        $this->assignManagerRole($manager);

        // Associate manager with current gym tenant
        $this->attachToGym($manager);

        // Send welcome notification with credentials
        $this->sendWelcomeNotification($manager, $plainPassword);

        return $manager;
    }

    /**
     * Prepare user data by filtering and transforming form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareUserData(array $data): array
    {
        // Handle email verification
        $data['email_verified_at'] = $data['email_verified_at'] ? now() : null;

        // Generate avatar if not provided
        if (empty($data['profile_photo'])) {
            $data['profile_photo'] = $this->generateAvatar($data['name']);
        }

        // Remove non-user fields from the data
        return collect($data)
            ->except(['passwordConfirmation', 'employeeDetails', 'address'])
            ->toArray();
    }

    /**
     * Generate an avatar for the manager using the AvatarService.
     */
    private function generateAvatar(string $name): string
    {
        return app(AvatarService::class)->generate($name);
    }

    /**
     * Create employee details for the manager if provided.
     *
     * @param  array<string, mixed>  $data
     */
    private function createEmployeeDetails(User $manager, array $data): void
    {
        $employeeData = $data['employeeDetails'] ?? null;

        if (! is_array($employeeData) || empty($employeeData)) {
            return;
        }

        EmployeeDetail::create([
            ...$employeeData,
            'user_id' => $manager->id,
        ]);
    }

    /**
     * Assign the manager role to the user.
     */
    private function assignManagerRole(User $manager): void
    {
        $manager->assignRole(DefaultRole::MANAGER->value);
    }

    /**
     * Attach the manager to the current gym tenant.
     */
    private function attachToGym(User $manager): void
    {
        $gym = $this->getGym();

        if (! $gym instanceof Gym) {
            return;
        }

        $manager->gyms()->attach($gym->id, [
            'role' => DefaultRole::MANAGER->value,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Send welcome notification to the newly created manager.
     */
    private function sendWelcomeNotification(User $manager, string $plainPassword): void
    {
        $manager->notify(new ManagerCreate($manager, $plainPassword));
    }
}
