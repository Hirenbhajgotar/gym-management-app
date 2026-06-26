<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Services\TwilioAccountManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CleanDeletedGymOwnersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the gym_owner role (global, no gym_id) for test usage
        Role::query()->firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_deletes_owners_soft_deleted_more_than_15_days_ago(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(DefaultRole::GYM_OWNER->value);

        $gym = Gym::factory()->create();
        $gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        // Soft-delete 16 days ago
        $owner->delete();
        $owner->update(['deleted_at' => now()->subDays(16)]);

        // Mock Twilio cleanup so it doesn't make real API calls
        $managerMock = Mockery::mock(TwilioAccountManager::class);
        $managerMock->shouldReceive('cleanupTenantResources')->andReturn([
            'success' => true,
            'message' => 'Cleaned',
            'cleaned' => [],
        ]);
        $this->app->singleton(TwilioAccountManager::class, fn () => $managerMock);

        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        // Owner should be permanently deleted
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        // Gym should also be deleted (cascade via UserObserver -> GymObserver)
        $this->assertDatabaseMissing('gyms', ['id' => $gym->id]);
    }

    #[Test]
    public function it_does_not_delete_owners_soft_deleted_less_than_15_days_ago(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(DefaultRole::GYM_OWNER->value);

        $gym = Gym::factory()->create();
        $gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        // Soft-delete only 10 days ago (within grace period)
        $owner->delete();
        $owner->update(['deleted_at' => now()->subDays(10)]);

        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        // Owner should still exist (soft-deleted)
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        // Gym should still exist
        $this->assertDatabaseHas('gyms', ['id' => $gym->id]);
    }

    #[Test]
    public function it_reports_no_owners_found(): void
    {
        $this->artisan('owners:clean-deleted')
            ->expectsOutput('No deleted gym owners found older than 15 days.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_supports_dry_run_mode(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(DefaultRole::GYM_OWNER->value);

        $gym = Gym::factory()->create();
        $gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        // Soft-delete 20 days ago
        $owner->delete();
        $owner->update(['deleted_at' => now()->subDays(20)]);

        $this->artisan('owners:clean-deleted --dry-run')
            ->expectsOutput('Dry run mode - no owners were deleted.')
            ->assertExitCode(0);

        // Owner should still exist in trashed state
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('gyms', ['id' => $gym->id]);
    }

    #[Test]
    public function it_triggers_twilio_cleanup_when_gym_has_whatsapp_account(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(DefaultRole::GYM_OWNER->value);

        $gym = Gym::factory()->create();
        $gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        // Create WhatsApp resources
        $whatsappAccount = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'twilio_account_sid' => 'ACtest_cmd_sid',
            'twilio_auth_token' => 'test_token',
            'messaging_service_sid' => 'MGtest_cmd_sid',
            'status' => 'active',
            'verification_status' => 'verified',
            'business_name' => 'Test Gym',
            'business_email' => 'test@gym.com',
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $whatsappAccount->id,
            'phone_number' => '+14155551111',
            'phone_number_sid' => 'PNtest_cmd_123',
            'country_code' => 'US',
            'status' => 'active',
            'type' => 'local',
            'purchased_at' => now(),
        ]);

        // Soft-delete 16 days ago
        $owner->delete();
        $owner->update(['deleted_at' => now()->subDays(16)]);

        // Mock Twilio cleanup - verify it's called with the correct gym ID
        $managerMock = Mockery::mock(TwilioAccountManager::class);
        $managerMock->shouldReceive('cleanupTenantResources')
            ->with($gym->id)
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'All Twilio resources cleaned up',
                'cleaned' => [
                    'phone_numbers' => ['success' => true, 'released' => 1],
                    'messaging_service' => ['success' => true],
                    'subaccount' => ['success' => true],
                ],
            ]);
        $this->app->singleton(TwilioAccountManager::class, fn () => $managerMock);

        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        // Everything should be cleaned up
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('gyms', ['id' => $gym->id]);
        $this->assertDatabaseMissing('whatsapp_accounts', ['gym_id' => $gym->id]);
        $this->assertDatabaseMissing('whatsapp_phone_numbers', ['whatsapp_account_id' => $whatsappAccount->id]);
    }

    #[Test]
    public function it_handles_multiple_gym_owners(): void
    {
        $owners = [];
        $gyms = [];

        for ($i = 0; $i < 3; $i++) {
            $owner = User::factory()->create();
            $owner->assignRole(DefaultRole::GYM_OWNER->value);

            $gym = Gym::factory()->create();
            $gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);

            $owner->delete();
            $owner->update(['deleted_at' => now()->subDays(20)]);

            $owners[] = $owner;
            $gyms[] = $gym;
        }

        // Mock Twilio cleanup
        $managerMock = Mockery::mock(TwilioAccountManager::class);
        $managerMock->shouldReceive('cleanupTenantResources')->andReturn([
            'success' => true,
            'message' => 'Cleaned',
            'cleaned' => [],
        ]);
        $this->app->singleton(TwilioAccountManager::class, fn () => $managerMock);

        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        foreach ($owners as $owner) {
            $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        }

        foreach ($gyms as $gym) {
            $this->assertDatabaseMissing('gyms', ['id' => $gym->id]);
        }
    }

    #[Test]
    public function it_continues_processing_when_one_owner_fails(): void
    {
        // Create two owners - one will fail, one should succeed
        $owner1 = User::factory()->create();
        $owner1->assignRole(DefaultRole::GYM_OWNER->value);
        $gym1 = Gym::factory()->create();
        $gym1->users()->attach($owner1->id, ['role' => DefaultRole::GYM_OWNER->value]);

        // Create a WhatsApp account for gym1 that will cause issues
        WhatsAppAccount::query()->create([
            'gym_id' => $gym1->id,
            'twilio_account_sid' => 'ACtest_fail_sid',
            'twilio_auth_token' => 'test_token',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $owner2 = User::factory()->create();
        $owner2->assignRole(DefaultRole::GYM_OWNER->value);
        $gym2 = Gym::factory()->create();
        $gym2->users()->attach($owner2->id, ['role' => DefaultRole::GYM_OWNER->value]);

        $owner1->delete();
        $owner1->update(['deleted_at' => now()->subDays(20)]);
        $owner2->delete();
        $owner2->update(['deleted_at' => now()->subDays(20)]);

        // Mock Twilio cleanup - it won't block deletion even if it fails
        $managerMock = Mockery::mock(TwilioAccountManager::class);
        $managerMock->shouldReceive('cleanupTenantResources')->andReturn([
            'success' => true,
            'message' => 'Cleaned',
            'cleaned' => [],
        ]);
        $this->app->singleton(TwilioAccountManager::class, fn () => $managerMock);

        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        // Both should be processed
        $this->assertDatabaseMissing('users', ['id' => $owner2->id]);
        $this->assertDatabaseMissing('gyms', ['id' => $gym2->id]);
    }
}
