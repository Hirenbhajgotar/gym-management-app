<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\User;
use EightyNine\ExcelImport\EnhancedDefaultImport;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

abstract class BaseExcelImport extends EnhancedDefaultImport implements ShouldQueue, ToCollection, WithChunkReading, WithHeadingRow
{
    protected $successfulRows = 0;

    protected $failedRows = 0;

    protected $failedData = [];

    protected $importRecord;

    protected $failedFilePath;

    protected $expectedHeaders = [];

    // Property to be defined in child classes
    public $importModel;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        // Ensure the importModel property is defined in the child class
        if (! isset($this->importModel)) {
            throw new \Exception("The $this->importModel property must be defined in the child import class: " . static::class);
        }

        // Verify the model class exists
        if (! class_exists($this->importModel)) {
            throw new \Exception("Model class {$this->importModel} does not exist in " . static::class);
        }

        parent::__construct($this->importModel); // Pass model and attributes to parent

        Notification::make()
            ->info()
            ->title('Import Started')
            ->body('Your import is being processed in the background. You will be notified once it is complete.')
            ->send();

        // Create an import record in Filament's imports table
        $this->importRecord = Import::create([
            'completed_at' => null,
            'file_name' => 'import_' . now()->timestamp . '.xlsx',
            'file_path' => 'temp',
            'importer' => static::class,
            'processed_rows' => 0,
            'total_rows' => 0,
            'successful_rows' => 0,
            'user_id' => auth()->id(),
        ]);
    }

    public function collection(Collection $collection): void
    {
        // Filter out empty rows
        $filteredCollection = $collection->filter(function ($row) {
            return filled(array_filter($row->toArray()));
        });

        $this->beforeCollection($filteredCollection);

        foreach ($filteredCollection as $row) {
            $data = $row->toArray();

            // Validate the row using the rules defined in the child class
            $validator = Validator::make($data, $this->rules());
            if ($validator->fails()) {
                $this->failedRows++;
                $failedRow = $data;
                $failedRow['error'] = implode(', ', $validator->errors()->all());
                $this->failedData[] = $failedRow;
                FailedImportRow::create([
                    'import_id' => $this->importRecord->id,
                    'data' => json_encode($failedRow),
                    'validation_error' => $failedRow['error'],
                ]);
            } else {
                try {
                    $this->processRow($data);
                    $this->successfulRows++;
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
            }

            $this->importRecord->update([
                'processed_rows' => $this->successfulRows + $this->failedRows,
                'successful_rows' => $this->successfulRows,
            ]);
        }

        $this->afterCollection($filteredCollection);
    }

    public function chunkSize(): int
    {
        return 1000; // Process 1000 rows at a time
    }

    // Implement WithValidation's rules method
    public function rules(): array
    {
        // // Child classes must implement this method
        // throw new \Exception('The rules() method must be implemented in the child import class: ' . static::class);
        return [];
    }

    // Override beforeCollection to validate headers and set total rows
    protected function beforeCollection(Collection $collection): void
    {
        // Validate headers if expectedHeaders are defined
        if (filled($this->expectedHeaders)) {
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
    protected function setExpectedHeaders(array $headers): void
    {
        $this->expectedHeaders = $headers;
    }

    protected function generateFailedRowsExcel(): void
    {
        $fileName = 'failed_rows_' . $this->importRecord->id . '_' . now()->timestamp . '.xlsx';
        $filePath = 'failed_imports/' . $fileName;

        Excel::store(new class($this->failedData) implements FromArray, WithHeadings
        {
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
                if (blank($this->data)) {
                    return [];
                }

                return array_keys($this->data[0]); // Includes 'error' column
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
        $this->sendNotification();
    }

    protected function sendNotification()
    {
        $successfulRows = number_format($this->successfulRows) . ' ' . Str::plural('row', $this->successfulRows);
        $message = "Your import has completed and {$successfulRows} imported.";

        // Retrieve the user using the user_id from the Import record
        $recipient = User::find($this->importRecord->user_id);

        if (! $recipient) {
            Log::warning('User not found for import notification.', [
                'import_id' => $this->importRecord->id,
                'user_id' => $this->importRecord->user_id,
            ]);

            return;
        }

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
        } else {
            Notification::make()
                ->danger()
                ->title('Import Completed')
                ->body($message)
                ->sendToDatabase($recipient)
                ->send();
        }
        event(new DatabaseNotificationsSent($recipient));
    }

    public function failed(\Throwable $exception)
    {
        if ($this->importRecord) {
            $this->importRecord->update([
                'completed_at' => now(),
                'file_path' => 'N/A',
            ]);
        }

        // Retrieve the user using the user_id from the Import record
        $recipient = User::find($this->importRecord->user_id);

        if ($recipient) {

            Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body('An error occurred while processing your import: ' . $exception->getMessage())
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        } else {
            Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body('An error occurred while processing your import: ' . $exception->getMessage())
                ->send();

            Log::warning('User not found for import failure notification.', [
                'import_id' => $this->importRecord->id,
                'user_id' => $this->importRecord->user_id,
            ]);
        }
    }
}
