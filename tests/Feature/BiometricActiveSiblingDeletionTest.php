<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiometricActiveSiblingDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_type' => 'fingerprint',
        ]);

        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);
    }

    #[Test]
    public function active_sibling_template_deferers_instead_of_claiming_hardware_removal(): void
    {
        $fingerprint = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        $face = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        $result = resolve(BiometricDataDeletionService::class)->deleteSingleTemplate($fingerprint);

        $this->assertTrue($result);

        // No user_delete: erasing the shared PIN would destroy the sibling's credential too.
        $this->assertSame(0, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count());

        // `Deleted` asserts the terminal confirmed removal, which has not happened. The
        // obligation stays open so the nightly reconciler revisits it.
        $fingerprint->refresh();
        $this->assertFalse($fingerprint->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $fingerprint->sync_status);

        $face->refresh();
        $this->assertTrue($face->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $face->sync_status);
    }

    #[Test]
    public function erasing_the_last_template_emits_user_delete_and_converges_both_to_deleted_on_ack(): void
    {
        $fingerprint = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        $face = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        $service = resolve(BiometricDataDeletionService::class);

        // First row: the sibling is still active, so the shared PIN is retained.
        $this->assertTrue($service->deleteSingleTemplate($fingerprint));
        $fingerprint->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $fingerprint->sync_status);
        $this->assertFalse($fingerprint->is_active);
        $this->assertSame(0, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count());

        // Second row: no active template needs the PIN any more, so it is erased.
        $this->assertTrue($service->deleteSingleTemplate($face));
        $face->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $face->sync_status);

        $deleteCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_delete')
            ->first();

        $this->assertNotNull($deleteCommand);
        $this->assertSame($face->resolvedCommandPin(), $deleteCommand->command_payload['Pin'] ?? null);

        $deleteCommand->update(['status' => AdmsCommandStatusEnum::SENT]);
        $deleteCommand->markAcknowledged('0');

        // `DATA DELETE user Pin=N` is user-granular, so every template sharing the PIN
        // converges to Deleted together once the terminal confirms.
        $fingerprint->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $fingerprint->sync_status);
        $this->assertFalse($fingerprint->is_active);
        $face->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $face->sync_status);
    }

    #[Test]
    public function last_template_deletion_issues_hardware_user_delete(): void
    {
        $fingerprint = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => 'fp_1',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        $service = resolve(BiometricDataDeletionService::class);
        $result = $service->deleteSingleTemplate($fingerprint);

        $this->assertTrue($result);

        $deleteCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_delete')
            ->first();

        $this->assertNotNull($deleteCommand);
        $this->assertSame($fingerprint->resolvedCommandPin(), $deleteCommand->command_payload['Pin'] ?? null);
    }

    #[Test]
    public function a_stale_user_delete_ack_cannot_delete_another_members_template(): void
    {
        $otherMember = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $victim = BiometricTemplate::query()->create([
            'gym_member_id' => $otherMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $otherMember->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        // The command key names the victim's template id, but the payload PIN belongs to a
        // different member. The PIN must win, so the victim must survive.
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => AdmsCommandKey::buildUserDelete($this->device->id, (string) $this->member->id),
        ]);

        $command->markAcknowledged('0');

        $victim->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $victim->sync_status);
        $this->assertTrue($victim->is_active === false);
    }
}
