<?php

namespace App\Filament\Admin\Resources\ExpenseBudgetResource\Pages;

use App\Filament\Admin\Resources\ExpenseBudgetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseBudget extends CreateRecord
{
    protected static string $resource = ExpenseBudgetResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
