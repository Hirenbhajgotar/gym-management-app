<?php

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use App\Filament\Admin\Resources\ReceptionistResource;
use App\Models\User;
use App\Notifications\ReceptionistCreate;
use App\Notifications\StaffMemberCreate;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReceptionist extends CreateRecord
{
    protected static string $resource = ReceptionistResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $plainPassword = $data['password'];

        $receptionist = User::create($data);

        $receptionist->assignRole('receptionist');

        $currentUser = auth()->user();
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


        Notification::make()
            ->title('Receptionist Created')
            ->body('A new receptionist has been created and assigned to the selected gyms.')
            ->success()
            ->send();

        return $receptionist;
    }

    protected function getRedirectUrl(): string
    {
        return ReceptionistResource::getUrl('index'); // Redirect to index page after create
    }
}
