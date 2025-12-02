<?php

namespace App\Imports;

use App\Enums\Currency;
use App\Models\Trainer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrainerImport extends BaseImporter implements ShouldQueue
{
    public function __construct(public string $tenantId)
    {
        parent::__construct();
    }

    protected function processRow(array $row)
    {
        $formattedRow = [];
        foreach ($row as $key => $value) {
            $formattedRow[Str::slug($key, '_')] = $value;
        }

        $isAvailable = 1;
        if (isset($formattedRow['is_available'])) {
            $isAvailable = in_array($formattedRow['is_available'], ['Yes', 1]) ? 1 : 0;
        }

        // Map Excel row data to Trainer model
        $data = [
            'gym_id' => $this->tenantId,
            'name' => $formattedRow['name'] ?? null,
            'email' => $formattedRow['email'] ?? null,
            'phone' => $formattedRow['phone'] ?? null,
            'specialization' => $formattedRow['specialization'] ?? null,
            'experience_years' => $formattedRow['experience_years'] ? (int) $formattedRow['experience_years'] : 0,
            'is_available' => $isAvailable,
            'available_hours' => $formattedRow['available_hours'] ?? null,
            'basic_salary' => $formattedRow['basic_salary'] ? (float) $formattedRow['basic_salary'] : 0,
            'pt_salary_per_member' => $formattedRow['pt_salary_per_member'] ? (float) $formattedRow['pt_salary_per_member'] : 0,
            'currency' => $formattedRow['currency'] ?? Currency::INR->value,
        ];

        Trainer::create($data);
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
