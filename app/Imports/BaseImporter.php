<?php

namespace App\Imports;

use App\Models\User;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithSkipDuplicates;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Facades\Excel;

abstract class BaseImporter implements ToCollection, WithHeadingRow, WithChunkReading
{
    use Importable, SerializesModels;

    protected int $successfulRows = 0;
    protected int $failedRows = 0;
    protected array $failedData = [];
    protected $importRecord;
    protected ?string $failedFilePath = null;

    public function __construct()
    {
        Notification::make()
            ->info()
            ->title('Import Started')
            ->body('Your import is being processed in the background. You will be notified once it is complete.')
            ->send();

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

    abstract protected function processRow(array $row);

    abstract public function rules(): array;

    // public function batchSize(): int
    // {
    //     return 1000;
    // }

    public function chunkSize(): int
    {
        return 3000;
    }

    public function collection(Collection $collection): void
    {
        $filteredCollection = $collection->filter(function ($row) {
            // Convert row to array and remove null values
            $filteredRow = array_filter($row->toArray(), function ($value) {
                return !is_null($value) && $value !== '';
            });
            // If the filtered row is empty, it means the original row was effectively empty
            return ! empty($filteredRow);
        });

        $this->importRecord->update([
            'total_rows' => $filteredCollection->count(),
        ]);

        foreach ($filteredCollection as $row) {
            $rowData = $row->toArray();

            $data = array_filter($rowData, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $validator = Validator::make($data, $this->rules());
            if ($validator->fails()) {
                $this->failedRows++;
                $failedRow = $data;
                $failedRow['error'] = implode(', ', $validator->errors()->all());
                $this->failedData[] = $failedRow;

                Log::info('Failed row data: ', $failedRow);
                $FailedImportRowobj = FailedImportRow::create([
                    'import_id' => $this->importRecord->id,
                    'data' => json_encode($failedRow),
                    'validation_error' => $failedRow['error'],
                ]);
                // Log::info('FailedImportRow created with ID: ' . $FailedImportRowobj);
            } else {
                try {
                    Log::info('Processing row: ');
                    $this->processRow($data);
                    $this->successfulRows++;
                } catch (\Exception $e) {
                    Log::error('Error processing row: ', [
                        'exception' => $e,
                    ]);
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



    protected function afterCollection(Collection $collection): void
    {
        if ($this->failedRows > 0) {
            $this->generateFailedRowsExcel();
        }

        $this->importRecord->update([
            'completed_at' => now(),
            'file_path' => $this->failedFilePath ?? 'N/A',
        ]);

        $this->sendNotification();
    }

    protected function generateFailedRowsExcel(): void
    {
        $fileName = 'failed_rows_' . $this->importRecord->id . '_' . now()->timestamp . '.xlsx';
        $filePath = 'failed_imports/' . $fileName;

        // $failedRows = FailedImportRow::where('import_id', $this->importRecord->id)->get();
        // $dataToExport = $failedRows->map(function ($failedRow) {
        //     $data = json_decode($failedRow->data, true);
        //     $data['error'] = $failedRow->validation_error;
        //     return $data;
        // })->all();

        $excel = (new class($this->failedData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
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
                if (empty($this->data)) {
                    return [];
                }
                $keys = array_keys($this->data[0]);

                return $keys;
            }
        });

        Excel::store($excel, $filePath, 'public');
        $this->failedFilePath = $filePath;
    }

    protected function sendNotification()
    {
        $successfulRows = number_format($this->successfulRows) . ' ' . Str::plural('row', $this->successfulRows);
        $message = "Your import has completed and {$successfulRows} imported.";

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

            // This route needs to be created.
            $downloadUrl = route('filament.failed-import.download', [
                'importId' => $this->importRecord?->id,
            ]);

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
            event(new DatabaseNotificationsSent($recipient));
        } else {
            Notification::make()
                ->success()
                ->title('Import Completed')
                ->body($message)
                ->send();
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->importRecord) {
            $this->importRecord->update([
                'completed_at' => now(),
                'file_path' => 'N/A',
            ]);
        }

        $recipient = User::find($this->importRecord->user_id);

        if ($recipient) {
            Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body('An error occurred while processing your import')
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        } else {
            Log::warning('User not found for import failure notification.', [
                'import_id' => $this->importRecord->id,
                'user_id' => $this->importRecord->user_id,
            ]);
        }

        Log::error('Import failed with exception: ' . $exception->getMessage(), [
            'import_id' => $this->importRecord->id ?? null,
            'exception' => $exception,
        ]);
    }
}
