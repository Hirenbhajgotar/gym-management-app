<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Models\BiometricDevice;
use App\Models\BiometricErasureRegisterExport;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppAccountRelease;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CleanDeletedGymOwnersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The purge dispatches the Twilio release after the delete commits. The sync queue
        // would otherwise make a real HTTP call from the test process, so the outbox row is
        // asserted here and the job itself is exercised separately with a mocked manager.
        Queue::fake();

        // Create the gym_owner role (global, no gym_id) for test usage
        Role::query()->firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);
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
        $owner->forceFill(['deleted_at' => now()->subDays(16)])->saveQuietly();

        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        // Owner should be permanently deleted
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        // Gym should also be deleted (cascade via UserObserver -> GymObserver)
        $this->assertDatabaseMissing('gyms', ['id' => $gym->id]);
    }

    #[Test]
    public function it_exports_the_erasure_register_and_purges_an_owner_whose_gym_has_a_terminal(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(DefaultRole::GYM_OWNER->value);

        $gym = Gym::factory()->create();
        $gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        $member = GymMember::factory()->create(['gym_id' => $gym->id]);
        $device = BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'created_by' => $owner->id,
            'adms_enabled' => true,
        ]);

        $template = BiometricTemplate::factory()->deviceNative()->create([
            'gym_id' => $gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $device->id,
            'template_ref' => (string) $member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $owner->delete();
        $owner->forceFill(['deleted_at' => now()->subDays(16)])->saveQuietly();

        // Retiring the terminal parks the credential as an obligation. The background
        // purge has no operator to discharge it, so it exports the register to a durable,
        // non-tenant-scoped table and then deletes the tenant under that audited override.
        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('gyms', ['id' => $gym->id]);
        $this->assertDatabaseMissing('biometric_devices', ['id' => $device->id]);
        $this->assertDatabaseMissing('biometric_templates', ['id' => $template->id]);

        $export = BiometricErasureRegisterExport::query()
            ->where('gym_id', $gym->id)
            ->sole();

        $this->assertSame(1, $export->obligation_count);
        $this->assertSame($template->id, $export->obligations[0]['template_id']);
        $this->assertSame('deletion_unresolvable', $export->obligations[0]['sync_status']);
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
        $owner->forceFill(['deleted_at' => now()->subDays(10)])->saveQuietly();

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
        $owner->forceFill(['deleted_at' => now()->subDays(20)])->saveQuietly();

        $this->artisan('owners:clean-deleted --dry-run')
            ->expectsOutput('Dry run mode - no owners were deleted.')
            ->assertExitCode(0);

        // Owner should still exist in trashed state
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('gyms', ['id' => $gym->id]);
    }

    #[Test]
    public function it_captures_the_whatsapp_resources_for_a_post_commit_release(): void
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
            'channel_sender_sid' => 'CNtest_cmd_123',
            'country_code' => 'US',
            'status' => 'active',
            'type' => 'local',
            'purchased_at' => now(),
        ]);

        // Soft-delete 16 days ago
        $owner->delete();
        $owner->forceFill(['deleted_at' => now()->subDays(16)])->saveQuietly();

        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        // Everything local should be cleaned up
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('gyms', ['id' => $gym->id]);
        $this->assertDatabaseMissing('whatsapp_accounts', ['gym_id' => $gym->id]);
        $this->assertDatabaseMissing('whatsapp_phone_numbers', ['whatsapp_account_id' => $whatsappAccount->id]);

        // The external release intent survives the tenant cascade because it has no
        // foreign key, and the queued job runs only after the delete commits.
        $release = WhatsAppAccountRelease::query()->where('gym_id', $gym->id)->sole();

        $this->assertSame(WhatsAppAccountRelease::STATUS_PENDING, $release->status);
        $this->assertSame('ACtest_cmd_sid', $release->twilio_account_sid);
        $this->assertSame('test_token', $release->twilio_auth_token);
        $this->assertSame('MGtest_cmd_sid', $release->messaging_service_sid);
        $this->assertSame('+14155551111', $release->phone_number);
        $this->assertSame('CNtest_cmd_123', $release->channel_sender_sid);
        $this->assertSame('PNtest_cmd_123', $release->incoming_phone_sid);
        $this->assertSame(0, $release->attempts);
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
            $owner->forceFill(['deleted_at' => now()->subDays(20)])->saveQuietly();

            $owners[] = $owner;
            $gyms[] = $gym;
        }

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
    public function it_processes_every_owner_independently_when_one_has_whatsapp_resources(): void
    {
        $owner1 = User::factory()->create();
        $owner1->assignRole(DefaultRole::GYM_OWNER->value);
        $gym1 = Gym::factory()->create();
        $gym1->users()->attach($owner1->id, ['role' => DefaultRole::GYM_OWNER->value]);

        // The WhatsApp account must be captured for release without blocking the purge.
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
        $owner1->forceFill(['deleted_at' => now()->subDays(20)])->saveQuietly();
        $owner2->delete();
        $owner2->forceFill(['deleted_at' => now()->subDays(20)])->saveQuietly();

        $this->artisan('owners:clean-deleted')
            ->assertExitCode(0);

        // Both should be processed; the first leaves a durable release intent behind.
        $this->assertDatabaseMissing('users', ['id' => $owner1->id]);
        $this->assertDatabaseMissing('gyms', ['id' => $gym1->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner2->id]);
        $this->assertDatabaseMissing('gyms', ['id' => $gym2->id]);

        $this->assertDatabaseHas('whatsapp_account_releases', [
            'gym_id' => $gym1->id,
            'twilio_account_sid' => 'ACtest_fail_sid',
            'status' => WhatsAppAccountRelease::STATUS_PENDING,
        ]);
    }
}
