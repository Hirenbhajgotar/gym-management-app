<?php

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use App\Filament\Admin\Resources\ReceptionistResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class EditReceptionist extends EditRecord
{
    protected static string $resource = ReceptionistResource::class;

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

        Notification::make()
            ->title('Receptionist Updated')
            ->body('The receptionist details have been updated.')
            ->success()
            ->send();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return ReceptionistResource::getUrl('index'); // Redirect to index page after create
    }
}
