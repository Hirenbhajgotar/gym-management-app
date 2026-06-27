<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Enums\ExpenseStatus;
use App\Filament\Admin\Resources\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('phosphor-plus')
                ->label('New Expense'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('phosphor-list'),

            'pending' => Tab::make('Pending Approval')
                ->icon('phosphor-hourglass')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ExpenseStatus::PENDING))
                ->badge(fn () => $this->getModel()::query()
                    ->where('gym_id', Filament::getTenant()?->id)
                    ->where('status', ExpenseStatus::PENDING)
                    ->count() ?: null)
                ->badgeColor('warning'),

            'approved' => Tab::make('Approved')
                ->icon('phosphor-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ExpenseStatus::APPROVED)),

            'paid' => Tab::make('Paid')
                ->icon('phosphor-coins')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ExpenseStatus::PAID)),

            'rejected' => Tab::make('Rejected')
                ->icon('phosphor-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ExpenseStatus::REJECTED)),

            'draft' => Tab::make('Drafts')
                ->icon('phosphor-note-pencil')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ExpenseStatus::DRAFT)),
        ];
    }
}
