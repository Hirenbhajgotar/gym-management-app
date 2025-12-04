<?php

namespace App\Filament\Actions\Imports;

use App\Models\User;
use App\Traits\HasTenantAccess;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Actions\Action as NotificationsActionsAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A custom Filament action for importing data from Excel files.
 *
 * This action provides a standardized way to handle Excel imports within Filament panels.
 * It includes a modal form for file uploads, validation, and processing of the imported data.
 * It also handles failed rows by generating a downloadable Excel file with the validation errors.
 */
class ExcelImportAction extends Action
{
    use HasTenantAccess;

    /**
     * The importer class to be used for processing the Excel file.
     */
    protected string|Closure|null $importer = null;

    /**
     * The model class associated with the import.
     */
    protected string|Closure|null $model = null;

    /**
     * The URL for the sample Excel file.
     */
    protected ?string $sampleUrl = null;

    /**
     * The data for the sample Excel file.
     *
     * @var array<array<string, string>>|null
     */
    protected ?array $sampleData = null;

    protected ?string $sampleFileName = 'sample.xlsx';

    /**
     * The label for the sample file download button.
     */
    protected ?string $sampleButtonLabel = 'Download Sample File';

    /**
     * Get the default name for the action.
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
            ->modalSubmitActionLabel('Import')
            ->closeModalByClickingAway(false);

        // Set the default form for the modal.
        $this->form(fn (): array => $this->defaultForm());

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

            // Instantiate the importer and queue the import.
            $tenantId = $this->getGymId();
            $importer = new $importerClass($tenantId);
            Excel::import($importer, $filePath);
        });
    }

    /**
     * Set the importer class for the action.
     *
     * @param  string|Closure  $importer  The importer class.
     */
    public function importer(string|Closure $importer): static
    {
        $this->importer = $importer;

        return $this;
    }

    /**
     * Set the model class for the action.
     *
     * @param  string|Closure  $model  The model class.
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
     * @param  array|Collection  $failures  The array of failed rows.
     */
    protected function handleFailedRows(array|Collection $failures): void
    {
        $failedRows = $failures->map(function ($failure) {
            return [
                'row_number' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => implode(', ', $failure->errors()),
                'values' => implode(', ', $failure->values()),
            ];
        })->all();

        $export = new class($failedRows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            protected array $data;

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
                return [
                    'Row Number',
                    'Attribute',
                    'Errors',
                    'Values',
                ];
            }
        };

        $fileName = 'failed_imports_' . now()->timestamp . '.xlsx';
        $path = 'failed_imports/' . $fileName;
        Excel::store($export, $path, 'public');

        $downloadUrl = Storage::disk('public')->url($path);

        $recipient = Auth::user();
        Notification::make()
            ->title('Import Partially Successful')
            ->body('Some records failed to import. Please check the downloaded file for details.')
            ->danger()
            ->actions([
                NotificationsActionsAction::make('download_failed_rows')
                    ->label('Download Failed Rows')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url($downloadUrl, shouldOpenInNewTab: true)
                    ->markAsRead()
                    ->color('danger'),
            ])
            ->sendToDatabase($recipient)
            ->send();

        event(new DatabaseNotificationsSent($recipient));
    }

    /**
     * Create the file upload component for the form.
     */
    private function fileUpload(): FileUpload
    {
        $fileUpload = FileUpload::make('upload')
            ->label('Upload File')
            ->required()
            ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->disk('local')
            ->directory('excel-imports');

        // Add a hint action to download a sample file, if a URL or data is provided.
        if (filled($this->sampleUrl) || filled($this->sampleData)) {
            $fileUpload->hintAction(
                $this->getSampleExcelButton()
            );
        }

        return $fileUpload;
    }

    /**
     * Get the default form components.
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
     */
    private function getSampleExcelButton(): ActionsAction
    {
        $action = ActionsAction::make($this->sampleButtonLabel ?: __('Download Sample File'));

        if (filled($this->sampleUrl)) {
            $action->url($this->sampleUrl, shouldOpenInNewTab: true);

            if ($this->sampleFileName) {
                $action->extraAttributes(['download' => $this->sampleFileName]);
            }
        } else {
            $action->action(fn () => $this->downloadSampleExcel());
        }

        return $action;
    }

    /**
     * Download the sample Excel file generated from data.
     */
    protected function downloadSampleExcel()
    {
        $export = new class($this->sampleData) implements FromCollection, WithHeadings
        {
            protected array $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }

            public function headings(): array
            {
                if (empty($this->data)) {
                    return [];
                }

                return array_map('ucwords', array_keys($this->data[0]));
            }
        };

        return Excel::download($export, $this->sampleFileName ?? 'sample.xlsx');
    }

    /**
     * Set the URL or data for the sample file.
     *
     * @param  string|array<array<string, string>>  $urlOrData
     * @param  string|null  $fileName  The name of the file to be downloaded.
     * @param  string|null  $sampleButtonLabel  The label for the download button.
     */
    public function sampleFile(string|array $urlOrData, ?string $fileName = null, ?string $sampleButtonLabel = null): static
    {
        if (is_string($urlOrData)) {
            $this->sampleUrl = $urlOrData;
            $this->sampleData = null;
        } else {
            $this->sampleUrl = null;
            $this->sampleData = $urlOrData;
        }
        $this->sampleFileName = $fileName;
        $this->sampleButtonLabel = $sampleButtonLabel;

        return $this;
    }
}
