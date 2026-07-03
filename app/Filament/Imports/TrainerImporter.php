<?php

declare(strict_types=1);

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

            ImportColumn::make('name')
                ->rules(['max:255']),
            ImportColumn::make('email')
                ->rules(['email', 'max:255']),
            ImportColumn::make('phone')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('specialization')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('experience_years')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('certifications')
                ->rules(['nullable']),
            ImportColumn::make('is_available')
                ->boolean()
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('available_hours')
                ->rules(['nullable']),
            ImportColumn::make('basic_salary')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('pt_salary_per_member')
                ->label('PT Commission Rate (%)')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0', 'max:100']),
            ImportColumn::make('currency')
                ->rules(['nullable', 'max:255']),
        ];
    }

    public function resolveRecord(): ?Trainer
    {
        $trainer = Trainer::query()->firstOrNew(['email' => $this->data['email']]);

        $trainer->fill([
            'name' => $this->data['name'],
            'phone' => $this->data['phone'] ?? null,
            'specialization' => $this->data['specialization'] ?? null,
            'experience_years' => $this->data['experience_years'] ?? null,
            'certifications' => $this->data['certifications'] ?? null,
            'is_available' => $this->data['is_available'] ?? null,
            'available_hours' => $this->data['available_hours'] ?? null,
            'basic_salary' => $this->data['basic_salary'] ?? null,
            'pt_salary_per_member' => $this->data['pt_salary_per_member'] ?? null,
            'currency' => $this->data['currency'] ?? null,
            'gym_id' => $this->options['gym_id'],
        ]);

        return $trainer;
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
