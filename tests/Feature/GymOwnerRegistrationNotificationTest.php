<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Plan;
use App\Models\User;
use App\Services\GymOwnerRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class GymOwnerRegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // Manually create necessary roles/permissions to avoid heavy seeder
        $role = \Spatie\Permission\Models\Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_registration_completes_even_if_notification_throws_exception()
    {
        // Arrange
        Notification::fake();
        Log::shouldReceive('info')->andReturnNull();
        
        // We expect an error log for the failed notification
        Log::shouldReceive('error')
            ->once() // Expect at least one error log
            ->withArgs(function ($message) {
                return str_contains($message, 'Failed to notify super admin');
            });

        // Create a plan
        $plan = Plan::factory()->create();

        // Mock a super admin that throws an exception when notified
        $mockSuperAdmin = Mockery::mock(User::class)->makePartial();
        $mockSuperAdmin->shouldReceive('getAttribute')->with('id')->andReturn(999);
        $mockSuperAdmin->shouldReceive('getAttribute')->with('name')->andReturn('Super Admin');
        $mockSuperAdmin->shouldReceive('getAttribute')->with('email')->andReturn('admin@example.com');
        $mockSuperAdmin->shouldReceive('notify')
            ->andThrow(new \Exception('Simulated notification failure'));

        // Mock Cache to return our problematic super admin
        Cache::shouldReceive('flexible')
            ->with(User::ALL_SUPER_ADMINS_CACHE_KEY, Mockery::any(), Mockery::any())
            ->andReturn(collect([$mockSuperAdmin]));

        $service = app(GymOwnerRegistrationService::class);

        $registrationData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'password' => 'password',
            'gym_name' => 'Test Gym',
            'business_type' => 'LLC',
            'number_of_locations' => '1',
            'years_in_business' => 5,
            'business_description' => 'A test gym',
            'address' => [
                'street' => '123 Main St',
                'city_id' => 1,
                'state_id' => 1,
                'country_id' => 1,
                'postal_code' => '12345',
            ],
            'website' => 'https://example.com',
            'gym_size' => 'Medium',
            'special_requirements' => 'None',
            'selected_plan_id' => $plan->id,
        ];

        // Act
        $result = $service->createFromRegistrationData($registrationData);

        // Assert
        $this->assertNotNull($result['user']);
        $this->assertNotNull($result['gym']);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('gyms', ['name' => 'Test Gym']);
    }
}
