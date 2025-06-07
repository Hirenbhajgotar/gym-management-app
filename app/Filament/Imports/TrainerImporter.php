<?php

namespace App\Filament\Imports;

use App\Models\Trainer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class TrainerImporter extends Importer
{
    protected static ?string $model = Trainer::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('gym')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('name')
                ->rules(['max:255']),
            ImportColumn::make('email')
                ->rules(['email', 'max:255']),
            ImportColumn::make('phone')
                ->rules(['max:255']),
            ImportColumn::make('specialization')
                ->rules(['max:255']),
            ImportColumn::make('experience_years')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('certifications'),
            ImportColumn::make('is_available')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('available_hours'),
            ImportColumn::make('basic_salary')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('pt_salary_per_member')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('currency')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): ?Trainer
    {
        // return Trainer::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new Trainer();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your trainer import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
