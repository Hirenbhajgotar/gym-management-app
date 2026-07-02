<?php

declare(strict_types=1);

namespace App\Imports;

use App\Events\ImportCompleted;
use App\Models\User;
use Exception;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Class BaseImporter
 *
 * This abstract class provides a base for creating importers that handle data from Excel files.
 * It includes functionality for tracking successful and failed rows, generating a report of failed rows,
 * and sending notifications upon completion.
 */
abstract class BaseImporter implements ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow
{
    use Importable, SerializesModels;

    protected int $successfulRows = 0;

    protected int $failedRows = 0;

    protected ?Import $importRecord = null;

    protected ?string $failedFilePath = null;

    /**
     * The Eloquent model class this importer writes to.
     *
     * Concrete importers MUST override this. It is used by
     * {@see \App\Filament\Actions\ImportAction} and {@see \App\Policies\ImportPolicy}
     * to resolve the import permission from the model's policy.
     */
    protected static ?string $model = null;

    /**
     * Return the Eloquent model class associated with this importer.
     */
    public static function getModel(): ?string
    {
        return static::$model;
    }

    /**
     * BaseImporter constructor.
     *
     * Initializes the import process by sending a notification to the user
     * and creating a record in the 'imports' table to track the progress of the import.
     */
    public function __construct()
    {
        Notification::make()
            ->info()
            ->title('Import Started')
            ->body('Your import is being processed in the background. You will be notified once it is complete.')
            ->send();

        $this->importRecord = Import::query()->create([
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

    /**
     * Process a single row of data from the import file.
     *
     * This method must be implemented by subclasses to define how each row of data is handled.
     *
     * @param  array  $row  The data from a single row of the import file.
     */
    abstract protected function processRow(array $row);

    /**
     * Get the validation rules that apply to the import.
     *
     * This method must be implemented by subclasses to define the validation rules for the data.
     *
     * @return array An array of validation rules.
     */
    abstract public function rules(): array;

    /**
     * Get the size of each batch for batch insertion.
     *
     * @return int The number of rows to be inserted in a single batch.
     */
    public function batchSize(): int
    {
        return 1000;
    }

    /**
     * Get the size of each chunk for chunk reading.
     *
     * @return int The number of rows to be read in a single chunk.
     */
    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * Process the collection of rows from the import file.
     *
     * This method iterates over each row in the collection, validates the data,
     * and then processes each valid row. It also handles failed validations
     * and exceptions during processing.
     *
     * @param  Collection  $collection  The collection of rows from the import file.
     */
    public function collection(Collection $collection): void
    {
        // Filter out empty rows from the collection.
        $filteredCollection = $collection->filter(function ($row) {
            // Convert row to array and remove null or empty values.
            $filteredRow = array_filter($row->toArray(), function ($value) {
                return ! is_null($value) && $value !== '';
            });

            // A row is considered not empty if it has at least one non-empty value.
            return filled($filteredRow);
        });

        // Update the total number of rows to be processed.
        $this->importRecord->update([
            'total_rows' => $filteredCollection->count(),
        ]);

        // Iterate over each row in the filtered collection.
        foreach ($filteredCollection as $row) {
            $rowData = $row->toArray();

            // Filter out null or empty values from the row data.
            $data = array_filter($rowData, function ($value) {
                return ! is_null($value) && $value !== '';
            });

            // Transform date columns before validation.
            $this->transformDates($data);

            // Validate the data against the defined rules.
            $validator = Validator::make($data, $this->rules());
            if ($validator->fails()) {
                // If validation fails, record the failed row and error message.
                $this->failedRows++;
                $failedRow = $rowData;
                $failedRow['error'] = implode(', ', $validator->errors()->all());

                FailedImportRow::query()->create([
                    'import_id' => $this->importRecord->id,
                    'data' => json_encode($failedRow),
                    'validation_error' => $failedRow['error'],
                ]);
            } else {
                try {
                    // If validation passes, process the row.
                    Log::info('Processing row: ');
                    $this->processRow($data);
                    $this->successfulRows++;
                } catch (Exception $e) {
                    // If an exception occurs during processing, record the failed row and error message.
                    Log::error('Error processing row: ', [
                        'exception' => $e,
                    ]);
                    $this->failedRows++;
                    $failedRow = $rowData;
                    $errorMessage = $this->sanitizeErrorMessage($e);
                    $failedRow['error'] = $errorMessage;

                    FailedImportRow::query()->create([
                        'import_id' => $this->importRecord->id,
                        'data' => json_encode($failedRow),
                        'validation_error' => $errorMessage,
                    ]);
                }
            }

            // Update the import record with the current progress every 100 rows to reduce database calls.
            if (($this->successfulRows + $this->failedRows) % 100 === 0) {
                $this->importRecord->update([
                    'processed_rows' => $this->successfulRows + $this->failedRows,
                    'successful_rows' => $this->successfulRows,
                ]);
            }

            // Free memory by unsetting variables
            unset($rowData, $data, $validator, $failedRow);
        }

        // Final update of the import record
        $this->importRecord->update([
            'processed_rows' => $this->successfulRows + $this->failedRows,
            'successful_rows' => $this->successfulRows,
        ]);

        // Perform actions after the collection has been processed.
        $this->afterCollection($filteredCollection);
    }

    /**
     * Transform date columns in the data array.
     *
     * This method checks for columns specified in the $dateColumns property
     * and converts Excel date serial numbers to 'Y-m-d' formatted strings.
     *
     * @param  array  $data  The data array containing the row values.
     */
    protected function transformDates(array &$data): void
    {
        if (property_exists($this, 'dateColumns')) {
            foreach ($this->dateColumns as $column) {
                if (isset($data[$column]) && is_numeric($data[$column])) {
                    $data[$column] = Date::excelToDateTimeObject($data[$column])->format('Y-m-d');
                }
            }
        }
    }

    /**
     * Sanitize an exception message for user-facing output.
     *
     * Strips raw SQL and internal system details from exceptions
     * so the failed-rows CSV only contains meaningful errors.
     */
    protected function sanitizeErrorMessage(Exception $e): string
    {
        if ($e instanceof ValidationException) {
            return $e->getMessage();
        }

        if ($e instanceof QueryException) {
            Log::warning('QueryException during import row processing', [
                'exception' => $e,
            ]);

            return 'There is an issue at DB Level, Please try again or contact support';
        }

        // Application-level exceptions from child importers are kept as-is
        // (they should already be meaningful messages like "Membership plan not found.")
        return $e->getMessage();
    }

    /**
     * Perform actions after the entire collection has been processed.
     *
     * This method is called after all rows in the collection have been processed.
     * It generates an Excel file for failed rows (if any) and sends a final notification.
     *
     * @param  Collection  $collection  The collection of rows that was processed.
     */
    protected function afterCollection(Collection $collection): void
    {
        // If there are failed rows but not too many, generate an Excel file with the details.
        if ($this->failedRows > 0 && $this->failedRows <= 1000) {
            $this->generateFailedRowsExcel();
        }

        // Mark the import as completed and update the file path for the failed rows report.
        $this->importRecord->update([
            'completed_at' => now(),
            'file_path' => $this->failedFilePath ?? 'N/A',
        ]);

        // Send a notification to the user about the import status.
        $this->sendNotification();
    }

    /**
     * Escape a single field value for safe CSV output.
     *
     * Wraps the value in double quotes if it contains a comma, double-quote,
     * or newline. Double-quotes within the value are escaped by doubling them.
     */
    private function escapeCsvField(string $value): string
    {
        //  || str_contains($value, "\n") || str_contains($value, "\r")
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * Generate a CSV file containing the rows that failed to import.
     *
     * This method creates a CSV file with the data of the failed rows and their corresponding error messages.
     * The file is stored in the 'public' disk and the path is saved for later use.
     */
    protected function generateFailedRowsExcel(): void
    {
        try {
            // Generate a unique file name and path for the failed rows report.
            $fileName = 'failed_rows_' . $this->importRecord->id . '_' . now()->timestamp . '.csv';
            $filePath = 'failed_imports/' . $fileName;

            Log::info('Generating failed rows CSV', [
                'import_id' => $this->importRecord->id,
                'file_path' => $filePath,
                'failed_rows_count' => $this->failedRows,
            ]);

            // Generate CSV content manually to avoid memory issues
            $failedRows = FailedImportRow::query()->where('import_id', $this->importRecord->id)->get();

            if ($failedRows->isEmpty()) {
                Log::info('No failed rows to export');

                return;
            }

            $csvContent = '';

            // Add headers (apply CSV escaping in case column names contain special characters)
            $firstRow = json_decode($failedRows->first()->data, true);
            $csvContent .= implode(',', array_map(
                fn (string $header): string => $this->escapeCsvField($header),
                array_keys($firstRow),
            )) . "\n";

            // Add data rows
            foreach ($failedRows as $failedRow) {
                $data = json_decode($failedRow->data, true);
                $csvContent .= implode(',', array_map(function (mixed $value): string {
                    return $this->escapeCsvField(is_scalar($value) ? (string) $value : '');
                }, $data)) . "\n";
            }

            // Write to file
            $fullPath = storage_path('app/public/' . $filePath);
            $directory = dirname($fullPath);
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($fullPath, $csvContent);
            $this->failedFilePath = $filePath;

            Log::info('Failed rows CSV generated successfully', [
                'import_id' => $this->importRecord->id,
                'file_path' => $this->failedFilePath,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to generate failed rows CSV', [
                'import_id' => $this->importRecord->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a notification to the user about the import status.
     *
     * This method constructs and sends a notification to the user who initiated the import.
     * The notification includes the number of successful and failed rows, and a link to download
     * the report of failed rows if any.
     */
    protected function sendNotification()
    {
        // Prepare the message for the notification.
        $successfulRows = number_format($this->successfulRows) . ' ' . Str::plural('row', $this->successfulRows);
        $message = "Your import has completed and {$successfulRows} imported.";

        // Find the user who initiated the import.
        $recipient = User::query()->find($this->importRecord?->user_id);

        // If the user is not found, log a warning and exit.
        if (! $recipient) {
            Log::warning('User not found for import notification.', [
                'import_id' => $this->importRecord?->id,
                'user_id' => $this->importRecord?->user_id,
            ]);

            return;
        }

        // If there are failed rows, add details to the message and provide a download link if not too many.
        if ($this->failedRows > 0) {
            $failedRows = number_format($this->failedRows) . ' ' . Str::plural('row', $this->failedRows);
            $message .= " {$failedRows} failed to import.";

            if ($this->failedRows <= 1000) {
                // Generate the URL to download the failed rows report.
                $downloadUrl = route('filament.failed-import.download', [
                    'importId' => $this->importRecord?->id,
                ]);

                // Send a danger notification with a download action.
                Notification::make()
                    ->danger()
                    ->title('Import Completed')
                    ->body($message)
                    ->actions([
                        Action::make('download_failed_rows')
                            ->label('Download Failed Rows')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->url($downloadUrl)
                            ->openUrlInNewTab()
                            ->markAsRead()
                            ->color('danger'),
                    ])
                    ->sendToDatabase($recipient)
                    ->send();
            } else {
                // Too many failed rows to generate Excel file
                $message .= ' The failed rows report is too large to generate (more than 1000 failed rows). Please check your data and try importing smaller batches or fix the validation issues first.';

                Notification::make()
                    ->danger()
                    ->title('Import Completed')
                    ->body($message)
                    ->sendToDatabase($recipient)
                    ->send();
            }
            event(new DatabaseNotificationsSent($recipient));
        } else {
            // If all rows were imported successfully, send a success notification.
            Notification::make()
                ->success()
                ->title('Import Completed')
                ->body($message)
                ->sendToDatabase($recipient)
                ->send();
            event(new DatabaseNotificationsSent($recipient));
        }

        // Dispatch the ImportCompleted event to refresh the list page.
        event(new ImportCompleted($recipient->id));
    }

    /**
     * Handle a failed import job.
     *
     * This method is called when the import job fails due to an exception.
     * It updates the import record, sends a failure notification to the user,
     * and logs the exception.
     *
     * @param  Throwable  $exception  The exception that caused the job to fail.
     */
    public function failed(Throwable $exception): void
    {
        $importId = $this->importRecord?->id;

        // If there is an import record, mark it as completed with an error status.
        if ($this->importRecord) {
            $this->importRecord->update([
                'completed_at' => now(),
                'file_path' => 'N/A',
            ]);
        }

        // Find the user who initiated the import (only if we have a record with a user_id).
        $recipient = $this->importRecord?->user_id
            ? User::query()->find($this->importRecord->user_id)
            : null;

        // If the user is found, send a failure notification.
        if ($recipient) {
            Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body('An error occurred while processing your import')
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        } else {
            // If the user is not found, log a warning.
            Log::warning('User not found for import failure notification.', [
                'import_id' => $importId,
                'user_id' => $this->importRecord?->user_id,
            ]);
        }

        // Log the exception for debugging purposes.
        Log::error('Import failed with exception: ' . $exception->getMessage(), [
            'import_id' => $importId,
            'exception' => $exception,
        ]);
    }
}
