<?php

namespace App\Imports;

use EightyNine\ExcelImport\EnhancedDefaultImport;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
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

    // Property to be defined in child classes
    public $importModel;

    public function __construct()
    {
        // Ensure the importModel property is defined in the child class
        if (!isset($this->importModel)) {
            throw new \Exception('The $importModel property must be defined in the child import class: ' . static::class);
        }

        // Verify the model class exists
        if (!class_exists($this->importModel)) {
            throw new \Exception("Model class {$this->importModel} does not exist in " . static::class);
        }

        parent::__construct($this->importModel, []); // Pass model and attributes to parent

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

    public function collection(Collection $collection)
    {
        // Filter out empty rows
        $filteredCollection = $collection->filter(function ($row) {
            return !empty(array_filter($row->toArray()));
        });

        $this->beforeCollection($filteredCollection);

        foreach ($filteredCollection as $row) {
            $data = $row->toArray();
            try {
                $this->processRow($data);
                $this->successfulRows++;
                Log::info('Row imported successfully.', ['row' => $data]);
            } catch (\Exception $e) {
                $this->failedRows++;
                $failedRow = $data;
                $failedRow['error'] = $e->getMessage();
                $this->failedData[] = $failedRow;
                FailedImportRow::create([
                    'import_id' => $this->importRecord->id,
                    'data' => json_encode($failedRow),
                    'validation_error' => $e->getMessage(),
                ]);
            }
            $this->importRecord->update([
                'processed_rows' => $this->successfulRows + $this->failedRows,
                'successful_rows' => $this->successfulRows,
            ]);
        }

        $this->afterCollection($filteredCollection);
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

        Excel::store(new class($this->failedData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            protected $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                if (empty($this->data)) {
                    return [];
                }
                $keys = array_keys($this->data[0]);
                return $keys; // Includes 'error' column
            }
        }, $filePath, 'public');

        $this->failedFilePath = $filePath;
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

    protected function sendNotification(int $totalRows)
    {
        $successfulRows = number_format($this->successfulRows) . ' ' . Str::plural('row', $this->successfulRows);
        $message = "Your import has completed and {$successfulRows} imported.";
        $recipient = auth()->user();

        if ($this->failedRows > 0) {
            $failedRows = number_format($this->failedRows) . ' ' . Str::plural('row', $this->failedRows);
            $message .= " {$failedRows} failed to import.";

            $downloadUrl = route('filament.failed-import.download', [
                'importId' => $this->importRecord?->id,
            ]);

            Notification::make()
                ->danger()
                ->title('Import Completed')
                ->body($message)
                ->actions([
                    Action::make('download_failed_rows')
                        ->label('Download information about the failed row')
                        ->url($downloadUrl)
                        ->openUrlInNewTab()
                        ->markAsRead()
                        ->color('danger'),
                ])
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        } else {
            Notification::make()
                ->danger()
                ->title('Import Completed')
                ->body($message)
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        }
    }
}
