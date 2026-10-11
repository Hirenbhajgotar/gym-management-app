<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDeProvisioner;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeProvisionOutcome;
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
 * One test per de-provision outcome, so each arm of `ensureUserDeleteCommand()` and each
 * early return in `deprovision()` is pinned. Previously several of these branches could be
 * deleted without turning the suite red.
 */
class BiometricDeProvisionOutcomeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected BiometricDeProvisioner $deProvisioner;

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

        $this->deProvisioner = resolve(BiometricDeProvisioner::class);
    }

    protected function pendingDeletionTemplate(?BiometricDevice $device = null): BiometricTemplate
    {
        return BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => ($device ?? $this->device)->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);
    }

    protected function deleteKey(): string
    {
        return AdmsDeviceCommand::deleteIdempotencyKeyFor($this->device->id, (string) $this->member->id);
    }

    #[Test]
    public function an_undelivered_placeholder_is_written_off_without_a_hardware_command(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => null,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::NotDelivered, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    #[Test]
    public function an_active_sibling_on_the_same_device_defers_the_erasure(): void
    {
        $template = $this->pendingDeletionTemplate();

        BiometricTemplate::factory()->synced()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::DeferredToSibling, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    /**
     * The authority must refuse to emit a command for a device belonging to another gym,
     * because the command row would be attributed to the wrong tenant.
     */
    #[Test]
    public function a_device_belonging_to_another_gym_is_refused(): void
    {
        $foreignGym = Gym::factory()->create();
        $foreignDevice = BiometricDevice::factory()->create([
            'gym_id' => $foreignGym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_type' => 'fingerprint',
        ]);

        $template = $this->pendingDeletionTemplate($foreignDevice);

        $this->assertSame(BiometricDeProvisionOutcome::DeviceUnavailable, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    #[Test]
    public function a_device_that_is_not_adms_enabled_parks_the_erasure(): void
    {
        $this->device->update(['adms_enabled' => false]);
        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::DeviceUnavailable, $this->deProvisioner->deprovision($template));
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    #[Test]
    public function a_first_erasure_queues_a_command_keyed_by_pin(): void
    {
        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::Queued, $this->deProvisioner->deprovision($template));

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    /**
     * ADMS erases a whole PIN, so the idempotency key must be PIN-scoped. A template-scoped key
     * would let a second template sharing the PIN insert its own command for the same record.
     */
    #[Test]
    public function a_second_erasure_for_the_same_pin_reuses_the_live_command(): void
    {
        $first = $this->pendingDeletionTemplate();
        $this->assertSame(BiometricDeProvisionOutcome::Queued, $this->deProvisioner->deprovision($first));

        $second = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::AlreadyQueued, $this->deProvisioner->deprovision($second));

        $this->assertSame(
            1,
            AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count()
        );
    }

    #[Test]
    public function a_failed_delete_below_the_cap_is_re_driven_in_place(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 1,
            'device_return_code' => 'ERROR: rejected',
        ]);

        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::ReDriven, $this->deProvisioner->deprovision($template));

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertNull($command->device_return_code);
        $this->assertSame(
            1,
            AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count(),
            'Re-driving must reuse the row rather than insert a new command.'
        );
    }

    /**
     * Past the cap the obligation cannot self-heal, so it must be reported rather than being
     * indistinguishable from a healthy idle run.
     */
    #[Test]
    public function a_failed_delete_at_the_cap_reports_exhausted_instead_of_silently_no_opping(): void
    {
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 99,
            'device_return_code' => 'ERROR: rejected',
        ]);

        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::Exhausted, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
    }

    #[Test]
    public function the_reconciler_surfaces_an_exhausted_erasure_that_is_past_the_cooldown(): void
    {
        config()->set('biometrics.adms.retry_cooldown_hours', 0);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 99,
            'updated_at' => now()->subDays(3),
            'device_return_code' => 'ERROR: rejected',
        ]);

        $this->pendingDeletionTemplate();

        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('1 template(s) still ERASURE-OUTSTANDING past the delivery cap')
            ->assertSuccessful();
    }
}
