<?php

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Notifications\MemberActionNotification;
use Exception;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditGymMember extends EditRecord
{
    protected static string $resource = GymMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function ($record) {
                    try {
                        Log::info('Member deleted successfully', [
                            'member_id' => $this->record,
                            'record' => $record,
                        ]);

                        // Notify the member if they have a user account
                        if ($this->record->user) {
                            $this->record->user->notify(new MemberActionNotification('deleted', $this->record));
                        }

                        // Notify the gym owner
                        if ($this->record->gym && $this->record->gym->owner) {
                            $this->record->gym->owner->notify(new MemberActionNotification('deleted', $this->record));
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to send member deletion notification: ' . $e->getMessage(), [
                            'member_id' => $this->record->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }),
        ];
    }
}
