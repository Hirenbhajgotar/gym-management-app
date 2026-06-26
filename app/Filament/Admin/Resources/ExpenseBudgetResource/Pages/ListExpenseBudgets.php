<?php

namespace App\Filament\Admin\Resources\ExpenseBudgetResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\ExpenseBudgetResource;
use Filament\Resources\Pages\ListRecords;

class ListExpenseBudgets extends ListRecords
{
    protected static string $resource = ExpenseBudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('phosphor-plus')
                ->label('New Budget'),
        ];
    }
}
