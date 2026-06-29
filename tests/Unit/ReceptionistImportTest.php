<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Imports\BaseExcelImport;
use App\Imports\ReceptionistsImport;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// use PHPUnit\Framework\TestCase;

class ReceptionistImportTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    private $import;

    protected function setUp(): void
    {
        parent::setUp();

        Mockery::mock(BaseExcelImport::class)
            ->makePartial()
            ->shouldReceive('__construct')
            ->andReturnNull();

        $this->tenant = Gym::factory()->create();
        // filament()->setTenant($this->tenant);
        $this->import = resolve(ReceptionistsImport::class);
    }

    #[Test]
    public function it_validates_required_headers(): void
    {
        $expected = $this->import->getExpectedHeaders();

        // Check required headers
        $this->assertContains('name', $expected);
        $this->assertContains('email', $expected);
        $this->assertContains('password', $expected);

        // Check optional but important headers
        $this->assertContains('phone', $expected);
        $this->assertContains('date_of_birth', $expected);
        $this->assertContains('skills', $expected);
    }

    //
    // public function it_validates_email_uniqueness()
    // {
    //     $rules = $this->import->setRules();

    //     // Test with non-existing email
    //     $validator = Validator::make(['email' => 'new@test.test'], $rules);
    //     $this->assertFalse($validator->fails());

    //     // Create user and test with existing email
    //     User::factory()->create(['email' => 'existing@test.test']);
    //     $validator = Validator::make(['email' => 'existing@test.test'], $rules);
    //     $this->assertTrue($validator->fails());
    // }

    //
    // public function it_validates_data_rules()
    // {
    //     $rules = $this->import->setRules();

    //     $invalidData = [
    //         'name' => '', // required
    //         'email' => 'invalid-email', // invalid email
    //         'password' => '123', // too short
    //         'date_of_birth' => 'invalid-date',
    //         'gender' => 'invalid-gender',
    //         'marital_status' => 'invalid-status',
    //         'monthly_salary' => 'not-a-number',
    //     ];

    //     $validator = Validator::make($invalidData, $rules);
    //     $this->assertTrue($validator->fails());
    //     $this->assertArrayHasKey('name', $validator->errors());
    //     $this->assertArrayHasKey('email', $validator->errors());
    //     $this->assertArrayHasKey('password', $validator->errors());
    //     $this->assertArrayHasKey('date_of_birth', $validator->errors());
    //     $this->assertArrayHasKey('gender', $validator->errors());
    //     $this->assertArrayHasKey('marital_status', $validator->errors());
    //     $this->assertArrayHasKey('monthly_salary', $validator->errors());
    // }

    //
    // public function it_creates_user_with_hashed_password()
    // {
    //     $userData = [
    //         'name' => 'John Doe',
    //         'email' => 'john@test.test',
    //         'password' => 'password123',
    //         'phone' => '1234567890',
    //     ];

    //     $this->import->processRow($userData);

    //     $user = User::where('email', 'john@test.test')->first();
    //     $this->assertNotNull($user);
    //     $this->assertTrue(Hash::check('password123', $user->password));
    // }

    //
    // public function it_creates_employee_details_with_complete_data()
    // {
    //     $data = [
    //         'name' => 'Jane Doe',
    //         'email' => 'jane@test.test',
    //         'password' => 'password123',
    //         'phone' => '9876543210',
    //         'date_of_birth' => '1990-01-01',
    //         'gender' => GenderEnum::FEMALE->value,
    //         'marital_status' => MaritalStatusEnum::SINGLE->value,
    //         'monthly_salary' => 5000,
    //         'emergency_contact_name' => 'John Doe',
    //         'emergency_contact_phone' => '1234567890',
    //         'emergency_contact_email' => 'emergency@test.test',
    //         'emergency_contact_relationship' => 'Spouse',
    //         'hire_date' => '2023-01-01',
    //         'employment_type' => 'Full-time',
    //         'skills' => 'customer_service,phone_handling,cash_handling',
    //         'notes' => 'Test notes',
    //     ];

    //     $this->import->processRow($data);

    //     $user = User::where('email', 'jane@test.test')->first();
    //     $employeeDetail = EmployeeDetail::where('user_id', $user->id)->first();

    //     $this->assertNotNull($employeeDetail);
    //     $this->assertEquals('1990-01-01', $employeeDetail->date_of_birth);
    //     $this->assertEquals(GenderEnum::FEMALE->value, $employeeDetail->gender);
    //     $this->assertEquals(5000, $employeeDetail->monthly_salary);
    //     $this->assertEquals(['customer_service', 'phone_handling', 'cash_handling'], $employeeDetail->skills);
    // }

    //
    // public function it_handles_optional_fields_correctly()
    // {
    //     $minimalData = [
    //         'name' => 'Minimal User',
    //         'email' => 'minimal@test.test',
    //         'password' => 'password123',
    //     ];

    //     $this->import->processRow($minimalData);

    //     $user = User::where('email', 'minimal@test.test')->first();
    //     $employeeDetail = EmployeeDetail::where('user_id', $user->id)->first();

    //     $this->assertNotNull($user);
    //     $this->assertNotNull($employeeDetail);
    //     $this->assertNull($employeeDetail->phone);
    //     $this->assertNull($employeeDetail->date_of_birth);
    //     $this->assertNull($employeeDetail->skills);
    // }

    //
    // public function it_assigns_correct_role_and_tenant()
    // {
    //     $data = [
    //         'name' => 'Bob Smith',
    //         'email' => 'bob@test.test',
    //         'password' => 'password123',
    //         'phone' => '1234567890',
    //     ];

    //     $this->import->processRow($data);

    //     $user = User::where('email', 'bob@test.test')->first();

    //     // Check role assignment
    //     $this->assertTrue($user->hasRole('receptionist', $this->tenant));

    //     // Check tenant association
    //     $this->assertDatabaseHas('gym_user', [
    //         'gym_id' => $this->tenant->id,
    //         'user_id' => $user->id,
    //         'role' => 'receptionist',
    //     ]);

    //     // Verify gym relationship
    //     $this->assertTrue($user->gyms->contains($this->tenant));
    // }

    //
    // public function it_validates_enum_fields()
    // {
    //     $rules = $this->import->setRules();

    //     // Test valid gender
    //     $validator = Validator::make(['gender' => GenderEnum::MALE->value], $rules);
    //     $this->assertFalse($validator->errors()->has('gender'));

    //     // Test invalid gender
    //     $validator = Validator::make(['gender' => 'invalid'], $rules);
    //     $this->assertTrue($validator->errors()->has('gender'));

    //     // Test valid marital status
    //     $validator = Validator::make(['marital_status' => MaritalStatusEnum::MARRIED->value], $rules);
    //     $this->assertFalse($validator->errors()->has('marital_status'));

    //     // Test invalid marital status
    //     $validator = Validator::make(['marital_status' => 'invalid'], $rules);
    //     $this->assertTrue($validator->errors()->has('marital_status'));
    // }
}
