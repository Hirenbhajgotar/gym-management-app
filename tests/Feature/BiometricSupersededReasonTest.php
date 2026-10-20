<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricTemplatePropagator;
use App\Biometrics\Services\BiometricTemplateSuperseder;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvenanceEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Actions\EnrollBiometricAction;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * `superseded_reason` carries a compliance obligation: `erasure_override` means an explicit
 * erasure request — member- or operator-initiated — was overtaken by a re-enrolment. The
 * discriminator must therefore be derived from the row's prior status on every path, not
 * stamped optimistically by a bulk update.
 */
class BiometricSupersededReasonTest extends TestCase
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

    protected function template(BiometricTemplateSyncStatusEnum $status): BiometricTemplate
    {
        return BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'is_active' => $status !== BiometricTemplateSyncStatusEnum::PendingDeletion,
            'sync_status' => $status,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);
    }

    protected function reasonFor(BiometricTemplate $template): ?string
    {
        $reason = DB::table('biometric_templates')->where('id', $template->id)->value('superseded_reason');

        return is_string($reason) ? $reason : null;
    }

    /**
     * The primary path: enrolment initiation must record that it overrode an erasure, and
     * must emit the audit event a regulator would ask for.
     */
    protected function gymOwner(): User
    {
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');

        $owner = User::factory()->create();
        $this->gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $owner->assignRole($role);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        return $owner;
    }

    #[Test]
    public function enrolment_initiation_records_an_overridden_erasure_as_an_erasure_override(): void
    {
        // A row parked by the member's own Art. 17 request (the marker the GDPR deletion
        // service writes), not a machine park.
        $pendingDeletion = $this->template(BiometricTemplateSyncStatusEnum::PendingDeletion)
            ->forceFill(['erasure_requested_at' => now()->subHour()]);
        $pendingDeletion->save();

        $this->actingAs($this->gymOwner());

        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $pendingDeletion->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $pendingDeletion->sync_status);
        $this->assertSame(
            BiometricTemplate::SUPERSEDED_BY_ERASURE_OVERRIDE,
            $this->reasonFor($pendingDeletion),
            'An in-flight Art. 17 erasure must not be recorded as an ordinary re-enrolment.'
        );

        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'biometric')
                ->where('description', 'like', '%erasure request overridden%')
                ->exists(),
            'An erasure override must leave an auditable event.'
        );
    }

    /**
     * `PendingDeletion` is the universal parked state (archive/sweep/reconciler), so it alone
     * does not mean the member asked for erasure. A machine park carries no request marker, so
     * re-enrolling over it must be an ordinary supersede and must not fabricate an Art. 17
     * withdrawal record.
     */
    #[Test]
    public function a_machine_park_without_a_member_request_is_recorded_as_a_re_enrolment(): void
    {
        $machinePark = $this->template(BiometricTemplateSyncStatusEnum::PendingDeletion);

        $this->actingAs($this->gymOwner());

        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $machinePark->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $machinePark->sync_status);
        $this->assertSame(
            BiometricTemplate::SUPERSEDED_BY_RE_ENROLMENT,
            $this->reasonFor($machinePark),
            'A machine park is not a member erasure request and must not be recorded as one.'
        );

        $this->assertFalse(
            Activity::query()
                ->where('log_name', 'biometric')
                ->where('description', 'like', '%erasure request overridden%')
                ->exists()
        );
    }

    #[Test]
    public function a_machine_written_erasure_obligation_is_not_superseded_by_a_re_enrolment(): void
    {
        // The marker records a credential that lives at the provider. A
        // device-side re-enrolment does not replace it, so superseding the marker
        // would cancel the erasure obligation and write a false
        // "GDPR erasure overridden" audit line for an erasure nobody requested.
        $marker = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => null,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
        ]);
        // The flag is a compliance discriminator and is deliberately not mass-assignable.
        $marker->forceFill(['is_erasure_obligation' => true])->save();

        $this->actingAs($this->gymOwner());

        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $marker->refresh();
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $marker->sync_status,
            'A machine-written erasure marker must survive a re-enrolment.'
        );
        $this->assertNull($this->reasonFor($marker));

        $this->assertFalse(
            Activity::query()
                ->where('log_name', 'biometric')
                ->where('description', 'like', '%erasure request overridden%')
                ->exists(),
            'No false erasure-override audit line may be written for a machine marker.'
        );
    }

    #[Test]
    public function an_ordinary_re_enrolment_is_recorded_as_a_re_enrolment(): void
    {
        $synced = $this->template(BiometricTemplateSyncStatusEnum::Synced);

        $this->actingAs($this->gymOwner());

        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $synced->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $synced->sync_status);
        $this->assertSame(BiometricTemplate::SUPERSEDED_BY_RE_ENROLMENT, $this->reasonFor($synced));

        $this->assertFalse(
            Activity::query()
                ->where('log_name', 'biometric')
                ->where('description', 'like', '%erasure request overridden%')
                ->exists()
        );
    }

    #[Test]
    public function the_superseder_never_touches_a_row_outside_the_requested_modality(): void
    {
        $faceErasure = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
        ]);

        resolve(BiometricTemplateSuperseder::class)->supersedeForNewEnrolment(
            $this->gym->id,
            $this->member,
            $this->device->id,
            BiometricTypeEnum::Fingerprint->value,
            BiometricProvenanceEnum::Hardware
        );

        $faceErasure->refresh();
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $faceErasure->sync_status,
            'A PIN is shared across modalities, so re-enrolling one must not drop the other modality erasure.'
        );
    }

    #[Test]
    public function the_superseder_returns_zero_and_writes_nothing_when_there_is_nothing_to_supersede(): void
    {
        $this->assertSame(0, resolve(BiometricTemplateSuperseder::class)->supersedeForNewEnrolment(
            $this->gym->id,
            $this->member,
            $this->device->id,
            BiometricTypeEnum::Fingerprint->value,
            BiometricProvenanceEnum::Hardware
        ));
    }

    /**
     * `Superseded` is excluded from every live-credential predicate and from the erasure
     * reconciler, so a cloud enrolment must only demote cloud rows. On ADR-004's dual-mode
     * device the terminal credential shares the PIN but is a different credential; demoting
     * it here would strand a terminal profile no path ever revisits.
     */
    #[Test]
    public function a_cloud_supersede_does_not_demote_the_terminal_credential_on_a_dual_mode_device(): void
    {
        $dualMode = BiometricDevice::factory()->dualMode('SN-DUAL-CLOUD-SUPERSEDE')->create([
            'gym_id' => $this->gym->id,
            'device_type' => 'fingerprint',
        ]);

        $terminal = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $dualMode->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $superseded = resolve(BiometricTemplateSuperseder::class)->supersedeForNewEnrolment(
            $this->gym->id,
            $this->member,
            $dualMode->id,
            BiometricTypeEnum::Fingerprint->value,
            BiometricProvenanceEnum::Cloud
        );

        $this->assertSame(0, $superseded, 'A cloud enrolment has no cloud row to demote here.');

        $terminal->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $terminal->sync_status);
        $this->assertTrue((bool) $terminal->is_active);
        $this->assertNotSame(
            BiometricTemplateSyncStatusEnum::Superseded,
            $terminal->sync_status,
            'A cloud enrolment must not demote the terminal credential that shares its PIN.'
        );
        $this->assertNull($this->reasonFor($terminal));
    }

    /**
     * A fan-out discharges only the origin's own provenance class. A hardware origin must not
     * demote a cloud `PendingDeletion` whose provider erase is still outstanding: the demotion
     * strands the credential in `Superseded` — which no live-credential predicate or the
     * reconciler revisits — and stamps a false "GDPR erasure request overridden by
     * re-enrolment" audit line for an erasure the fan-out never touched.
     */
    #[Test]
    public function a_hardware_fan_out_does_not_demote_a_cloud_pending_deletion(): void
    {
        $sibling = BiometricDevice::factory()->admsEnabled('SN-SUPERSEDE-SIBLING')->create([
            'gym_id' => $this->gym->id,
            'device_type' => 'fingerprint',
        ]);

        $cloudErasure = BiometricTemplate::factory()->cloudBacked('rekognition')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $sibling->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => '{"provider":"rekognition","externalId":"ext-cloud-erasure","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
            'is_erasure_obligation' => false,
            'erasure_requested_at' => now(),
        ]);

        $origin = BiometricTemplate::factory()->synced()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
        ]);

        resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->device, $this->member, $origin);

        $cloudErasure->refresh();
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $cloudErasure->sync_status,
            'A hardware fan-out must leave the cloud row awaiting its own provider erase.'
        );
        $this->assertNotNull($cloudErasure->erasure_requested_at);
        $this->assertNull($this->reasonFor($cloudErasure));

        $this->assertFalse(
            Activity::query()
                ->where('log_name', 'biometric')
                ->where('description', 'like', '%erasure request overridden%')
                ->exists(),
            'No erasure-override audit line may be written for a cross-provenance fan-out.'
        );

        $hardwareSibling = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $sibling->id)
            ->where('biometric_type', BiometricTypeEnum::Fingerprint)
            ->where('id', '!=', $cloudErasure->id)
            ->sole();

        $this->assertSame(BiometricTemplateSyncStatusEnum::Propagated, $hardwareSibling->sync_status);
        $this->assertSame('device_native', $hardwareSibling->provider_type);
        $this->assertFalse((bool) $hardwareSibling->is_erasure_obligation);
    }
}
