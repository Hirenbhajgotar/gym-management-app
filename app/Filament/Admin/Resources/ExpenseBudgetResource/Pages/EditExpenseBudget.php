<?php

namespace App\Filament\Admin\Resources\ExpenseBudgetResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Resources\ExpenseBudgetResource;
use Filament\Resources\Pages\EditRecord;

class EditExpenseBudget extends EditRecord
{
    protected static string $resource = ExpenseBudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('phosphor-trash'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
