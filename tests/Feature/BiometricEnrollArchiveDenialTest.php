<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\Services\MembershipGuard;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Services\MemberArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Archiving is an access revocation, so an archived member must not be able to
 * acquire — or regain — a credential the door would still honour.
 *
 * BiometricArchiveDenialTest covers the CHECK-IN half of that invariant. This
 * suite covers the ENROLLMENT half, which consent alone does not close:
 * hasActiveBiometricConsent() is independent of is_archived, and a consent row
 * outlives the archiving that forced the credential off, so a consent-only gate
 * would happily (re)enroll a member whose access was revoked — or flip a pending
 * device-native template active — recreating exactly the "the app says revoked,
 * the device still says enrolled" state MemberArchiveService states must never
 * exist.
 *
 * The cloud-enrol refusal is paired with the same request against a member who
 * is NOT archived, so a 422 from any other cause (a dead provider, a validation
 * rule, a consent miss) cannot make that pair pass for the wrong reason. The
 * device-native and orphan-marker cases are single-sided by design: they assert
 * the row state that a refusal must leave untouched, which has no non-archived
 * counterpart to compare against.
 */
class BiometricEnrollArchiveDenialTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected GymMember $archivedMember;

    protected GymMember $activeMember;

    protected BiometricDevice $cloudDevice;

    protected BiometricDevice $deviceNativeDevice;

    protected string $cloudToken;

    protected string $deviceNativeToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        // Both members hold an ACTIVE consent and a live, paid membership, so
        // the archive flag is the single variable between them.
        $this->archivedMember = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
        ]);

        $this->activeMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
        ]);

        $this->grantConsent($this->archivedMember);
        $this->grantConsent($this->activeMember);

        $this->cloudToken = 'enroll-archive-cloud-token-123456';
        $this->cloudDevice = BiometricDevice::factory()
            ->withToken($this->cloudToken)
            ->create([
                'gym_id' => $this->gym->id,
                'device_serial' => 'SN-ENROLL-ARCHIVE-CLOUD',
                'status' => BiometricDeviceStatusEnum::Active,
                'is_enabled' => true,
                'provider_type' => BiometricProvidersEnum::Rekognition,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $this->deviceNativeToken = 'enroll-archive-native-token-1234';
        $this->deviceNativeDevice = BiometricDevice::factory()
            ->withToken($this->deviceNativeToken)
            ->create([
                'gym_id' => $this->gym->id,
                'device_serial' => 'SN-ENROLL-ARCHIVE-NATIVE',
                'status' => BiometricDeviceStatusEnum::Active,
                'is_enabled' => true,
                'provider_type' => null,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);
    }

    private function grantConsent(GymMember $member): void
    {
        BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);
    }

    /**
     * A provider that accepts any sample, so the enrollment request itself can
     * succeed for the control member.
     */
    private function mockAcceptingCloudProvider(): void
    {
        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('enroll')
            ->andReturn(new TemplateRef('rekognition', 'ext_enroll_archive', ['quality_score' => 0.91]));

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')
            ->andReturn($mockProvider);

        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cloudEnroll(array $payload): mixed
    {
        return $this->withHeaders($this->authHeaders($this->cloudToken))
            ->postJson('/api/v1/biometric/enroll', $payload);
    }

    #[Test]
    public function it_refuses_cloud_enrollment_for_an_archived_member_who_holds_active_consent(): void
    {
        $this->mockAcceptingCloudProvider();

        $response = $this->cloudEnroll([
            'gym_member_id' => $this->archivedMember->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
            'data' => 'archived-enrollment-sample',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'member_archived',
            ]);

        $this->assertDatabaseMissing('biometric_templates', [
            'gym_member_id' => $this->archivedMember->id,
        ]);
    }

    #[Test]
    public function it_enrolls_the_same_request_for_a_member_who_is_not_archived(): void
    {
        $this->mockAcceptingCloudProvider();

        $response = $this->cloudEnroll([
            'gym_member_id' => $this->activeMember->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
            'data' => 'active-enrollment-sample',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('biometric_templates', [
            'gym_member_id' => $this->activeMember->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_refuses_device_native_enrollment_sync_for_an_archived_member(): void
    {
        // The pending placeholder is the dangerous shape: the device can still
        // confirm it, so a refusal that only stopped new rows would leave the
        // archived member one request away from an active credential.
        $pending = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->archivedMember->id,
            'enrolled_by_device_id' => $this->deviceNativeDevice->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
        ]);

        // Asserted preconditions: this row really is the shape enroll-sync
        // would otherwise promote.
        $this->assertFalse((bool) $pending->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Pending, $pending->sync_status);
        $this->assertNull($pending->enrolled_at);

        $response = $this->withHeaders($this->authHeaders($this->deviceNativeToken))
            ->postJson('/api/v1/biometric/enroll-sync', [
                'gym_member_id' => $this->archivedMember->id,
                'biometric_type' => BiometricTypeEnum::Fingerprint->value,
                'template_ref' => (string) $this->archivedMember->id,
                'quality_score' => 0.95,
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'error' => 'member_archived',
            ]);

        $pending->refresh();

        $this->assertFalse((bool) $pending->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Pending, $pending->sync_status);
        $this->assertNull($pending->enrolled_at);
        $this->assertNull($pending->hardware_confirmed_at);
    }

    #[Test]
    public function it_refuses_enrollment_sync_that_would_otherwise_create_a_new_template(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->deviceNativeToken))
            ->postJson('/api/v1/biometric/enroll-sync', [
                'gym_member_id' => $this->archivedMember->id,
                'biometric_type' => BiometricTypeEnum::Fingerprint->value,
                'template_ref' => (string) $this->archivedMember->id,
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'error' => 'member_archived',
            ]);

        $this->assertDatabaseMissing('biometric_templates', [
            'gym_member_id' => $this->archivedMember->id,
        ]);
    }

    #[Test]
    public function the_refusal_carries_the_same_reason_the_check_in_guard_names(): void
    {
        // member_archived is what MembershipGuard::resolveReason() returns and
        // what the device-facing webhook maps to a message. Enrollment has to
        // speak the same language, or an operator triaging a device log cannot
        // tell an access revocation from a consent problem.
        $reason = resolve(MembershipGuard::class)
            ->canCheckIn($this->archivedMember->refresh())
            ->reason;

        $this->mockAcceptingCloudProvider();

        $response = $this->cloudEnroll([
            'gym_member_id' => $this->archivedMember->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
            'data' => 'archived-enrollment-sample',
        ]);

        $this->assertSame('member_archived', $reason);
        $response->assertJson(['error' => $reason]);
    }

    #[Test]
    public function it_compensates_a_provider_enrolment_when_the_member_is_archived_mid_enrolment(): void
    {
        // The TOCTOU the guard exists to close: the member is NOT archived at
        // the outer gate, and a concurrent archive lands while the provider is
        // creating the credential. The provider double performs that archive
        // from inside enroll(), so the check inside the transaction is the only
        // thing that can catch it — and the remote template it already created
        // must be compensated, or a refused enrolment leaves a live credential.
        $member = $this->activeMember;
        $orphanedRef = new TemplateRef('rekognition', 'ext_orphaned', ['quality_score' => 0.88]);

        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('enroll')
            ->once()
            ->andReturnUsing(function (GymMember $enrolled) use ($orphanedRef): TemplateRef {
                resolve(MemberArchiveService::class)->archiveAsSystem($enrolled);

                return $orphanedRef;
            });
        $mockProvider->shouldReceive('deleteTemplate')
            ->once()
            ->with(Mockery::on(fn (TemplateRef $ref): bool => $ref->externalId === 'ext_orphaned'))
            ->andReturnTrue();

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')->andReturn($mockProvider);
        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);

        $response = $this->cloudEnroll([
            'gym_member_id' => $member->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
            'data' => 'race-sample',
        ]);

        // The in-transaction archive guard fired...
        $response->assertStatus(422)
            ->assertJson(['error' => 'member_archived']);

        // The provider double archived the member INSIDE the enrolment
        // transaction, so that archive rolls back with the refused enrolment —
        // the member is not archived afterwards. What proves the refusal was for
        // the archive reason (and not an unrelated 422) is the error code above,
        // together with deleteTemplate() being invoked exactly once: the guard
        // observed the in-transaction archive and the compensation ran.
        $this->assertFalse((bool) $member->fresh()?->is_archived);

        // ...and no template of any kind — the rolled-back local row or a
        // compensation tombstone — survives for the member.
        $this->assertDatabaseMissing('biometric_templates', [
            'gym_member_id' => $member->id,
        ]);
        $this->assertSame(
            0,
            BiometricTemplate::query()->where('gym_member_id', $member->id)->count(),
            'A confirmed provider erase must not leave a PendingDeletion orphan marker.'
        );
    }

    #[Test]
    public function it_records_a_durable_orphan_when_the_provider_creates_a_template_then_throws(): void
    {
        // The create-then-throw case: the provider creates the remote
        // template and then throws before returning a reference (reachable in
        // Azure's trainPersonGroup flow). The controller holds no handle to
        // erase with, so nothing can ever discharge the obligation: the attempt
        // must be recorded as a durable, terminal tombstone for an operator —
        // not a PendingDeletion row the reconciler would retry forever — or the
        // live remote credential survives the refusal invisibly.
        $member = $this->activeMember;

        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('enroll')
            ->once()
            ->andThrow(new RuntimeException('provider exploded after creating the template'));
        // No reference was returned, so there is nothing to erase with.
        $mockProvider->shouldNotReceive('deleteTemplate');

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')->andReturn($mockProvider);
        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);

        $response = $this->cloudEnroll([
            'gym_member_id' => $member->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
            'data' => 'create-then-throw-sample',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'Enrollment failed']);

        $orphan = BiometricTemplate::query()
            ->where('gym_member_id', $member->id)
            ->where('sync_status', BiometricTemplateSyncStatusEnum::DeletionUnresolvable)
            ->first();

        $this->assertNotNull(
            $orphan,
            'A create-then-throw must leave a durable orphan marker for reconciliation.'
        );
        $this->assertTrue((bool) $orphan->is_erasure_obligation);
        $this->assertFalse((bool) $orphan->is_active);
        $this->assertNull($orphan->enrolled_at);
        $this->assertSame(BiometricProvidersEnum::Rekognition->value, $orphan->provider_type);
    }

    #[Test]
    public function it_records_an_orphan_when_the_provider_reports_a_failed_erase(): void
    {
        // Every shipped provider reports a failed erase by RETURNING false.
        // Ignoring that return value is what silently treated a failed erase as
        // success; the reference is kept on the marker so the reconciler can
        // retry it against the provider.
        $member = $this->activeMember;
        $ref = new TemplateRef('rekognition', 'ext_erase_refused', ['quality_score' => 0.9]);

        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('enroll')
            ->once()
            ->andReturnUsing(function (GymMember $enrolled) use ($ref): TemplateRef {
                resolve(MemberArchiveService::class)->archiveAsSystem($enrolled);

                return $ref;
            });
        $mockProvider->shouldReceive('deleteTemplate')
            ->once()
            ->with(Mockery::on(fn (TemplateRef $passed): bool => $passed->externalId === 'ext_erase_refused'))
            ->andReturnFalse();

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')->andReturn($mockProvider);
        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);

        $response = $this->cloudEnroll([
            'gym_member_id' => $member->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
            'data' => 'erase-refused-sample',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'member_archived']);

        $orphan = BiometricTemplate::query()
            ->where('gym_member_id', $member->id)
            ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
            ->first();

        $this->assertNotNull(
            $orphan,
            'A provider that returns false from deleteTemplate must leave a durable orphan marker.'
        );
        $this->assertNotNull($orphan->template_ref, 'The marker must keep the reference for a retry.');
        $this->assertStringContainsString(
            'ext_erase_refused',
            (string) $orphan->template_ref,
            'The marker must carry the provider reference so the reconciler can retry the erase.'
        );
    }

    #[Test]
    public function it_confirms_device_native_enrollment_sync_for_a_member_who_is_not_archived(): void
    {
        // The positive control for the pending-promotion refusal: the exact same
        // request against a live member must promote the placeholder, so the
        // 422 above cannot be passing for an unrelated reason.
        $pending = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->activeMember->id,
            'enrolled_by_device_id' => $this->deviceNativeDevice->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
        ]);

        $response = $this->withHeaders($this->authHeaders($this->deviceNativeToken))
            ->postJson('/api/v1/biometric/enroll-sync', [
                'gym_member_id' => $this->activeMember->id,
                'biometric_type' => BiometricTypeEnum::Fingerprint->value,
                'template_ref' => (string) $this->activeMember->id,
                'quality_score' => 0.95,
            ]);

        $response->assertOk();

        $pending->refresh();

        $this->assertTrue((bool) $pending->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $pending->sync_status);
    }

    #[Test]
    public function it_creates_a_device_native_template_for_a_member_who_is_not_archived(): void
    {
        // The positive control for the create-refusal: the same request against a
        // live member must create the template row.
        $response = $this->withHeaders($this->authHeaders($this->deviceNativeToken))
            ->postJson('/api/v1/biometric/enroll-sync', [
                'gym_member_id' => $this->activeMember->id,
                'biometric_type' => BiometricTypeEnum::Fingerprint->value,
                'template_ref' => (string) $this->activeMember->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('biometric_templates', [
            'gym_member_id' => $this->activeMember->id,
            'is_active' => true,
        ]);
    }

    /**
     * The authority of a device token over erasure is scoped to the rows it enrolled.
     *
     * Both devices are in the same gym, so the tenant check alone would pass. The device
     * that did not enrol the row must still be refused: a sibling device's token must not
     * drive an erasure on hardware it does not own, nor stamp the Art. 17 request marker
     * (`deleteSingleTemplate()`) on another device's credential.
     */
    #[Test]
    public function a_device_token_cannot_delete_a_template_another_device_enrolled(): void
    {
        $otherDevice = BiometricDevice::factory()->admsEnabled('SN-ENROLL-ARCHIVE-OTHER')->create([
            'gym_id' => $this->gym->id,
            'device_type' => 'fingerprint',
        ]);

        $foreignTemplate = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->activeMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $otherDevice->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => (string) $this->activeMember->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'hardware_confirmed_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($this->deviceNativeToken))
            ->deleteJson('/api/v1/biometric/template/' . $foreignTemplate->id);

        $response->assertForbidden()
            ->assertJson(['error' => 'Forbidden']);

        $foreignTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $foreignTemplate->sync_status);
        $this->assertTrue((bool) $foreignTemplate->is_active);
        $this->assertNull(
            $foreignTemplate->erasure_requested_at,
            'A refused delete must not stamp the Art. 17 request marker on another device\'s template.'
        );
        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $otherDevice->id,
            'command_type' => 'user_delete',
        ]);
    }

    /**
     * The positive control for the cross-device refusal: the same request from the device
     * that enrolled the row is authorised and the erasure obligation is recorded.
     */
    #[Test]
    public function a_device_token_can_delete_a_template_it_enrolled(): void
    {
        $ownTemplate = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->activeMember->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceNativeDevice->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => (string) $this->activeMember->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'hardware_confirmed_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($this->deviceNativeToken))
            ->deleteJson('/api/v1/biometric/template/' . $ownTemplate->id);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $ownTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $ownTemplate->sync_status);
        $this->assertNotNull($ownTemplate->erasure_requested_at);
    }
}
