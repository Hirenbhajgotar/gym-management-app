<?php

namespace App\Filament\SuperAdmin\Resources\RequestContactResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Resources\RequestContactResource;
use App\Models\RequestContact;
use Filament\Resources\Pages\ViewRecord;

class ViewRequestContact extends ViewRecord
{
    protected static string $resource = RequestContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsContacted')
                ->label('Mark as Contacted')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn (RequestContact $record): bool => ! $record->is_contacted)
                ->form([
                    Textarea::make('admin_notes')
                        ->label('Notes (optional)')
                        ->rows(3),
                ])
                ->action(function (RequestContact $record, array $data): void {
                    $record->markAsContacted($data['admin_notes'] ?? null);
                    $this->refreshFormData(['is_contacted', 'contacted_at', 'admin_notes']);
                })
                ->successNotificationTitle('Marked as contacted'),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
