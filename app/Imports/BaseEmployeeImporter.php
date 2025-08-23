<?php

namespace App\Imports;

use App\Enums\DefaultRole;
use App\Models\EmployeeDetail;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseEmployeeImporter extends BaseExcelImport
{
    /**
     * The model that this importer will handle.
     *
     * @var string
     */
    public $importModel = User::class;

    public function __construct()
    {
        parent::__construct();

        // Set expected headers for validation
        $this->setExpectedHeaders($this->getExpectedHeaders());
    }

    /**
     * Get the validation rules for the imported data.
     *
     * This method calls the setRules() method to provide an array
     * of validation rules that will be applied to each row of data
     * during the import process. Child classes should define specific
     * rules by implementing the setRules() method.
     *
     * @return array The array of validation rules.
     */
    public function rules(): array
    {
        return $this->setRules();
    }

    /**
     * Process a single row of data.
     *
     * @param  array  $row
     */
    protected function processRow($row): void
    {
        try {
            DB::beginTransaction();

            // Create a new user with basic information from the imported row.
            $user = User::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => $row['password'], // Hash the password for security.
                'phone' => $row['phone'] ?? null,
            ]);

            // Get the current tenant (gym) from Filament.
            $tenant = Filament::getTenant();
            Log::info('default tenant is '.$tenant);

            // Attach the user to the current gym with the specified role.
            $user->gyms()->attach($tenant->id, ['role' => $this->getRole()->value]);

            // Use the RolesAndPermissionsService to formally assign the role to the user within the tenant.
            app(RolesAndPermissionsService::class)->assignRoleToTenant($tenant, $user, $this->getRole());

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

            DB::commit();
        } catch (Exception $e) {
            Log::error($e);
            DB::rollBack();
            //            throw $e;
        }
    }

    /**
     * Define the validation rules for each row of imported data.
     *
     * This method should be implemented by child classes to provide
     * specific rules that validate the data being imported. The rules
     * will be applied to each row during the import process to ensure
     * data integrity and correctness.
     *
     * @return array The array of validation rules.
     */
    protected function setRules(): array
    {
        return [];
    }

    // Get the expected headers for the import.
    abstract protected function getExpectedHeaders(): array;

    // Get the role for the employee being imported.
    abstract protected function getRole(): DefaultRole;
}
