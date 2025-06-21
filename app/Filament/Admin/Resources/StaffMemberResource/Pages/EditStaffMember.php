<?php

namespace App\Filament\Admin\Resources\StaffMemberResource\Pages;

use App\Filament\Admin\Resources\StaffMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaffMember extends EditRecord
{
    protected static string $resource = StaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        // Update password only if a new value is provided
        if (filled($data['password'])) {
            $record->update(['password' => $data['password']]);
        }

        // Update gyms relationship
        if (isset($data['gyms'])) {
            $record->gyms()->syncWithPivotValues($data['gyms'], ['active' => false]);
            if (!empty($data['gyms'])) {
                $record->gyms()->updateExistingPivot($data['gyms'][0], ['active' => true]);
            }
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return StaffMemberResource::getUrl('index'); // Redirect to index page after create
    }
}
