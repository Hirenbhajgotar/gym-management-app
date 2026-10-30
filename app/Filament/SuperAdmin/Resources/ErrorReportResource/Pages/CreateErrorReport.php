<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;

use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateErrorReport extends CreateRecord
{
    protected static string $resource = ErrorReportResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The operator form does not expose occurrence metadata; the record
        // would otherwise violate the non-null `occurred_at` column.
        $data['occurred_at'] ??= now();
        $data['last_occurrence'] ??= now();
        $data['user_id'] ??= auth()->id();

        return $data;
    }
}
