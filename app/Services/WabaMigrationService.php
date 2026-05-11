<?php

namespace App\Services;

use App\Enums\WabaMigrationStatusEnum;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Jobs\MigrateTenantToIndividualWaba;
use App\Models\WabaMigration;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated This service is no longer in use. All WABA migrations have been completed.
 */
class WabaMigrationService
{
    public function __construct(
        private EmbeddedSignupService $embeddedSignupService,
        private TwilioAccountManager $accountManager
    ) {}

    public function getPendingMigrations(): int
    {
        return WabaMigration::where('status', WabaMigrationStatusEnum::PENDING)->count();
    }

    public function getGymsNeedingMigration(): array
    {
        return [];
    }

    public function createMigrationForGym(int $gymId): ?WabaMigration
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            Log::warning("No WhatsApp account found for gym {$gymId}");

            return null;
        }

        if ($account->isWabaProvider() && filled($account->waba_id)) {
            Log::info("Gym {$gymId} already has individual/manual WABA, skipping migration");

            return null;
        }

        $existingMigration = WabaMigration::where('gym_id', $gymId)
            ->whereIn('status', [
                WabaMigrationStatusEnum::PENDING,
                WabaMigrationStatusEnum::IN_PROGRESS,
            ])
            ->first();

        if ($existingMigration) {
            Log::info("Migration already exists for gym {$gymId}");

            return $existingMigration;
        }

        return WabaMigration::createForGym(
            $gymId,
            $account->id,
            $account->waba_id ?? 'unknown',
            'shared'
        );
    }

    public function createMigrationsForAllGyms(): int
    {
        $gymIds = $this->getGymsNeedingMigration();
        $created = 0;

        foreach ($gymIds as $gymId) {
            $migration = $this->createMigrationForGym($gymId);
            if ($migration) {
                $created++;
            }
        }

        Log::info("Created {$created} WABA migrations");

        return $created;
    }

    public function startMigration(int $gymId): array
    {
        $migration = WabaMigration::where('gym_id', $gymId)
            ->where('status', WabaMigrationStatusEnum::PENDING)
            ->first();

        if (! $migration) {
            return [
                'success' => false,
                'error' => 'No pending migration found for this gym',
            ];
        }

        MigrateTenantToIndividualWaba::dispatch($migration->id);

        return [
            'success' => true,
            'message' => 'Migration started',
            'migration_id' => $migration->id,
        ];
    }

    public function processMigration(WabaMigration $migration): bool
    {
        $migration->markAsStarted();

        $account = $migration->whatsappAccount;

        if (! $account) {
            $migration->markAsFailed('WhatsApp account not found');

            return false;
        }

        try {
            $embeddedUrl = $this->embeddedSignupService->getLoginUrl($migration->gym_id);

            Log::info('Migration step 1: Generated Embedded Signup URL', [
                'migration_id' => $migration->id,
                'gym_id' => $migration->gym_id,
            ]);

            $migration->update([
                'migration_data' => array_merge($migration->migration_data ?? [], [
                    'embedded_signup_url' => $embeddedUrl,
                    'step' => 'awaiting_embedded_signup',
                ]),
            ]);

            return true;
        } catch (\Exception $e) {
            $migration->markAsFailed($e->getMessage());

            Log::error('Migration failed', [
                'migration_id' => $migration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function completeMigration(WabaMigration $migration, string $newWabaId, string $newPhoneNumber): bool
    {
        $account = $migration->whatsappAccount;

        if (! $account) {
            $migration->markAsFailed('WhatsApp account not found');

            return false;
        }

        try {
            $templates = WhatsAppTemplate::where('gym_id', $migration->gym_id)->get();

            $migration->update([
                'migration_data' => array_merge($migration->migration_data ?? [], [
                    'old_template_count' => $templates->count(),
                    'templates_to_recreate' => $templates->pluck('id')->toArray(),
                    'step' => 'migrating_templates',
                ]),
            ]);

            $account->update([
                'waba_id' => $newWabaId,
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
                'embedded_signup_completed' => true,
            ]);

            $migration->markAsCompleted($newWabaId, 'individual');

            return true;
        } catch (\Exception $e) {
            $migration->markAsFailed($e->getMessage());

            Log::error('Migration completion failed', [
                'migration_id' => $migration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getMigrationStatus(int $gymId): ?array
    {
        $migration = WabaMigration::where('gym_id', $gymId)
            ->latest()
            ->first();

        if (! $migration) {
            return null;
        }

        return [
            'id' => $migration->id,
            'status' => $migration->status->value,
            'old_waba_id' => $migration->old_waba_id,
            'new_waba_id' => $migration->new_waba_id,
            'started_at' => $migration->started_at,
            'completed_at' => $migration->completed_at,
            'failed_at' => $migration->failed_at,
            'notes' => $migration->migration_notes,
        ];
    }
}
