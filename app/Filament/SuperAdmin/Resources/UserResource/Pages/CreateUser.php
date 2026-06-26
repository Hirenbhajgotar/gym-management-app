<?php

namespace App\Filament\SuperAdmin\Resources\UserResource\Pages;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;
use App\Notifications\GymOwnerWelcomeNotification;
use Spatie\Permission\Models\Role;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Enums\DefaultRole;
use App\Filament\SuperAdmin\Resources\UserResource;
use App\Helpers\FeedbackHelper;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\UserActionNotification;
use App\Services\RolesAndPermissionsService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?array $gymData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If password is not provided, generate a random one
        if (blank($data['password'])) {
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

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = parent::handleRecordCreation($data);

            // Get the role from form data
            // Note: $data here is the mutated data, but 'role' key should persist if it was present
            $role = $data['role'] ?? DefaultRole::GYM_OWNER->value;

            // If gym_owner, create gym and associate
            if ($role === DefaultRole::GYM_OWNER->value) {
                $this->setupGymOwner($user);
            }

            // If email_verified_at was NOT set by the admin (meaning it's null),
            // we fire the Registered event to send the verification email.
            // If it WAS set, the model already has it saved, so we don't need to do anything.
            if (! $user->hasVerifiedEmail()) {
                event(new Registered($user));
            }

            return $user;
        });
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        try {
            // Send welcome notification
            $this->sendWelcomeNotification($user);

            // Notify SuperAdmins about new gym owner
            // $this->notifySuperAdmins($user);

            // Store feedback cache
            FeedbackHelper::storeFeedbackCache($user?->id, 15);

        } catch (\Exception $e) {
            // Notifications failing shouldn't rollback the user creation,
            // but we'll log it and notify admin in UI
            Notification::make()
                ->danger()
                ->title('Error Sending Notifications')
                ->body($e->getMessage())
                ->send();
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

        if (filled($this->gymData['address'])) {
            $gym->address()->create($this->gymData['address']);
        }

        // Create default roles and permissions for the gym
        resolve(RolesAndPermissionsService::class)->createDefaultTenantRolesAndAssignPermissions($user, $gym);

        // Assign the gym to the user with proper role
        resolve(RolesAndPermissionsService::class)->assignRoleToTenant($gym, $user, DefaultRole::GYM_OWNER);

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
        $token = Password::broker()->createToken($user);

        // Send welcome email with password reset link
        $user->notify(new GymOwnerWelcomeNotification(
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

    protected function grantAllPermissionsToGymOwner(User $user, Gym $gym): void
    {
        // Set the permission team context to the gym
        setPermissionsTeamId($gym->id);

        // Get the gym_owner role for this specific gym
        $gymOwnerRoleId = Role::where('name', DefaultRole::GYM_OWNER->value)
            ->where('gym_id', $gym->id)
            ->value('id');

        if ($gymOwnerRoleId) {
            // Get ALL permission IDs efficiently
            $allPermissionIds = Permission::pluck('id');

            $insertData = $allPermissionIds->map(fn ($permId) => [
                'permission_id' => $permId,
                'role_id' => $gymOwnerRoleId,
            ])->all();

            // Clear existing permissions first (though likely empty for new gym)
            DB::table('role_has_permissions')->where('role_id', $gymOwnerRoleId)->delete();

            // Bulk insert
            if (filled($insertData)) {
                foreach (array_chunk($insertData, 1000) as $chunk) {
                    DB::table('role_has_permissions')->insert($chunk);
                }
            }

            // Clear permission cache to ensure changes take effect immediately
            resolve(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
