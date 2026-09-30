<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupSupersededTemplatesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected GymMember $member;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    public function test_dry_run_previews_without_deleting_superseded_templates(): void
    {
        $oldTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'updated_at' => now()->subDays(35),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 superseded template(s) older than 30 days to purge (dry-run).')
            ->assertSuccessful();

        $this->assertDatabaseHas('biometric_templates', ['id' => $oldTemplate->id]);
    }

    public function test_purges_superseded_templates_older_than_retention_period(): void
    {
        $oldSuperseded = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'updated_at' => now()->subDays(35),
        ]);

        $recentSuperseded = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'updated_at' => now()->subDays(10),
        ]);

        $activeSynced = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Purged 1 superseded template(s) older than 30 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('biometric_templates', ['id' => $oldSuperseded->id]);
        $this->assertDatabaseHas('biometric_templates', ['id' => $recentSuperseded->id]);
        $this->assertDatabaseHas('biometric_templates', ['id' => $activeSynced->id]);
    }

    public function test_rejects_non_positive_days_argument(): void
    {
        $this->artisan('biometrics:cleanup-superseded-templates', ['--days' => 0])
            ->expectsOutputToContain('--days must be an integer greater than 0.')
            ->assertFailed();

        $this->artisan('biometrics:cleanup-superseded-templates', ['--days' => -5])
            ->expectsOutputToContain('--days must be an integer greater than 0.')
            ->assertFailed();
    }

    public function test_does_not_emit_device_delete_command_when_purging_superseded_templates(): void
    {
        $oldSuperseded = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'template_ref' => 'REF_PIN_987',
            'updated_at' => now()->subDays(35),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Purged 1 superseded template(s) older than 30 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('biometric_templates', ['id' => $oldSuperseded->id]);
        $this->assertDatabaseMissing('adms_device_commands', [
            'command_type' => 'user_delete',
        ]);
    }
}
