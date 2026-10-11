<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Backfills the biometric consent per-type switch for gyms that predate it.
 *
 * The WhatsApp master switch gates biometric consent notifications, and the
 * per-type toggle underneath it defaults to off whenever no row exists — so every
 * gym created before the key was added had the channel silently unavailable with
 * no way to reach it except discovering the toggle by hand. Backfilling only
 * gyms whose master switch is already on inherits the tenant's existing
 * intent instead of overriding it; a gym that has WhatsApp switched off stays
 * off, which is what its master switch already decided.
 *
 * An absent row is indistinguishable from one an admin deleted by hand, and
 * whereNotExists cannot separate the two, so this treats both as absent: a
 * master-on gym gets the key back. That is the intended reading. The master
 * switch is the tenant's stated position on WhatsApp, and a compliance channel
 * staying off because a row was removed is a worse failure than a row
 * reappearing that one toggle can switch off again.
 */
return new class() extends Migration
{
    private const SETTING_KEY = 'biometric_consent_notifications';

    private const MASTER_KEY = 'whatsapp_notifications';

    public function up(): void
    {
        $now = now();

        // No ordering: the loop inserts one row per qualifying gym, so the row
        // order the SELECT returns has no effect on the outcome and ordering it
        // only costs a sort on the whole gym_settings table.
        DB::table('gym_settings')
            ->where('key', self::MASTER_KEY)
            ->whereNotExists(fn (Builder $query): Builder => $query
                ->select(DB::raw(1))
                ->from('gym_settings as existing')
                ->whereColumn('existing.gym_id', 'gym_settings.gym_id')
                ->where('existing.key', self::SETTING_KEY))
            ->get(['gym_id', 'value'])
            ->each(function (object $row) use ($now): void {
                if (! filter_var($row->value, FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }

                DB::table('gym_settings')->insert([
                    'gym_id' => $row->gym_id,
                    'key' => self::SETTING_KEY,
                    'value' => 'true',
                    'type' => 'boolean',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Removes the key entirely.
     *
     * The key did not exist before this migration, so deleting every row for it
     * is the correct inverse and the only one that leaves no residue — a gym
     * created after the feature shipped already carries this row from
     * GymSettingService::createDefaultSettings(), so scoping the delete to the
     * population up() seeded would strand rows for a key that is being rolled
     * back out of existence.
     */
    public function down(): void
    {
        DB::table('gym_settings')
            ->where('key', self::SETTING_KEY)
            ->delete();
    }
};
