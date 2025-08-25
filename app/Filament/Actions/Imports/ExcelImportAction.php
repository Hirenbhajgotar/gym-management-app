<?php

namespace App\Filament\Actions\Imports;

use Closure;
use Throwable;
use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * A custom Filament action for importing data from Excel files.
 *
 * This action provides a standardized way to handle Excel imports within Filament panels.
 * It includes a modal form for file uploads, validation, and processing of the imported data.
 * It also handles failed rows by generating a downloadable Excel file with the validation errors.
 */
class ExcelImportAction extends Action
{
    /**
     * The importer class to be used for processing the Excel file.
     *
     * @var string|Closure|null
     */
    protected string|Closure|null $importer = null;

    /**
     * The model class associated with the import.
     *
     * @var string|Closure|null
     */
    protected string|Closure|null $model = null;

    /**
     * The URL for the sample Excel file.
     *
     * @var string|null
     */
    protected ?string $sampleUrl = null;

    /**
     * The label for the sample file download button.
     *
     * @var string|null
     */
    protected ?string $sampleButtonLabel = 'Download Sample File';

    /**
     * Get the default name for the action.
     *
     * @return string|null
     */
    public static function getDefaultName(): ?string
    {
        return 'import';
    }

    /**
     * Set up the action with default configurations.
     *
     * This method configures the appearance and behavior of the action,
     * including its label, icon, modal settings, and the main action logic.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Import')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->modalWidth('xl')
            ->modalFooterActionsAlignment('right')
            ->closeModalByClickingAway(false);

        // Set the default form for the modal.
        $this->form(fn(): array => $this->defaultForm());

        // Define the main action logic.
        $this->action(function (array $data) {
            $filePath = Storage::disk('local')->path($data['upload']);
            $importerClass = $data['importer'];
            $modelClass = $data['model'];

            // Ensure the importer and model classes exist.
            if (! class_exists($importerClass) || ! class_exists($modelClass)) {
                Notification::make()
                    ->title('Error')
                    ->body('Importer or model not found.')
                    ->danger()
                    ->send();

                return;
            }

            try {
                // Instantiate the importer and import the data.
                $importer = new $importerClass($modelClass);
                Excel::import($importer, $filePath);

                $failedRows = $importer->failures();

                // Handle failed rows, if any.
                if (! empty($failedRows)) {
                    $this->handleFailedRows($failedRows);
                } else {
                    Notification::make()
                        ->title('Import Successful')
                        ->body('All records have been imported successfully.')
                        ->success()
                        ->send();
                }
            } catch (Throwable $e) {
                // Notify the user of any errors during the import process.
                Notification::make()
                    ->title('Import Error')
                    ->body('An unexpected error occurred during the import process. Please check the file and try again.')
                    ->danger()
                    ->send();

                Log::error('Import Error', ['exception' => $e]);
            } finally {
                // Clean up the uploaded file.
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        });
    }

    /**
     * Set the importer class for the action.
     *
     * @param  string|Closure  $importer The importer class.
     * @return static
     */
    public function importer(string|Closure $importer): static
    {
        $this->importer = $importer;

        return $this;
    }

    /**
     * Set the model class for the action.
     *
     * @param  string|Closure  $model The model class.
     * @return static
     */
    public function use(string|Closure $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Handle rows that failed to import.
     *
     * This method generates an Excel file with the failed rows and their validation errors,
     * and provides a download link to the user.
     *
     * @param  array|Collection  $failures The array of failed rows.
     */
    protected function handleFailedRows(array|Collection $failures): void
    {
        $failedRowsData = [];
        foreach ($failures as $failure) {
            $attribute = $failure->attribute(); // either heading key (if using heading row concern) or column index
            $errors = $failure->errors(); // Actual error messages from Laravel validator

            // $failedRowsData[] = array_merge($failure->values(), ['errors' => implode(', ', $failure->errors())]);
            $failedRowsData[] = [
                $attribute => implode(', ', $errors)
            ];
        }

        $failedRowsData = Arr::collapse($failedRowsData);

        $headings = array_keys($failedRowsData);

        // dd($failedRowsData, $headings);

        // Create an anonymous class for exporting the failed rows.
        $export = new class($failedRowsData, $headings) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(
                private readonly array $data,
                private readonly array $headings
            ) {}

            public function collection(): \Illuminate\Support\Collection
            {
                return collect($this->data);
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        // Store the failed imports file and get its URL.
        $fileName = 'failed_imports_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $path = 'failed-imports/' . $fileName;
        Excel::store($export, $path, 'public');

        $downloadUrl = Storage::disk('public')->url($path);

        $recipient = Auth::user();
        // Notify the user about the partially successful import.
        Notification::make()
            ->title('Import Partially Successful')
            ->body('Some records failed to import. Please check the downloaded file for details.')
            ->danger()
            ->actions([
                Action::make('download_failed_rows')
                    ->label('Download Failed Rows')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url($downloadUrl, shouldOpenInNewTab: true),
            ])
            ->sendToDatabase($recipient);

        event(new DatabaseNotificationsSent($recipient));
    }

    /**
     * Create the file upload component for the form.
     *
     * @return FileUpload
     */
    private function fileUpload(): FileUpload
    {
        $fileUpload = FileUpload::make('upload')
            ->label('Upload File')
            ->required()
            ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->disk('local')
            ->directory('excel-imports');

        // Add a hint action to download a sample file, if a URL is provided.
        if (filled($this->sampleUrl)) {
            $fileUpload->hintAction(
                $this->getSampleExcelButton()
            );
        }

        return $fileUpload;
    }

    /**
     * Get the default form components.
     *
     * @return array
     */
    private function defaultForm(): array
    {
        return [
            $this->fileUpload(),
            Hidden::make('importer')->default($this->importer),
            Hidden::make('model')->default($this->model),
        ];
    }

    /**
     * Get the action for downloading the sample Excel file.
     *
     * @return ActionsAction
     */
    private function getSampleExcelButton(): ActionsAction
    {
        $action = ActionsAction::make($this->sampleButtonLabel ?: __('Download Sample File'));
        if (is_string($this->sampleUrl)) {
            $action->url($this->sampleUrl);
        } else {
            $action->action(fn() => $this->downloadSampleExcelFile());
        }

        return $action;
    }

    /**
     * Set the URL for the sample file.
     *
     * @param  string  $url The URL of the sample file.
     * @param  string|null  $sampleButtonLabel The label for the download button.
     * @return static
     */
    public function sampleFile(string $url, ?string $sampleButtonLabel = null): static
    {
        $this->sampleUrl = $url;
        $this->sampleButtonLabel = $sampleButtonLabel;

        return $this;
    }
}
