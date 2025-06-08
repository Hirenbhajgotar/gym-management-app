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

class TrainerImport extends BaseExcelImport
{

    public $importModel = Trainer::class;

    public function __construct()
    {
        parent::__construct();

        // Set expected headers for validation
        $this->setExpectedHeaders([
            'gym',
            'name',
            'email',
            'phone',
            'specialization',
            'experience_years',
            'is_available',
            'available_hours',
            'basic_salary',
            'pt_salary_per_member',
        ]);
    }

    protected function processRow($row)
    {
        // Get gym name and find corresponding gym_id
        $gymName = $row['gym'] ?? null;
        if (!$gymName) {
            throw new \Exception('Missing gym name.');
        }

        $gym = Gym::where('name', $gymName)->first();
        if (!$gym) {
            throw new \Exception("Gym not found: {$gymName}");
        }

        // Handle is_available transformation
        $isAvailable = null;
        if (isset($row['is_available'])) {
            if ($row['is_available'] === 'Yes' || $row['is_available'] === 1) {
                $isAvailable = 1;
            } elseif ($row['is_available'] === 'No' || $row['is_available'] === 0) {
                $isAvailable = 0;
            } else {
                throw new \Exception("Invalid value for is_available: {$row['is_available']}");
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
            'is_available' => $isAvailable ?? 1, // Default to 1 if not provided
            'available_hours' => $row['available_hours'] ?? null,
            'basic_salary' => $row['basic_salary'] ? (float) $row['basic_salary'] : 0,
            'pt_salary_per_member' => $row['pt_salary_per_member'] ? (float) $row['pt_salary_per_member'] : 0,
            'currency' => $row['currency'] ?? 'INR', // Default to INR if not provided
        ];

        // Validate required fields
        if (!$data['name'] || !$data['email']) {
            throw new \Exception('Missing required fields: name or email.');
        }

        // Use firstOrCreate to handle duplicates
        $existingTrainer = Trainer::where('email', $data['email'])->first();
        if ($existingTrainer) {
            throw new \Exception("Duplicate email: {$data['email']} already exists.");
        }

        // dd($data);
        // Use insert for bulk import
        Trainer::create($data);
    }
}
