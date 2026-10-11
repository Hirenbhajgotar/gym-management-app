<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\Services\BiometricDataDeletionService;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\Attendances;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class BiometricDataDeletionPartialFailureTest extends TestCase
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
    }

    #[Test]
    public function attendances_and_consents_are_preserved_if_any_template_deletion_fails(): void
    {
        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);

        $attendance = Attendances::query()->create([
            'gym_id' => $this->gym->id,
            'attendanceable_type' => GymMember::class,
            'attendanceable_id' => $this->member->id,
            'check_in_time' => now(),
            'check_in_method' => 'biometric',
            'device_id' => $this->device->id,
            'liveness_score' => 0.95,
        ]);

        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('deleteTemplate')
            ->once()
            ->andReturn(false);

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')
            ->with('custom_cloud')
            ->andReturn($mockProvider);
        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);

        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'custom_cloud',
            'template_ref' => 'ref_1',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        $service = resolve(BiometricDataDeletionService::class);

        $caught = false;
        try {
            $service->deleteForGymMember($this->member);
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertStringContainsString('Biometric data deletion failed for 1 template', $e->getMessage());
        }

        $this->assertTrue($caught, 'Expected RuntimeException was not thrown on partial template deletion failure');

        // Assert attendance was NOT anonymized
        $attendance->refresh();
        $this->assertSame($this->device->id, $attendance->device_id);
        $this->assertSame(0.95, (float) $attendance->liveness_score);

        // Assert consent was NOT deleted
        $this->assertDatabaseHas('biometric_consents', [
            'gym_member_id' => $this->member->id,
        ]);
    }

    #[Test]
    public function attendances_anonymized_and_consent_deleted_when_all_templates_succeed(): void
    {
        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);

        $attendance = Attendances::query()->create([
            'gym_id' => $this->gym->id,
            'attendanceable_type' => GymMember::class,
            'attendanceable_id' => $this->member->id,
            'check_in_time' => now(),
            'check_in_method' => 'biometric',
            'device_id' => $this->device->id,
            'liveness_score' => 0.95,
        ]);

        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => 'ref_1',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        $service = resolve(BiometricDataDeletionService::class);
        $service->deleteForGymMember($this->member);

        // Assert attendance WAS anonymized
        $attendance->refresh();
        $this->assertNull($attendance->device_id);
        $this->assertNull($attendance->liveness_score);

        // Assert consent WAS deleted
        $this->assertDatabaseMissing('biometric_consents', [
            'gym_member_id' => $this->member->id,
        ]);
    }

    #[Test]
    public function multi_template_partial_failure_leaves_successful_template_processed_and_retains_consent_and_attendance(): void
    {
        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);

        $attendance = Attendances::query()->create([
            'gym_id' => $this->gym->id,
            'attendanceable_type' => GymMember::class,
            'attendanceable_id' => $this->member->id,
            'check_in_time' => now(),
            'check_in_method' => 'biometric',
            'device_id' => $this->device->id,
            'liveness_score' => 0.95,
        ]);

        // Template 1: Device template (never confirmed on hardware -> deleted immediately)
        $template1 = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => 'ref_1',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        // Template 2: Cloud template that will fail
        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('deleteTemplate')
            ->once()
            ->andReturn(false);

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')
            ->with('custom_cloud')
            ->andReturn($mockProvider);
        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);

        $template2 = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'custom_cloud',
            'template_ref' => 'ref_2',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        $service = resolve(BiometricDataDeletionService::class);

        $caught = false;
        try {
            $service->deleteForGymMember($this->member);
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertStringContainsString('Biometric data deletion failed for 1 template', $e->getMessage());
        }

        $this->assertTrue($caught);

        // Template 1 was processed and marked Deleted
        $template1->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template1->sync_status);
        $this->assertFalse($template1->is_active);

        // Template 2 failed and was parked at PendingDeletion
        $template2->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template2->sync_status);
        $this->assertFalse($template2->is_active);

        // Attendance and Consent preserved
        $attendance->refresh();
        $this->assertSame($this->device->id, $attendance->device_id);
        $this->assertDatabaseHas('biometric_consents', [
            'gym_member_id' => $this->member->id,
        ]);
    }
}
