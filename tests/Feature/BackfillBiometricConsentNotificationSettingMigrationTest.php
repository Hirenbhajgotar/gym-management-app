<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The backfill seeds a per-type switch for gyms that predate it, and it has to
 * do so without touching a gym that has already made a decision.
 *
 * GymSettingService::createDefaultSettings() already writes this key for every
 * gym created after the feature shipped, so the factory leaves a row behind on
 * each fixture. Each test deletes the rows it means to control rather than
 * assuming a clean slate, otherwise "the migration did nothing" and "the
 * migration wrote true" are indistinguishable.
 */
class BackfillBiometricConsentNotificationSettingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const SETTING_KEY = 'biometric_consent_notifications';

    private const MASTER_KEY = 'whatsapp_notifications';

    #[Test]
    public function it_backfills_a_gym_whose_master_switch_is_on_and_which_has_no_per_type_row(): void
    {
        $gym = $this->gymWithMasterSwitch('true');
        $this->forgetPerTypeRow($gym);

        $this->runMigrationMethod($this->migration(), 'up');

        $this->assertTrue(
            $this->perTypeValue($gym),
            'A gym with WhatsApp on must not stay locked out of the biometric consent channel.'
        );
    }

    #[Test]
    public function it_leaves_a_gym_alone_when_its_master_switch_is_off(): void
    {
        $gym = $this->gymWithMasterSwitch('false');
        $this->forgetPerTypeRow($gym);

        $this->runMigrationMethod($this->migration(), 'up');

        $this->assertNull(
            $this->perTypeValue($gym),
            'The master switch already decided this gym is off; backfilling would override it.'
        );
    }

    #[Test]
    public function it_does_not_overwrite_a_per_type_row_that_already_exists(): void
    {
        $gym = $this->gymWithMasterSwitch('true');

        $this->putSetting($gym, self::SETTING_KEY, 'false');

        $this->runMigrationMethod($this->migration(), 'up');

        $this->assertFalse(
            $this->perTypeValue($gym),
            'A gym that opted out before the migration must stay opted out.'
        );
    }

    /**
     * whereNotExists cannot tell a row an admin deleted from one that was never
     * seeded, so this pins the reading this migration actually implements rather
     * than leaving it implied. It is a deliberate trade: the master switch is the
     * tenant's stated position on WhatsApp, and a compliance channel staying off
     * because a row was removed is the worse failure — the gym can switch it off
     * again in one click, but cannot discover that it needs to.
     */
    #[Test]
    public function it_reinstates_the_per_type_key_when_a_master_on_gym_has_no_row_at_all(): void
    {
        $gym = $this->gymWithMasterSwitch('true');

        $this->putSetting($gym, self::SETTING_KEY, 'true');
        $this->forgetPerTypeRow($gym);

        $this->assertNull(
            $this->perTypeValue($gym),
            'Fixture must leave the member in the deleted-row state this test is about.'
        );

        $this->runMigrationMethod($this->migration(), 'up');

        $this->assertTrue(
            $this->perTypeValue($gym),
            'A master-on gym with no per-type row is treated as never-seeded, and gets the key back.'
        );
    }

    #[Test]
    public function it_does_not_touch_a_gym_with_no_master_row_at_all(): void
    {
        $gym = Gym::factory()->create();
        $this->forgetSetting($gym, self::MASTER_KEY);
        $this->forgetPerTypeRow($gym);

        $this->runMigrationMethod($this->migration(), 'up');

        $this->assertNull($this->perTypeValue($gym));
    }

    #[Test]
    public function it_removes_the_key_entirely_on_rollback(): void
    {
        $backfilled = $this->gymWithMasterSwitch('true');
        $this->forgetPerTypeRow($backfilled);

        $alsoBackfilled = $this->gymWithMasterSwitch('true');
        $this->forgetPerTypeRow($alsoBackfilled);

        $migration = $this->migration();
        $this->runMigrationMethod($migration, 'up');

        $this->assertTrue($this->perTypeValue($backfilled));
        $this->assertTrue($this->perTypeValue($alsoBackfilled));

        $this->runMigrationMethod($migration, 'down');

        $this->assertNull(
            $this->perTypeValue($backfilled),
            'The key did not exist before this migration, so a rollback must leave none of it behind.'
        );
        $this->assertNull($this->perTypeValue($alsoBackfilled));
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_08_000000_backfill_biometric_consent_notification_setting.php');

        return $migration;
    }

    /**
     * Migration declares no up()/down(), they are only invoked dynamically, so
     * the call goes through callable rather than a method the analyser cannot see.
     *
     * @param  callable-string  $method
     */
    private function runMigrationMethod(Migration $migration, string $method): void
    {
        $this->assertTrue(
            method_exists($migration, $method),
            "The migration must expose {$method}()."
        );

        call_user_func([$migration, $method]);
    }

    private function gymWithMasterSwitch(string $value): Gym
    {
        $gym = Gym::factory()->create();

        $this->putSetting($gym, self::MASTER_KEY, $value);

        return $gym;
    }

    private function putSetting(Gym $gym, string $key, string $value): void
    {
        GymSetting::query()->updateOrCreate(
            ['gym_id' => $gym->id, 'key' => $key],
            ['value' => $value, 'type' => 'boolean'],
        );
    }

    private function forgetSetting(Gym $gym, string $key): void
    {
        GymSetting::query()
            ->where('gym_id', $gym->id)
            ->where('key', $key)
            ->delete();
    }

    private function forgetPerTypeRow(Gym $gym): void
    {
        $this->forgetSetting($gym, self::SETTING_KEY);
    }

    /**
     * Read the way NotificationChannelResolver reads it: through the accessor,
     * which returns a real bool for a boolean-typed row.
     */
    private function perTypeValue(Gym $gym): ?bool
    {
        $row = GymSetting::query()
            ->where('gym_id', $gym->id)
            ->where('key', self::SETTING_KEY)
            ->first();

        return $row?->value;
    }
}
