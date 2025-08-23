<?php

namespace App\Imports;

use App\Enums\DefaultRole;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatusEnum;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ReceptionistsImport extends BaseEmployeeImporter implements WithBatchInserts, WithChunkReading
{
    protected function getRole(): DefaultRole
    {
        return DefaultRole::RECEPTIONIST;
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 500;
    }

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
