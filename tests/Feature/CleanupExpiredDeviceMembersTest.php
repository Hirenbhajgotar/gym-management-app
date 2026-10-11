<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
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
}
