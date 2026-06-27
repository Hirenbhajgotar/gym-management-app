<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseCategoryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\ExpenseCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListExpenseCategories extends ListRecords
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('phosphor-plus')
                ->label('New Category'),
        ];
    }
}
