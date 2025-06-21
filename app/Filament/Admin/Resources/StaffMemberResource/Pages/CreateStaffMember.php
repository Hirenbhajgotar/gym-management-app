<?php

namespace App\Filament\Admin\Resources\StaffMemberResource\Pages;

use App\Filament\Admin\Resources\StaffMemberResource;
use App\Models\StaffMember;
use App\Notifications\StaffMemberCreate;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffMember extends CreateRecord
{
    protected static string $resource = StaffMemberResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $plainPassword = $data['password'];

        $staffMember = StaffMember::create($data);

        if (!empty($data['gyms'])) {
            $staffMember->gyms()->syncWithPivotValues($data['gyms'], ['active' => false]);
            $staffMember->gyms()->updateExistingPivot($data['gyms'][0], ['active' => true]);
        }

        // Send welcome email notification
        $staffMember->notify(new StaffMemberCreate($staffMember, $plainPassword));


        return $staffMember;
    }

    protected function getRedirectUrl(): string
    {
        return StaffMemberResource::getUrl('index'); // Redirect to index page after create
    }
}
