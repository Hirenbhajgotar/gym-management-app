<?php

namespace App\Imports;

use EightyNine\ExcelImport\EnhancedDefaultImport;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class BaseExcelImport extends EnhancedDefaultImport implements ToCollection, WithHeadingRow
{
    protected $successfulRows = 0;
    protected $failedRows = 0;
    protected $failedData = [];
    protected $importRecord;
    protected $failedFilePath;
    protected $expectedHeaders = [];

    public function __construct()
    {
        parent::__construct(static::class, []); // Pass model and attributes to parent

        // Create an import record in Filament's imports table
        $this->importRecord = Import::create([
            'completed_at' => null,
            'file_name' => 'import_' . now()->timestamp . '.xlsx',
            'file_path' => 'temp', // Will update later
            'importer' => static::class,
            'processed_rows' => 0,
            'total_rows' => 0,
            'successful_rows' => 0,
            'user_id' => auth()->id(),
        ]);
    }

    // Override beforeCollection to validate headers and set total rows
    protected function beforeCollection(Collection $collection): void
    {
        // Validate headers if expectedHeaders are defined
        if (!empty($this->expectedHeaders)) {
            $this->validateHeaders($this->expectedHeaders, $collection);
        }

        // Update total rows in the import record
        $this->importRecord->update([
            'total_rows' => $collection->count(),
        ]);
    }

    // Override beforeCreateRecord to handle row processing with try-catch
    protected function beforeCreateRecord(array $data, $row): void
    {
        try {
            // Process the row using the abstract method
            $this->processRow($row);

            $this->successfulRows++;
            Log::info('Row imported successfully.', ['row' => $data]);
        } catch (\Exception $e) {
            $this->failedRows++;
            $this->failedData[] = $data;

            // Store failed row in Filament's failed_import_rows table
            FailedImportRow::create([
                'import_id' => $this->importRecord->id,
                'data' => json_encode($data),
                'validation_error' => $e->getMessage(),
            ]);

            Log::error('Failed to import row.', [
                'row' => $data,
                'error' => $e->getMessage(),
            ]);

            // Throw exception to prevent default creation by EnhancedDefaultImport
            throw $e;
        }

        // Update processed rows
        $this->importRecord->update([
            'processed_rows' => $this->successfulRows + $this->failedRows,
            'successful_rows' => $this->successfulRows,
        ]);
    }

    // Override afterCreateRecord (optional, can be used by child classes)
    protected function afterCreateRecord(array $data, $row): void
    {
        // Child classes can override this if needed
    }

    // Override afterCollection to handle failed rows and notifications
    protected function afterCollection(Collection $collection): void
    {
        // Generate failed rows Excel file if there are any
        if ($this->failedRows > 0) {
            $this->generateFailedRowsExcel();
        }

        // Update import record as completed
        $this->importRecord->update([
            'completed_at' => now(),
            'file_path' => $this->failedFilePath ?? 'N/A',
        ]);

        // Send notification
        $this->sendNotification($collection->count());
    }

    // Abstract method for child classes to implement row processing
    abstract protected function processRow($row);

    // Method to set expected headers (to be called by child classes)
    protected function setExpectedHeaders(array $headers)
    {
        $this->expectedHeaders = $headers;
    }

    protected function generateFailedRowsExcel()
    {
        $fileName = 'failed_rows_' . $this->importRecord->id . '_' . now()->timestamp . '.xlsx';
        $filePath = 'failed_imports/' . $fileName;

        // Generate Excel file using Maatwebsite Excel
        Excel::store(new class($this->failedData) implements \Maatwebsite\Excel\Concerns\FromArray {
            protected $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }
        }, $filePath, 'public');

        $this->failedFilePath = $filePath;
    }

    protected function sendNotification(int $totalRows)
    {
        $successfulRows = number_format($this->successfulRows) . ' ' . Str::plural('row', $this->successfulRows);
        $message = "Your import has completed and {$successfulRows} imported.";

        if ($this->failedRows > 0) {
            $failedRows = number_format($this->failedRows) . ' ' . Str::plural('row', $this->failedRows);
            $downloadUrl = Storage::disk('public')->url($this->failedFilePath);
            $message .= " {$failedRows} failed to import. <a href='{$downloadUrl}' target='_blank'>Download failed rows</a>.";
        }

        Notification::make()
            ->success()
            ->title('Import Completed')
            ->body($message)
            ->persistent()
            ->send();
    }
}
