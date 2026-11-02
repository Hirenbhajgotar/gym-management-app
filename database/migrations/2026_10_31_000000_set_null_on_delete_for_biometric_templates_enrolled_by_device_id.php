<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detach enrolled templates instead of blocking device deletion.
 *
 * A null `enrolled_by_device_id` is *not* evidence that the credential was
 * erased — the terminal's flash is untouched — so
 * {@see \App\Biometrics\Services\BiometricDeProvisioner} parks rows with delivery
 * evidence at `DeletionUnresolvable` rather than writing `Deleted`.
 *
 * Rolling this back restores the restrictive key, but it is impossible while a
 * *hardware* credential is detached: its terminal may still hold the template,
 * and a null device is not proof of erasure. Cloud-backed rows legitimately had
 * a null `enrolled_by_device_id` before this changeset, so they do not block the
 * restrictive key. The migration refuses rather than implying the operation is
 * data-reversible (MySQL DDL is non-transactional, so `up()`'s two statements
 * can partially apply).
 */
return new class() extends Migration
{
    public function up(): void
    {
        if ($this->hasForeignKeyOn('biometric_templates', 'enrolled_by_device_id')) {
            Schema::table('biometric_templates', function (Blueprint $table) {
                $table->dropForeign(['enrolled_by_device_id']);
            });
        }

        if (! $this->hasForeignKeyOn('biometric_templates', 'enrolled_by_device_id')) {
            Schema::table('biometric_templates', function (Blueprint $table) {
                $table->foreign('enrolled_by_device_id')
                    ->references('id')
                    ->on('biometric_devices')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->detachedHardwareCredentialsExist()) {
            throw new RuntimeException(
                'Cannot restore the restrictive enrolled_by_device_id foreign key: detached hardware credentials exist. '
                . 'Re-bind or archive those rows deliberately before rolling back; a null device on a hardware credential is not proof of erasure.'
            );
        }

        if ($this->hasForeignKeyOn('biometric_templates', 'enrolled_by_device_id')) {
            Schema::table('biometric_templates', function (Blueprint $table) {
                $table->dropForeign(['enrolled_by_device_id']);
            });
        }

        if (! $this->hasForeignKeyOn('biometric_templates', 'enrolled_by_device_id')) {
            Schema::table('biometric_templates', function (Blueprint $table) {
                $table->foreign('enrolled_by_device_id')
                    ->references('id')
                    ->on('biometric_devices');
            });
        }
    }

    /**
     * Whether rolling back would strand a detached hardware credential.
     *
     * Cloud-backed rows legitimately had a null `enrolled_by_device_id` before this
     * changeset, so they do not block the restrictive key; only a hardware credential
     * whose terminal may still hold it does. Extracted from `down()` so the guard can be
     * asserted without executing DDL against the shared test schema.
     */
    private function detachedHardwareCredentialsExist(): bool
    {
        return DB::table('biometric_templates')
            ->whereNull('enrolled_by_device_id')
            ->where(function (Builder $query): void {
                $query->whereNull('provider_type')
                    ->orWhereIn('provider_type', ['device_native', 'adms', 'hikvision']);
            })
            ->exists();
    }

    /**
     * Whether the table already carries a foreign key on the given column.
     */
    private function hasForeignKeyOn(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
