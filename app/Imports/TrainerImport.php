<?php

namespace App\Imports;

use App\Models\Gym;
use App\Models\Trainer;
use EightyNine\ExcelImport\EnhancedDefaultImport;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TrainerImport extends EnhancedDefaultImport
{
    protected $successfulRows = 0;
    protected $failedRows = 0;
    protected $failedData = [];

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            // dd($row); // Debugging line to inspect the row data
            try {
                // Get gym name and find corresponding gym_id
                $gymName = $row['gym'] ?? null;
                if (!$gymName) {
                    Log::warning('Skipping row due to missing gym name.', ['row' => $row->toArray()]);
                    continue;
                }

                $gym = Gym::where('name', $gymName)->first();
                if (!$gym) {
                    Log::warning('Gym not found.', ['gym_name' => $gymName, 'row' => $row->toArray()]);
                    continue;
                }

                // Handle is_available transformation
                $isAvailable = null;
                if (isset($row['is_available'])) {
                    if ($row['is_available'] === 'Yes' || $row['is_available'] === 1) {
                        $isAvailable = 1;
                    } elseif ($row['is_available'] === 'No' || $row['is_available'] === 0) {
                        $isAvailable = 0;
                    }
                }

                // Map Excel row data to Trainer model
                $data = [
                    'gym_id' => $gym->id,
                    'name' => $row['name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null, // Already cleaned in mutateBeforeValidationUsing
                    'specialization' => $row['specialization'] ?? null,
                    'experience_years' => $row['experience_years'] ? (int) $row['experience_years'] : 0,
                    'is_available' => $isAvailable,
                    'available_hours' => $row['available_hours'] ?? null,
                    'basic_salary' => $row['basic_salary'] ? (float) $row['basic_salary'] : 0,
                    'pt_salary_per_member' => $row['pt_salary_per_member'] ? (float) $row['pt_salary_per_member'] : 0,
                    'currency' => $row['currency'] ?? 'INR', // Default to INR if not provided
                ];

                Trainer::insert($data); // Use insert for bulk import
                $this->successfulRows++;

                Log::info('Trainer imported successfully.', [
                    'email' => $data['email'],
                ]);
            } catch (\Exception $e) {
                $this->failedRows++;
                $this->failedData[] = $row->toArray();

                Log::error('Failed to import trainer.', [
                    'row' => $row->toArray(),
                    'error' => $e->getMessage(),
                ]);
                continue; // Skip to the next row on error
            }
        }

        $this->afterCollection($collection);
    }

    protected function afterCollection(Collection $collection): void
    {
        // Show success message with statistics
        $count = $collection->count();
        $successfulRows = number_format($this->successfulRows) . ' ' . str('row')->plural($this->successfulRows);
        $message = "Your trainer import has completed and {$successfulRows} imported.";

        if ($this->failedRows > 0) {
            $failedRows = number_format($this->failedRows) . ' ' . str('row')->plural($this->failedRows);
            $message .= " {$failedRows} failed to import. Download failed rows here: [link]";
        }

        Notification::make()
            ->success()
            ->title('Import Successful')
            ->body($message)
            ->send();
        // $this->stopImportWithSuccess($message);
    }
}
