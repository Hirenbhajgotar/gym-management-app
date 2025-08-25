<?php

namespace App\Imports;

use App\Enums\Currency;
use App\Models\Trainer;
use Filament\Facades\Filament;
use Illuminate\Validation\Rule;

class TrainerImport extends BaseImporter
{

    public function collection(\Illuminate\Support\Collection $rows)
    {
        $tenant = Filament::getTenant();

        dd($rows);
        foreach ($rows as $row) {
            $isAvailable = 1;
            if (isset($row['is_available'])) {
                $isAvailable = in_array($row['is_available'], ['Yes', 1]) ? 1 : 0;
            }

            // Map Excel row data to Trainer model
            $data = [
                // 'gym_id' => $gym?->id ?? null,
                'gym_id' => $tenant->id ?? null,
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null, // Already cleaned in mutateBeforeValidationUsing
                'specialization' => $row['specialization'] ?? null,
                'experience_years' => $row['experience_years'] ? (int) $row['experience_years'] : 0,
                'is_available' => $isAvailable ?? 1, // Default to 1 if not provided
                'available_hours' => $row['available_hours'] ?? null,
                'basic_salary' => $row['basic_salary'] ? (float) $row['basic_salary'] : 0,
                'pt_salary_per_member' => $row['pt_salary_per_member'] ? (float) $row['pt_salary_per_member'] : 0,
                'currency' => $row['currency'] ?? Currency::INR->value, // Default to INR if not provided
            ];

            Trainer::create($data);
        }


        // Create the record (duplicate email check is handled by validation)
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
}
