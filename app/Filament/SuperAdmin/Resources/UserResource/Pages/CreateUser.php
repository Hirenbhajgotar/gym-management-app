<?php

namespace App\Filament\SuperAdmin\Resources\UserResource\Pages;

use App\Filament\SuperAdmin\Resources\UserResource;
use App\Models\User;
use App\Notifications\UserActionNotification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // Notify admin if new user is a gym_owner
        /** @var User $user */
        $user = $this->record;
        if ($user->hasRole('gym_owner')) {
            $admins = User::role('super_admin')->get();
            $details = 'Plan End Date: Not applicable'; // Add plan end date logic if available
            $admins->each(fn (User $admin) => $admin->notify(new UserActionNotification('created', $user, $details)));
        }
    }
}
