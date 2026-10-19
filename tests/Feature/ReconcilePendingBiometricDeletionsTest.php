<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcilePendingBiometricDeletionsTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);
    }

    public function test_chunking_processes_all_batches_without_starvation(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
            BiometricTemplate::query()->create([
                'gym_member_id' => $member->id,
                'gym_id' => $this->gym->id,
                'enrolled_by_device_id' => $this->device->id,
                'biometric_type' => BiometricTypeEnum::Fingerprint,
                'provider_type' => 'device_native',
                'template_ref' => (string) $member->id,
                'is_active' => false,
                'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
                'enrolled_at' => now(),
            ]);
        }

        $this->artisan('biometrics:reconcile-pending-deletions', ['--limit' => 2])
            ->expectsOutputToContain('4 command(s) queued')
            ->assertSuccessful();

        $this->assertSame(4, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count());
    }

    public function test_gym_option_filters_templates_by_gym(): void
    {
        $gym2 = Gym::factory()->create();
        $device2 = BiometricDevice::factory()->create([
            'gym_id' => $gym2->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        $member1 = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        BiometricTemplate::query()->create([
            'gym_member_id' => $member1->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $member1->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now(),
        ]);

        $member2 = GymMember::factory()->create(['gym_id' => $gym2->id]);
        BiometricTemplate::query()->create([
            'gym_member_id' => $member2->id,
            'gym_id' => $gym2->id,
            'enrolled_by_device_id' => $device2->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $member2->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now(),
        ]);

        $this->artisan('biometrics:reconcile-pending-deletions', ['--gym' => $this->gym->id])
            ->expectsOutputToContain('1 command(s) queued')
            ->assertSuccessful();

        $this->assertSame(1, AdmsDeviceCommand::query()->where('gym_id', $this->gym->id)->count());
        $this->assertSame(0, AdmsDeviceCommand::query()->where('gym_id', $gym2->id)->count());
    }

    public function test_invalid_gym_option_fails(): void
    {
        $this->artisan('biometrics:reconcile-pending-deletions', ['--gym' => '-1'])
            ->expectsOutputToContain('The --gym option must be a positive integer.')
            ->assertFailed();

        $this->artisan('biometrics:reconcile-pending-deletions', ['--gym' => 'invalid'])
            ->expectsOutputToContain('The --gym option must be a positive integer.')
            ->assertFailed();
    }

    public function test_existing_in_flight_command_does_not_queue_duplicate(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        BiometricTemplate::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now(),
        ]);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $member->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->device->id, (string) $member->id),
        ]);

        $this->artisan('biometrics:reconcile-pending-deletions', ['--gym' => $this->gym->id])
            ->expectsOutputToContain('0 template(s) deleted, 0 command(s) queued.')
            ->assertSuccessful();

        $this->assertSame(1, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count());
    }

    public function test_an_unresolvable_cloud_row_is_not_re_selected_after_it_is_parked(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $unresolvable = BiometricTemplate::factory()->erasureObligation()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'unconfigured_cloud_provider',
            'template_ref' => null, // no provider reference: nothing can erase it
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now(),
        ]);

        // The first run moves it to the terminal DeletionUnresolvable state.
        $this->artisan('biometrics:reconcile-pending-deletions')->assertSuccessful();

        $this->assertSame(
            BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            $unresolvable->refresh()->sync_status
        );

        // The second run must not re-select it: no hourly retry loop for a row
        // nothing can erase, and no terminal command emitted for it.
        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('No biometric templates pending deletion.')
            ->assertSuccessful();

        $this->assertSame(
            BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            $unresolvable->refresh()->sync_status
        );
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    public function test_reconciler_tallies_live_and_unavailable_cases(): void
    {
        // A live (SENT) delete already exists for this PIN.
        $liveMember = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        BiometricTemplate::query()->create([
            'gym_member_id' => $liveMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $liveMember->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now(),
        ]);
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $liveMember->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->device->id, (string) $liveMember->id),
        ]);

        // A second row bound to a device that cannot be driven.
        $offlineDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => false,
            'status' => BiometricDeviceStatusEnum::Inactive,
        ]);
        $offlineMember = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        BiometricTemplate::query()->create([
            'gym_member_id' => $offlineMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $offlineDevice->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $offlineMember->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now(),
        ]);

        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('1 template(s) already had a live delete command')
            ->expectsOutputToContain('1 template(s) waiting on an unavailable device')
            ->assertSuccessful();

        // The live row was left alone; the offline device got no command queued for it.
        $this->assertSame(1, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count());
        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $offlineDevice->id,
            'command_type' => 'user_delete',
        ]);
    }

    public function test_reconciler_re_arms_a_failed_delete_and_dead_letters_only_at_the_cap(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        BiometricTemplate::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $member->id],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 1,
            'updated_at' => now()->subDay(),
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->device->id, (string) $member->id),
        ]);

        // Below the cap the reconciler re-drives the row and preserves its delivery counter.
        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('1 command(s) queued')
            ->assertSuccessful();

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertSame(1, $command->attempt_count, 'A re-drive is the same obligation and must not reset the budget.');

        // Deliver and reject until the delivery budget is spent.
        for ($attempt = 2; $attempt <= AdmsCommandKey::maxAttempts(); $attempt++) {
            $this->assertTrue($command->refresh()->markSent());
            $this->assertTrue($command->markAcknowledged('rejected'));

            $this->artisan('biometrics:reconcile-pending-deletions')->assertSuccessful();
        }

        // At the cap the reconciler dead-letters it instead of writing to the hardware again.
        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('DEAD-LETTERED')
            ->assertSuccessful();

        $this->assertSame(AdmsCommandStatusEnum::FAILED, $command->refresh()->status);
        $this->assertSame(AdmsCommandKey::maxAttempts(), $command->attempt_count);
    }
}
