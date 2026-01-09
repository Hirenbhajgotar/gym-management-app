<?php

namespace App\Filament\SuperAdmin\Resources\UserResource\Pages;

use App\Enums\DefaultRole;
use App\Filament\SuperAdmin\Resources\UserResource;
use App\Helpers\FeedbackHelper;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\UserActionNotification;
use App\Services\RolesAndPermissionsService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?array $gymData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If password is not provided, generate a random one
        if (empty($data['password'])) {
            $data['password'] = Str::password(12);
        }

        // Extract Gym Data
        if (isset($data['gym_name'])) {
            $this->gymData['name'] = $data['gym_name'];
            unset($data['gym_name']);
        }

        if (isset($data['address'])) {
            $this->gymData['address'] = $data['address'];
            unset($data['address']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        // Get the role from form data
        $role = $this->data['role'] ?? DefaultRole::GYM_OWNER->value;

        try {
            DB::beginTransaction();

            // If gym_owner, create gym and associate
            if ($role === DefaultRole::GYM_OWNER->value) {
                $this->setupGymOwner($user);
            }

            // Mark email as verified for SuperAdmin-created users
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            DB::commit();

            // Send welcome notification
            $this->sendWelcomeNotification($user);

            // Notify SuperAdmins about new gym owner
            $this->notifySuperAdmins($user);

            // Store feedback cache
            FeedbackHelper::storeFeedbackCache($user?->id, 15);

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->danger()
                ->title('Error Creating Gym Owner')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    protected function setupGymOwner(User $user): void
    {
        // Create gym for the owner
        $gymName = $this->gymData['name'] ?? "{$user->name}'s Gym";
        $slug = Gym::generateUniqueSlug($gymName);

        $gym = Gym::create([
            'name' => $gymName,
            'slug' => $slug,
        ]);

        if (! empty($this->gymData['address'])) {
            $gym->address()->create($this->gymData['address']);
        }

        // Create default roles and permissions for the gym
        app(
            RolesAndPermissionsService::class,
        )->createDefaultTenantRolesAndAssignPermissions($user, $gym);

        // Assign the gym to the user with proper role
        app(RolesAndPermissionsService::class)->assignRoleToTenant(
            $gym,
            $user,
            DefaultRole::GYM_OWNER,
        );

        // Associate user with gym through pivot table
        $gym->users()->attach($user->id, [
            'role' => DefaultRole::GYM_OWNER->value,
            'active' => true,
        ]);

        // Grant ALL permissions to gym_owner for their gym
        // This ensures they have full access to all resources
        $this->grantAllPermissionsToGymOwner($user, $gym);
    }

    protected function sendWelcomeNotification(User $user): void
    {
        // Generate password reset token
        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);

        // Send welcome email with password reset link
        $user->notify(new \App\Notifications\GymOwnerWelcomeNotification(
            $user,
            $token
        ));
    }

    protected function notifySuperAdmins(User $user): void
    {
        $admins = User::role('super_admin')->get();
        $details = "New gym owner created: {$user->name}\nEmail: {$user->email}";

        $admins->each(fn (User $admin) => $admin->notify(new UserActionNotification('created', $user, $details)));
    }

    /**
     * Grant all available permissions to the gym owner.
     * This ensures they have full access to all resources including Manager, Receptionist, Membership, etc.
     */
    protected function grantAllPermissionsToGymOwner(User $user, Gym $gym): void
    {
        // Set the permission team context to the gym
        setPermissionsTeamId($gym->id);

        // Get the gym_owner role for this specific gym
        $gymOwnerRole = \Spatie\Permission\Models\Role::where('name', DefaultRole::GYM_OWNER->value)
            ->where('gym_id', $gym->id)
            ->first();

        if ($gymOwnerRole) {
            // Get ALL permissions available in the system
            $allPermissions = \App\Models\Permission::all();

            // Assign all permissions to the gym_owner role
            $gymOwnerRole->syncPermissions($allPermissions);

            // Clear permission cache to ensure changes take effect immediately
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
