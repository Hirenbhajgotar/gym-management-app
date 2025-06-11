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
        if ($this->record->hasRole('gym_owner')) {
            $admin = User::role('super_admin')->get();
            $details = 'Plan End Date: Not applicable'; // Add plan end date logic if available
            $admin->notify(new UserActionNotification('created', $this->record, $details));
            // foreach ($admins as $admin) {
            // }
        }
    }
}
