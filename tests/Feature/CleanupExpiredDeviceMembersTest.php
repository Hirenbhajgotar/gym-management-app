<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\GymSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CleanupExpiredDeviceMembersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->device = BiometricDevice::factory()
            ->admsEnabled('CLEANUP-SN-001')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);
    }

    public function test_cleanup_queues_delete_commands_for_expired_members(): void
    {
        // Member expired > default grace (7 + 10 = 17 days ago)
        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(3),
            'membership_end' => now()->subDays(30), // Well beyond 17-day grace
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        BiometricTemplate::factory()->create([
            'gym_member_id' => $expiredMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            // Hardware-backed: the ADMS sweep queues a terminal `user_delete` only
            // for a credential that actually lives on the terminal. A cloud-backed
            // row is erased through the provider instead (routing lives in
            // BiometricDeProvisioner).
            'provider_type' => 'device_native',
            'is_active' => true,
        ]);

        $command = $this->artisan('app:cleanup-expired-device-members');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertDatabaseHas('adms_device_commands', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'status' => 'pending',
        ]);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, AdmsDeviceCommand::query()->first()?->status);

        // The activity record pins the counters the operator-facing summary reports:
        // every visited template is "processed", while only the ones that queued a
        // delete raise the queued count.
        $activity = Activity::query()
            ->where('description', 'Expired or archived member biometric credentials de-provisioned via ADMS cleanup')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'The sweep must record what it processed.');
        $this->assertSame(1, $activity->properties['templates_processed']);
        $this->assertSame(1, $activity->properties['delete_commands_queued']);
        $this->assertFalse($activity->properties['is_archived']);
    }

    public function test_cleanup_respects_gym_grace_period_setting(): void
    {
        // Set custom gym grace period to 14 days
        GymSetting::query()->create([
            'gym_id' => $this->gym->id,
            'key' => 'grace_period_days',
            'value' => '14',
        ]);

        // Total grace: 14 + 10 = 24 days. Member expired 20 days ago → within grace
        $memberWithinGrace = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(3),
            'membership_end' => now()->subDays(20),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        BiometricTemplate::factory()->create([
            'gym_member_id' => $memberWithinGrace->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            // Hardware-backed: the ADMS sweep queues a terminal `user_delete` only
            // for a credential that actually lives on the terminal. A cloud-backed
            // row is erased through the provider instead (routing lives in
            // BiometricDeProvisioner).
            'provider_type' => 'device_native',
            'is_active' => true,
        ]);

        $command = $this->artisan('app:cleanup-expired-device-members');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        // Should NOT queue a delete — member is within 24-day grace
        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
        ]);
    }

    public function test_cleanup_skips_members_within_grace_period(): void
    {
        // Default grace: 7 + 10 = 17 days. Member expired 5 days ago → within grace
        $recentlyExpired = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(2),
            'membership_end' => now()->subDays(5),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        BiometricTemplate::factory()->create([
            'gym_member_id' => $recentlyExpired->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            // Hardware-backed: the ADMS sweep queues a terminal `user_delete` only
            // for a credential that actually lives on the terminal. A cloud-backed
            // row is erased through the provider instead (routing lives in
            // BiometricDeProvisioner).
            'provider_type' => 'device_native',
            'is_active' => true,
        ]);

        $command = $this->artisan('app:cleanup-expired-device-members');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->device->id,
        ]);

        // Template should still be active
        $this->assertDatabaseHas('biometric_templates', [
            'gym_member_id' => $recentlyExpired->id,
            'is_active' => true,
        ]);
    }

    public function test_cleanup_deactivates_biometric_template(): void
    {
        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(3),
            'membership_end' => now()->subDays(30),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        $template = BiometricTemplate::factory()->create([
            'gym_member_id' => $expiredMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            // Hardware-backed: the ADMS sweep queues a terminal `user_delete` only
            // for a credential that actually lives on the terminal. A cloud-backed
            // row is erased through the provider instead (routing lives in
            // BiometricDeProvisioner).
            'provider_type' => 'device_native',
            'is_active' => true,
        ]);

        $command = $this->artisan('app:cleanup-expired-device-members');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertDatabaseHas('biometric_templates', [
            'id' => $template->id,
            'is_active' => false,
        ]);
    }

    public function test_cleanup_does_not_duplicate_pending_commands(): void
    {
        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(3),
            'membership_end' => now()->subDays(30),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        BiometricTemplate::factory()->create([
            'gym_member_id' => $expiredMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            // Hardware-backed: the ADMS sweep queues a terminal `user_delete` only
            // for a credential that actually lives on the terminal. A cloud-backed
            // row is erased through the provider instead (routing lives in
            // BiometricDeProvisioner).
            'provider_type' => 'device_native',
            'is_active' => true,
        ]);

        // Pre-existing pending delete command
        AdmsDeviceCommand::factory()->userDelete((string) $expiredMember->id)->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);

        $command = $this->artisan('app:cleanup-expired-device-members');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        // Should still only have 1 pending delete command (not duplicated)
        $commandCount = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_delete')
            ->count();

        $this->assertEquals(1, $commandCount);
    }

    public function test_cleanup_selects_an_archived_member_whose_membership_end_has_not_expired(): void
    {
        // The archived clause is the only reason the sweep reaches a manually
        // archived member. A fixture with a three-year-old membership_end is
        // selected by the expiry clause alone and leaves the archived clause
        // idle, so this member is archived with a CURRENT membership_end.
        $archivedMember = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addYear(),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        BiometricTemplate::factory()->create([
            'gym_member_id' => $archivedMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'provider_type' => 'device_native',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $command = $this->artisan('app:cleanup-expired-device-members');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertDatabaseHas('adms_device_commands', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'status' => 'pending',
        ]);

        // The archived member is processed and queued, and the activity properties
        // carry the archive flag the operator summary is derived from.
        $activity = Activity::query()
            ->where('description', 'Expired or archived member biometric credentials de-provisioned via ADMS cleanup')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(1, $activity->properties['templates_processed']);
        $this->assertSame(1, $activity->properties['delete_commands_queued']);
        $this->assertTrue($activity->properties['is_archived']);
    }

    public function test_cleanup_leaves_a_pending_deletion_member_untouched(): void
    {
        // A row already awaiting erasure belongs to
        // biometrics:reconcile-pending-deletions, not this sweep. PendingDeletion
        // is excluded from the sweep's live-state selection, so no terminal
        // command is queued for it.
        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(3),
            'membership_end' => now()->subDays(30),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        $template = BiometricTemplate::factory()->create([
            'gym_member_id' => $expiredMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'provider_type' => 'device_native',
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $command = $this->artisan('app:cleanup-expired-device-members');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
        ]);

        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $template->refresh()->sync_status
        );
    }

    public function test_cleanup_does_not_queue_a_hardware_delete_for_a_cloud_backed_template(): void
    {
        // A cloud-backed credential is not on the terminal, so an ADMS
        // `user_delete` is meaningless for it — and when the terminal ACKs, it
        // would falsely converge the row to Deleted while the provider template
        // survives. The sweep delegates the cloud-vs-hardware decision to the
        // de-provisioner, so it queues nothing here.
        $archivedMember = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
        ]);

        $cloudTemplate = BiometricTemplate::factory()->create([
            'gym_member_id' => $archivedMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            // A cloud provider not registered in config: hasCloudProvider() is
            // true (it is not a hardware provider type), and the registry throws
            // rather than making a network call, so the row must be parked for
            // the provider retry path, never handed to the terminal.
            'provider_type' => 'unconfigured_cloud_provider',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-123","meta":[]}',
            'is_active' => true,
        ]);

        $command = $this->artisan('app:cleanup-expired-device-members');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
        ]);

        $cloudTemplate->refresh();
        $this->assertFalse((bool) $cloudTemplate->is_active);
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $cloudTemplate->sync_status,
            'A cloud row must be parked for the provider, never marked Deleted by a hardware path.'
        );
    }
}
