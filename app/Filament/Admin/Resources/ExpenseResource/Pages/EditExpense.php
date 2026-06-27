<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Filament\Admin\Resources\ExpenseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeSave(): void
    {
        // Ensure expense can still be edited
        if (! $this->record->canEdit()) {
            $this->halt();
        }
    }
}
