<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;

use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateErrorReport extends CreateRecord
{
    protected static string $resource = ErrorReportResource::class;
}
