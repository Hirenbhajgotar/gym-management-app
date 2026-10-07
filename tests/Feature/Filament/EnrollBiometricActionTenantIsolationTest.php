<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Actions\EnrollBiometricAction;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollBiometricActionTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected Gym $gymA;

    protected BiometricDevice $deviceA;

    protected GymMember $memberA;

    protected User $userB;

    protected Gym $gymB;

    protected BiometricDevice $deviceB;

    protected GymMember $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        // Gym A setup
        $this->gymA = Gym::factory()->create();
        setPermissionsTeamId($this->gymA->id);

        $roleA = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $permission = Permission::query()->firstOrCreate([
            'name' => 'update_gym_member',
            'guard_name' => 'web',
        ]);
        $roleA->givePermissionTo($permission);

        $this->userA = User::factory()->create();
        $this->gymA->users()->attach($this->userA->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->userA->assignRole($roleA);

        $this->deviceA = BiometricDevice::factory()->create([
            'gym_id' => $this->gymA->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_name' => 'Gym A Entrance',
            'device_serial' => 'SN-GYM-A-01',
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        $this->memberA = GymMember::factory()->create([
            'gym_id' => $this->gymA->id,
            'name' => 'Alice Member A',
        ]);

        $this->grantConsent($this->memberA);

        // Gym B setup
        $this->gymB = Gym::factory()->create();
        $this->userB = User::factory()->create();
        $this->gymB->users()->attach($this->userB->id, ['role' => DefaultRole::GYM_OWNER->value]);

        $this->deviceB = BiometricDevice::factory()->create([
            'gym_id' => $this->gymB->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_name' => 'Gym B Entrance',
            'device_serial' => 'SN-GYM-B-01',
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        $this->memberB = GymMember::factory()->create([
            'gym_id' => $this->gymB->id,
            'name' => 'Bob Member B',
        ]);

        $this->grantConsent($this->memberB);
    }

    protected function grantConsent(GymMember $member): BiometricConsent
    {
        return BiometricConsent::query()->create([
            'gym_id' => $member->gym_id,
            'gym_member_id' => $member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);
    }

    public function test_cannot_authorize_enrollment_for_member_from_another_tenant(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        $this->assertTrue(EnrollBiometricAction::canAuthorize($this->memberA));
        $this->assertFalse(EnrollBiometricAction::canAuthorize($this->memberB));
    }

    public function test_cannot_enroll_to_device_from_another_tenant(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        // Attempting to enroll using Gym B's device for Gym A's member must fail
        EnrollBiometricAction::handle($this->memberA, [
            'device_id' => $this->deviceB->id,
            'biometric_type' => 'fingerprint',
        ]);

        $this->assertDatabaseMissing('biometric_templates', [
            'gym_member_id' => $this->memberA->id,
            'enrolled_by_device_id' => $this->deviceB->id,
        ]);

        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->deviceB->id,
            'command_type' => 'user_add',
        ]);
    }

    public function test_successful_enrollment_cancels_pending_and_sent_commands_and_marks_superseded(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        // Pre-existing pending placeholder
        $oldTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gymA->id,
            'gym_member_id' => $this->memberA->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => 'fingerprint',
            'template_ref' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'is_active' => false,
        ]);

        // Pre-existing delete commands
        $pendingCmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gymA->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->memberA->id],
            'idempotency_key' => 'user_delete_1',
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $sentCmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gymA->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->memberA->id],
            'idempotency_key' => 'user_delete_2',
            'status' => AdmsCommandStatusEnum::SENT,
        ]);

        // Pre-existing user_add command
        $pendingAddCmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gymA->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->memberA->id, 'Name' => $this->memberA->name],
            'idempotency_key' => 'user_add_old',
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        EnrollBiometricAction::handle($this->memberA, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => 'fingerprint',
        ]);

        // Old placeholder must be marked Superseded
        $oldTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $oldTemplate->sync_status);

        // Delete commands must be cancelled and idempotency_key nulled (SF12)
        $pendingCmd->refresh();
        $sentCmd->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pendingCmd->status);
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $sentCmd->status);
        $this->assertNull($pendingCmd->idempotency_key);
        $this->assertNull($sentCmd->idempotency_key);

        // Prior user_add command must be cancelled and idempotency_key nulled (SF12)
        $pendingAddCmd->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pendingAddCmd->status);
        $this->assertNull($pendingAddCmd->idempotency_key);

        // New placeholder exists
        $newTemplate = BiometricTemplate::query()
            ->where('gym_id', $this->gymA->id)
            ->where('gym_member_id', $this->memberA->id)
            ->where('sync_status', BiometricTemplateSyncStatusEnum::Pending)
            ->latest('id')
            ->firstOrFail();

        // New command queued with tenant-scoped idempotency key
        $this->assertDatabaseHas('adms_device_commands', [
            'gym_id' => $this->gymA->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_add',
            'idempotency_key' => "user_add:{$this->gymA->id}:{$this->deviceA->id}:{$this->memberA->id}:{$newTemplate->id}",
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    public function test_device_select_options_are_tenant_scoped_and_filtered(): void
    {
        // Inactive device in Gym A
        $inactiveDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gymA->id,
            'status' => BiometricDeviceStatusEnum::Inactive,
            'adms_enabled' => true,
            'device_name' => 'Inactive Device',
        ]);

        // ADMS disabled device in Gym A
        $nonAdmsDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gymA->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => false,
            'device_name' => 'Non ADMS Device',
        ]);

        $schema = EnrollBiometricAction::getFormSchema();
        $deviceSelect = collect($schema)->first(fn ($component) => $component instanceof Select && $component->getName() === 'device_id');
        $this->assertNotNull($deviceSelect);

        $optionsProp = new ReflectionProperty($deviceSelect, 'options');
        /** @var callable $optionsClosure */
        $optionsClosure = $optionsProp->getValue($deviceSelect);

        // 1. With memberA (Gym A): should see deviceA only
        /** @var array<int|string, mixed> $options */
        $options = $optionsClosure($this->memberA, null);
        $this->assertArrayHasKey($this->deviceA->id, $options);
        $this->assertArrayNotHasKey($this->deviceB->id, $options, 'Gym B device must not be disclosed in Gym A');
        $this->assertArrayNotHasKey($inactiveDevice->id, $options, 'Inactive device must be excluded');
        $this->assertArrayNotHasKey($nonAdmsDevice->id, $options, 'Non-ADMS device must be excluded');

        // 2. Early return [] when neither tenant nor member is set
        Filament::setTenant(null);
        $emptyOptions = $optionsClosure(null, null);
        $this->assertSame([], $emptyOptions);
    }

    public function test_device_select_after_state_updated_sets_matching_biometric_type(): void
    {
        $faceDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gymA->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'device_type' => 'face',
            'device_name' => 'Gym A Face Terminal',
        ]);

        $schema = EnrollBiometricAction::getFormSchema();
        $deviceSelect = collect($schema)->first(fn ($component) => $component instanceof Select && $component->getName() === 'device_id');
        $this->assertNotNull($deviceSelect);

        $setTarget = null;
        $setValue = null;
        $fakeSet = function (string $target, mixed $value) use (&$setTarget, &$setValue): void {
            $setTarget = $target;
            $setValue = $value;
        };

        // Call afterStateUpdated callback
        $callbacksProp = new ReflectionProperty($deviceSelect, 'afterStateUpdated');
        $callbacks = $callbacksProp->getValue($deviceSelect);
        $callback = is_array($callbacks) ? ($callbacks[0] ?? null) : $callbacks;
        $this->assertNotNull($callback);
        $this->assertIsCallable($callback);

        $callback($faceDevice->id, $fakeSet, $this->memberA, null);

        $this->assertSame('biometric_type', $setTarget);
        $this->assertSame('face', $setValue);
    }

    public function test_consent_gate_blocks_enrollment_when_no_consent(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        $unconsentedMember = GymMember::factory()->create([
            'gym_id' => $this->gymA->id,
            'name' => 'Charlie Unconsented',
        ]);

        EnrollBiometricAction::handle($unconsentedMember, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => 'fingerprint',
        ]);

        $this->assertDatabaseMissing('biometric_templates', [
            'gym_member_id' => $unconsentedMember->id,
        ]);

        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->deviceA->id,
            'command_payload->Pin' => (string) $unconsentedMember->id,
        ]);
    }

    public function test_action_escapes_xss_payloads_in_member_name_and_device_name(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        $xssMember = GymMember::factory()->create([
            'gym_id' => $this->gymA->id,
            'name' => 'Alice <script>alert("xss")</script>',
        ]);

        $xssDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gymA->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_name' => 'Gym A Entrance <script>alert(1)</script>',
            'device_serial' => 'SN-XSS-01',
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $xssMember->id,
            'gym_id' => $this->gymA->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);

        EnrollBiometricAction::handle($xssMember, [
            'device_id' => $xssDevice->id,
            'biometric_type' => 'fingerprint',
        ]);

        // Verify successful creation without unescaped script tag in command
        $this->assertDatabaseHas('biometric_templates', [
            'gym_member_id' => $xssMember->id,
            'enrolled_by_device_id' => $xssDevice->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending->value,
        ]);

        // SF-13 & SF-17: Assert notification output properly escapes the XSS payload for device name
        /** @var array<int, array<string, mixed>> $notifications */
        $notifications = (array) session()->get('filament.notifications', []);
        $this->assertNotEmpty($notifications);
        $firstNotification = $notifications[0] ?? [];
        $body = is_string($firstNotification['body'] ?? null)
            ? $firstNotification['body']
            : '';
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);

        Notification::assertNotified('Biometric Enrollment Initiated');

        // SF-17: Assert member name XSS is properly escaped in notification
        $unconsentedXssMember = GymMember::factory()->create([
            'gym_id' => $this->gymA->id,
            'name' => 'Alice <script>alert("member_xss")</script>',
        ]);

        EnrollBiometricAction::handle($unconsentedXssMember, [
            'device_id' => $xssDevice->id,
            'biometric_type' => 'fingerprint',
        ]);

        /** @var array<int, array<string, mixed>> $updatedNotifications */
        $updatedNotifications = (array) session()->get('filament.notifications', []);
        $this->assertNotEmpty($updatedNotifications);
        $lastNotification = end($updatedNotifications);
        $consentBody = is_array($lastNotification) && is_string($lastNotification['body'] ?? null)
            ? $lastNotification['body']
            : '';
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;member_xss&quot;)&lt;/script&gt;', $consentBody);
        $this->assertStringNotContainsString('<script>alert("member_xss")</script>', $consentBody);
    }

    public function test_handle_throws_authorization_exception_when_operator_is_unauthorized(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userB);
        Filament::setTenant($this->gymB);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Unauthorized biometric enrollment initiation.');

        EnrollBiometricAction::handle($this->memberA, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => 'fingerprint',
        ]);
    }
}
