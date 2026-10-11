<?php

declare(strict_types=1);

namespace Tests\Feature;

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
}
