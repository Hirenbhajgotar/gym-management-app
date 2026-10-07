<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillHardwareConfirmedAtMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_up_backfills_hardware_confirmed_at_for_synced_templates(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create(['gym_id' => $gym->id]);

        $enrolledTime = '2026-01-01 10:00:00';

        $syncedTemplate = BiometricTemplate::query()->create([
            'gym_id' => $gym->id,
            'gym_member_id' => $member->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => 'ref_1',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => $enrolledTime,
            'hardware_confirmed_at' => null,
        ]);

        $pendingTemplate = BiometricTemplate::query()->create([
            'gym_id' => $gym->id,
            'gym_member_id' => $member->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => 'ref_2',
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $propagatedTemplate = BiometricTemplate::query()->create([
            'gym_id' => $gym->id,
            'gym_member_id' => $member->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'device_native',
            'template_ref' => 'ref_3',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Propagated,
            'enrolled_at' => $enrolledTime,
            'hardware_confirmed_at' => null,
        ]);

        $migration = require database_path('migrations/2026_10_03_000002_backfill_hardware_confirmed_at_from_enrolled_at.php');

        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'up'), 'The migration must expose up().');
        $this->assertTrue(method_exists($migration, 'down'), 'The migration must expose down().');

        call_user_func([$migration, 'up']);

        // A data migration must be right by construction: once it has run, the historical
        // evidence it repairs no longer exists to re-derive from.
        $this->assertSame(
            $enrolledTime,
            $syncedTemplate->refresh()->hardware_confirmed_at?->format('Y-m-d H:i:s'),
            'A synced template with enrolled_at must be backfilled.'
        );

        $this->assertNull(
            $pendingTemplate->refresh()->hardware_confirmed_at,
            'A pending placeholder has no enrolled_at and must not be backfilled.'
        );

        $this->assertNull(
            $propagatedTemplate->refresh()->hardware_confirmed_at,
            'A propagated template must not be backfilled from enrolled_at.'
        );
    }
}
