<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricTemplateSuperseder;
use App\Enums\BiometricDeviceStatusEnum;
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
 * `superseded_reason` carries a compliance obligation: `erasure_override` means an operator
 * overrode a member's Art. 17 request. The discriminator must therefore be derived from the
 * row's prior status on every path, not stamped optimistically by a bulk update.
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
        $pendingDeletion = $this->template(BiometricTemplateSyncStatusEnum::PendingDeletion);

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
            BiometricTypeEnum::Fingerprint->value
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
            BiometricTypeEnum::Fingerprint->value
        ));
    }
}
