<?php

declare(strict_types=1);

namespace Tests\Feature;

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

/**
 * A member's ADMS command PIN is their member id and is stable across re-enrolments, so an
 * acknowledgement for an earlier enrolment must never be attributed to a later placeholder.
 * Getting this wrong lets the abandoned-placeholder sweep queue a hardware delete and
 * erase a credential the member is still using.
 */
class BiometricTemplateDeliveredScopeTest extends TestCase
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

        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);
    }

    protected function pendingPlaceholder(int $ageDays = 0): BiometricTemplate
    {
        $template = BiometricTemplate::factory()->pending()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => null,
        ]);

        if ($ageDays > 0) {
            $template->forceFill([
                'created_at' => now()->subDays($ageDays),
                'updated_at' => now()->subDays($ageDays),
            ])->saveQuietly();
        }

        return $template->refresh();
    }

    protected function userAddKeyFor(BiometricTemplate $template): string
    {
        return AdmsCommandKey::buildUserAdd(
            $this->gym->id,
            $this->device->id,
            $this->member->id,
            $template->id
        );
    }

    #[Test]
    public function an_unrelated_acknowledged_user_add_for_the_same_pin_is_not_delivery_of_this_template(): void
    {
        $superseded = BiometricTemplate::factory()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'is_active' => false,
        ]);

        $placeholder = $this->pendingPlaceholder();

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'idempotency_key' => $this->userAddKeyFor($superseded),
        ]);

        $this->assertSame((string) $this->member->id, $placeholder->resolvedCommandPin());
        $this->assertFalse(
            $placeholder->wasDeliveredToHardware(),
            'An acknowledgement belonging to a different template must not count as delivery of this one.'
        );
    }

    #[Test]
    public function the_placeholder_own_acknowledged_user_add_counts_as_delivery(): void
    {
        $placeholder = $this->pendingPlaceholder();

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'idempotency_key' => $this->userAddKeyFor($placeholder),
        ]);

        $this->assertTrue($placeholder->wasDeliveredToHardware());
    }

    #[Test]
    public function a_daily_reconciler_requeue_for_this_template_counts_as_delivery(): void
    {
        $placeholder = $this->pendingPlaceholder();

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'idempotency_key' => AdmsCommandKey::buildUserAdd(
                $this->gym->id,
                $this->device->id,
                $this->member->id,
                $placeholder->id,
                AdmsCommandKey::STAGE_RECONCILE
            ),
        ]);

        $this->assertTrue($placeholder->wasDeliveredToHardware());
    }

    /**
     * The full round-9 blocker chain: an earlier enrolment is live on the terminal, the
     * re-enrolment is abandoned while the device is offline, and the 7-day sweep must not
     * erase the earlier credential.
     */
    #[Test]
    public function an_abandoned_re_enrolment_never_erases_the_previous_enrolments_credential(): void
    {
        $original = BiometricTemplate::factory()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
        ]);

        // The original enrolment's user_add was acknowledged and stays on the command log.
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'acknowledged_at' => now()->subMonth(),
            'idempotency_key' => $this->userAddKeyFor($original),
        ]);

        // Re-enrolment: the old row is superseded and a fresh placeholder takes its place.
        $original->update([
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'is_active' => false,
            'superseded_reason' => BiometricTemplate::SUPERSEDED_BY_RE_ENROLMENT,
        ]);

        $placeholder = $this->pendingPlaceholder(10);

        // The re-enrolment's own user_add never reaches the device, so it is still pending.
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => $this->userAddKeyFor($placeholder),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')->assertSuccessful();

        // The placeholder had nothing on the terminal, so it is retired outright.
        $this->assertSoftDeleted('biometric_templates', ['id' => $placeholder->id]);

        // The live credential is untouched: no PIN-wide DATA DELETE was queued.
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);

        $original->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $original->sync_status);
    }

    #[Test]
    public function a_delivered_placeholder_is_still_handed_to_hardware_erasure(): void
    {
        $placeholder = $this->pendingPlaceholder(10);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'idempotency_key' => $this->userAddKeyFor($placeholder),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')->assertSuccessful();

        $placeholder->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $placeholder->sync_status);

        $this->assertDatabaseHas('adms_device_commands', [
            'command_type' => 'user_delete',
            'command_payload->Pin' => (string) $this->member->id,
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }
}
