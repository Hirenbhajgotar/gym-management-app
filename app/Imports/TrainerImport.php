<?php

namespace App\Imports;

use App\Models\Gym;
use App\Models\Trainer;
use Filament\Facades\Filament;
use Illuminate\Validation\Rule;


class TrainerImport extends BaseExcelImport
{
    public $importModel = Trainer::class;

    public function __construct()
    {
        parent::__construct();

        // Set expected headers for validation
        $this->setExpectedHeaders([
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

    public function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('trainers', 'email'),
            ],
            'phone' => 'nullable|numeric',
            'specialization' => 'nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0',
            'is_available' => 'nullable|in:Yes,No,0,1',
            'available_hours' => 'nullable|string|max:255',
            'basic_salary' => 'nullable|numeric|min:0',
            'pt_salary_per_member' => 'nullable|numeric|min:0',
        ];
    }

    protected function processRow($row)
    {
        // $gym = Filament::getTenant();
        // dd($row);
        // Handle is_available transformation (validation ensures valid values)
        $isAvailable = null;
        if (isset($row['is_available'])) {
            $isAvailable = in_array($row['is_available'], ['Yes', 1]) ? 1 : 0;
        }

        // Map Excel row data to Trainer model
        $data = [
            // 'gym_id' => $gym?->id ?? null,
            'gym_id' => $row['gym_id'] ?? null,
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

        // Create the record (duplicate email check is handled by validation)
        Trainer::create($data);
    }
}
