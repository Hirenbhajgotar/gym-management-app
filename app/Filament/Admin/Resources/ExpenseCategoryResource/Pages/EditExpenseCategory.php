<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseCategoryResource\Pages;

use App\Filament\Admin\Resources\ExpenseCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExpenseCategory extends EditRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('phosphor-trash')
                ->before(function () {
                    if ($this->record->expenses()->exists()) {
                        $this->halt();
                        $this->addError('name', 'Cannot delete category with existing expenses');
                    }
                    if ($this->record->children()->exists()) {
                        $this->halt();
                        $this->addError('name', 'Cannot delete category with sub-categories');
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
