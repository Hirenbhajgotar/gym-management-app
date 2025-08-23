<?php

namespace App\Imports;

use App\Enums\DefaultRole;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatusEnum;
use Illuminate\Validation\Rule;

class ReceptionistsImport extends BaseEmployeeImporter
{
    /**
     * Get the default role for the receptionist being imported.
     *
     * @return DefaultRole The role assigned to the receptionist.
     */
    protected function getRole(): DefaultRole
    {
        return DefaultRole::RECEPTIONIST;
    }

    /**
     * Retrieve the list of expected headers for the receptionist import.
     *
     * This method returns an array of string values representing the column
     * headers that are expected to be present in the import file. These
     * headers correspond to the fields required for importing receptionist
     * data.
     *
     * @return array An array of header names expected in the import file.
     */
    protected function getExpectedHeaders(): array
    {
        return [
            'name',
            'email',
            'phone',
            'password',
            'date_of_birth',
            'gender',
            'marital_status',
            'monthly_salary',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_email',
            'emergency_contact_relationship',
            'hire_date',
            'employment_type',
            'skills',
            'notes',
        ];
    }

    /**
     * Define validation rules for the receptionist import process.
     *
     * This method returns an array of validation rules to apply to each field
     * in the import data. The rules ensure that the data is properly formatted
     * and meets the necessary criteria for successful import.
     *
     * @return array An associative array where keys are field names and values
     *               are arrays of validation rules applicable to those fields.
     */
    public function setRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', Rule::enum(GenderEnum::class)],
            'marital_status' => ['nullable', 'string', Rule::enum(MaritalStatusEnum::class)],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date', 'before_or_equal:today'],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'string'], // Will be converted to array later
            'notes' => ['nullable', 'string'],
        ];
    }
}
