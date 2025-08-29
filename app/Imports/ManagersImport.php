<?php

namespace App\Imports;

use App\Enums\DefaultRole;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatusEnum;
use App\Models\EmployeeDetail;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Filament\Facades\Filament;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ManagersImport extends BaseImporter implements WithColumnFormatting
{
    protected array $dateColumns = [
        'date_of_birth',
        'hire_date',
    ];

    public function columnFormats(): array
    {
        return [
            'date_of_birth' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'hire_date' => NumberFormat::FORMAT_DATE_YYYYMMDD,
        ];
    }

    public function processRow($row): void
    {
        $managerRole = DefaultRole::MANAGER;

        // Get the current tenant (gym) from Filament.
        $tenant = Filament::getTenant();

        // Create a new user with basic information from the imported row.
        $user = User::create([
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => $row['password'], // Hash the password for security.
            'phone' => $row['phone'] ?? null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Attach the user to the current gym with the specified role.
        $user->gyms()->attach($tenant->id, ['role' => $managerRole->value]);

        // Use the RolesAndPermissionsService to formally assign the role to the user within the tenant.
        app(RolesAndPermissionsService::class)->assignRoleToTenant($tenant, $user, $managerRole);

        // Create the employee's detailed profile.
        EmployeeDetail::create([
            'user_id' => $user->id, // Link to the created user.
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'gender' => $row['gender'] ?? null,
            'marital_status' => $row['marital_status'] ?? null,
            'monthly_salary' => $row['monthly_salary'] ?? null,
            'emergency_contact_name' => $row['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $row['emergency_contact_phone'] ?? null,
            'emergency_contact_email' => $row['emergency_contact_email'] ?? null,
            'emergency_contact_relationship' => $row['emergency_contact_relationship'] ?? null,
            'hire_date' => $row['hire_date'] ?? null,
            'employment_type' => $row['employment_type'] ?? null,
            'skills' => $row['skills'] ? explode(',', $row['skills']) : null, // Convert comma-separated skills to an array.
            'notes' => $row['notes'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', Rule::enum(GenderEnum::class)],
            'marital_status' => ['nullable', 'string', Rule::enum(MaritalStatusEnum::class)],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'max:20'],
            'emergency_contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'max:255'],
            'hire_date' => ['nullable', 'date', 'before_or_equal:today'],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'string'], // Will be converted to array later
            'notes' => ['nullable', 'string'],
        ];
    }
}
