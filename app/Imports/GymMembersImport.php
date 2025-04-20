<?php

namespace App\Imports;

use App\Models\GymMember;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

class GymMembersImport implements ToModel, WithHeadingRow, WithValidation, WithEvents
{
    protected $gymId;
    protected $failedRows = [];

    public function __construct($gymId)
    {
        $this->gymId = $gymId;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        Log::info('Importing member row:', $row);
        $plan = MembershipPlan::where('gym_id', $this->gymId)
            ->where('name', $row['membership_plan_name'])
            ->first();

        if (!$plan) {
            Log::warning('Membership plan not found:', ['gym_id' => $this->gymId, 'plan_name' => $row['membership_plan_name']]);
            $this->failedRows[] = $row;
            return null; // Skip if plan not found
        }

        return new GymMember([
            'gym_id' => $this->gymId,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'dob' => $row['dob'] ? Carbon::parse($row['dob']) : null,
            'membership_start' => $row['membership_start'] ? Carbon::parse($row['membership_start']) : now(),
            'gym_membership_plan_id' => $plan->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:gym_members,email',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:65535',
            'dob' => 'nullable|date',
            'membership_start' => 'nullable|date',
            'membership_plan_name' => 'required|string',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                Log::info('AfterImport event triggered', ['failed_rows_count' => count($this->failedRows)]);
                if (!empty($this->failedRows)) {
                    try {
                        // Ensure imports directory exists
                        $importsDir = storage_path('app/imports');
                        if (!File::exists($importsDir)) {
                            File::makeDirectory($importsDir, 0755, true);
                            Log::info('Created imports directory', ['path' => $importsDir]);
                        } elseif (!is_writable($importsDir)) {
                            Log::error('Imports directory is not writable', ['path' => $importsDir]);
                            throw new \Exception('Imports directory is not writable: ' . $importsDir);
                        }

                        // Generate failed rows CSV
                        $timestamp = time();
                        $filePath = 'app/imports/';
                        $fileName = "failed_rows_{$timestamp}.csv";
                        // $path = storage_path("app/imports/failed_rows_{$timestamp}.csv");
                        $path = storage_path($filePath . $fileName);
                        $file = fopen($path, 'w');
                        if ($file === false) {
                            throw new \Exception('Failed to open file for writing: ' . $path);
                        }
                        $headers = array_merge(array_keys($this->failedRows[0]), ['error']);
                        fputcsv($file, $headers);
                        foreach ($this->failedRows as $row) {
                            fputcsv($file, $row);
                        }
                        fclose($file);

                        // Store path for download
                        $downloadPath = "imports/failed_rows_{$timestamp}.csv";
                        Storage::disk('local')->put($downloadPath, file_get_contents($path));
                        Log::info('Failed rows CSV generated', ['path' => $downloadPath]);

                        // Notify user
                        Notification::make()
                            ->title('Some rows failed to import')
                            ->body('The membership plan was not found for the selected gym in some rows.')
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('download')
                                    ->label('Download Failed Rows')
                                    ->url(route('download.failed.rows', ['file' => $fileName]))
                                    ->openUrlInNewTab(),
                            ])
                            ->warning()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('Failed to generate failed rows CSV', ['error' => $e->getMessage()]);
                        Notification::make()
                            ->title('Import partially failed')
                            ->body('Some rows could not be imported due to missing membership plans. Failed to generate error report.')
                            ->warning()
                            ->send();
                    }
                } else {
                    Notification::make()
                        ->title('Members imported successfully')
                        ->success()
                        ->send();
                    Log::info('Import successful, no failed rows');
                }
            },
        ];
    }
}
