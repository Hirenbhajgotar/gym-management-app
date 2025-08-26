<?php

namespace App\Imports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithSkipDuplicates;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeImport;

abstract class BaseImporter implements ToCollection, WithBatchInserts, WithHeadingRow, WithSkipDuplicates, WithValidation, WithChunkReading, SkipsOnFailure, ShouldQueue
{
    use Importable, SkipsFailures;

    public static function beforeImport(BeforeImport $event)
    {
        // You can access the file being imported here
        // For example, you can log the file path
        // Or you can perform some checks on the file
        // before the import process starts.
        // dd($event->getReader()->getProperties()->getCreator());
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    abstract public function collection(\Illuminate\Support\Collection $rows);

    abstract public function rules(): array;
}
