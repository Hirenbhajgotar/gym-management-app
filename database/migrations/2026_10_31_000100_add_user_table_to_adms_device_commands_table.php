<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the ADMS user table schema on every queued command.
 *
 * The schema used to be read from the device at delivery time, so flipping the
 * per-device selector while a command was leased changed the wire format of a
 * command the terminal had already been told to expect. The snapshot also keeps
 * the serializer off the `device` relation.
 *
 * Existing rows are backfilled from their device's stored override (falling back
 * to the global default), so commands already queued before this deploy keep the
 * format their terminal is configured for. Terminal rows whose device can no
 * longer be resolved keep the global default; the column is nullable and the
 * serializer falls back at read time.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('adms_device_commands', function (Blueprint $table) {
            $table->string('user_table')->nullable()->after('command_payload');
        });

        $fallback = $this->normalize(config('biometrics.adms.user_table')) ?? 'USERINFO';

        DB::table('biometric_devices')
            ->select(['id', 'provider_config'])
            ->whereNotNull('provider_config')
            ->chunkById(100, function ($devices) use ($fallback): void {
                foreach ($devices as $device) {
                    $override = $this->storedOverride($device->provider_config, $device->id);

                    DB::table('adms_device_commands')
                        ->where('device_id', $device->id)
                        ->whereNull('user_table')
                        ->update(['user_table' => $override ?? $fallback]);
                }
            });

        DB::table('adms_device_commands')
            ->whereNull('user_table')
            ->update(['user_table' => $fallback]);
    }

    public function down(): void
    {
        Schema::table('adms_device_commands', function (Blueprint $table) {
            $table->dropColumn('user_table');
        });
    }

    private function storedOverride(mixed $raw, int|string|null $deviceId = null): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $config = json_decode(Crypt::decryptString($raw), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            Log::warning('Unable to read the stored ADMS user table override; falling back to the global default.', [
                'device_id' => $deviceId,
                'exception' => $exception::class,
            ]);

            return null;
        }

        return is_array($config) ? $this->normalize($config['adms_user_table'] ?? null) : null;
    }

    /**
     * Inlined on purpose: a migration must keep working even if the application
     * enum is renamed or removed later.
     */
    private function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return match (strtoupper(trim((string) $value))) {
            'USERINFO' => 'USERINFO',
            'USER' => 'user',
            default => null,
        };
    }
};
