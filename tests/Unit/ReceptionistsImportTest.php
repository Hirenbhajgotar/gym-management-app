<?php

namespace Tests\Unit;

use App\Enums\DefaultRole;
use App\Imports\ReceptionistsImport;
use App\Models\EmployeeDetail;
use App\Models\Gym;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReceptionistsImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $gym = Gym::factory()->create();
        Filament::setTenant($gym);
        // Mock authenticated user for notification
        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function it_can_import_receptionists()
    {
        // Given
        $importer = new ReceptionistsImport;

        $mockData = new Collection([
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '1234567890',
                'password' => 'password',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'marital_status' => 'single',
                'monthly_salary' => 50000,
                'emergency_contact_name' => 'Jane Doe',
                'emergency_contact_phone' => '0987654321',
                'emergency_contact_email' => 'jane.doe@example.com',
                'emergency_contact_relationship' => 'Wife',
                'hire_date' => '2023-01-01',
                'employment_type' => 'full-time',
                'skills' => 'communication,php',
                'notes' => 'A test note.',
            ],
        ]);

        // When
        $importer->collection($mockData);

        // Then
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user);

        $this->assertTrue($user->hasRole(DefaultRole::RECEPTIONIST->value));

        $this->assertDatabaseHas('employee_details', [
            'user_id' => $user->id,
            'monthly_salary' => 50000,
        ]);

        $employeeDetail = EmployeeDetail::where('user_id', $user->id)->first();
        $this->assertEquals(['communication', 'php'], $employeeDetail->skills);
    }

    /** @test */
    public function it_fails_to_import_when_required_data_is_missing()
    {
        // Given
        $importer = new ReceptionistsImport;

        $mockData = new Collection([
            [
                // 'name' => 'John Doe', // Missing name
                'email' => 'john.doe@example.com',
                'password' => 'password',
            ],
        ]);

        // When
        $importer->collection($mockData);

        // Then
        $this->assertDatabaseMissing('users', [
            'email' => 'john.doe@example.com',
        ]);

        $this->assertDatabaseHas('failed_import_rows', [
            'validation_error' => 'The name field is required.',
        ]);
    }
}
