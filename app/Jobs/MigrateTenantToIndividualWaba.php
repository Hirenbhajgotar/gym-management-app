<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WabaMigration;
use App\Services\WabaMigrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateTenantToIndividualWaba implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $migrationId
    ) {}

    public function handle(WabaMigrationService $migrationService): void
    {
        $migration = WabaMigration::query()->find($this->migrationId);

        if (! $migration) {
            Log::error("Migration not found: {$this->migrationId}");

            return;
        }

        if (! $migration->isPending()) {
            Log::warning("Migration is not pending: {$this->migrationId}", [
                'status' => $migration->status->value,
            ]);

            return;
        }

        $migrationService->processMigration($migration);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('MigrateTenantToIndividualWaba job failed', [
            'migration_id' => $this->migrationId,
            'error' => $exception?->getMessage(),
        ]);

        $migration = WabaMigration::query()->find($this->migrationId);

        if ($migration) {
            $migration->markAsFailed($exception?->getMessage() ?? 'Job failed');
        }
    }
}
