<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WabaMigrationService;
use Illuminate\Console\Command;

class WhatsAppMigrateToIndividualWaba extends Command
{
    protected $signature = 'whatsapp:migrate-to-individual-waba
                            {--dry-run : Show what would be migrated without making changes}
                            {--gym= : Migrate a specific gym by ID}';

    protected $description = 'Migrate existing tenants from shared WABA to individual WABA';

    public function handle(WabaMigrationService $migrationService): int
    {
        $this->info('WhatsApp WABA Migration Tool');
        $this->newLine();

        if (! config('services.twilio.use_individual_waba')) {
            $this->error('Individual WABA mode is not enabled.');
            $this->info('Set TWILIO_USE_INDIVIDUAL_WABA=true in your .env file.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        if ($gymId = $this->option('gym')) {
            return $this->migrateSingleGym($migrationService, (int) $gymId);
        }

        return $this->migrateAllGyms($migrationService);
    }

    private function migrateSingleGym(WabaMigrationService $migrationService, int $gymId): int
    {
        $this->info("Migrating gym ID: {$gymId}");

        if ($this->option('dry-run')) {
            $this->line("  Would create migration for gym {$gymId}");

            return self::SUCCESS;
        }

        $migration = $migrationService->createMigrationForGym($gymId);

        if (! $migration) {
            $this->error("  Could not create migration for gym {$gymId}");

            return self::FAILURE;
        }

        $this->info("  Migration created: ID {$migration->id}");

        if ($this->confirm('Start the migration now?', true)) {
            $result = $migrationService->startMigration($gymId);

            if ($result['success']) {
                $this->info('  Migration started successfully');
                $this->info('  The tenant will need to complete the Embedded Signup flow.');
            } else {
                $this->error("  Failed to start migration: {$result['error']}");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function migrateAllGyms(WabaMigrationService $migrationService): int
    {
        $gymsNeedingMigration = $migrationService->getGymsNeedingMigration();
        $count = count($gymsNeedingMigration);

        if ($count === 0) {
            $this->info('No gyms need migration. All gyms are already on individual WABA.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} gym(s) needing migration:");
        $this->newLine();

        if ($this->option('dry-run')) {
            foreach ($gymsNeedingMigration as $gymId) {
                $this->line("  - Gym ID: {$gymId}");
            }

            $this->newLine();
            $this->info('Run without --dry-run to create migrations.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Create migrations for all {$count} gyms?", true)) {
            $this->info('Migration cancelled.');

            return self::SUCCESS;
        }

        $created = $migrationService->createMigrationsForAllGyms();

        $this->info("Created {$created} migration(s).");
        $this->newLine();

        $pending = $migrationService->getPendingMigrations();
        $this->info("{$pending} migration(s) pending.");

        if ($this->confirm('Start all pending migrations?', true)) {
            $this->info('Starting migrations...');

            foreach ($gymsNeedingMigration as $gymId) {
                $result = $migrationService->startMigration($gymId);

                if ($result['success']) {
                    $this->line("  - Gym {$gymId}: Started");
                } else {
                    $this->error("  - Gym {$gymId}: Failed - {$result['error']}");
                }
            }

            $this->newLine();
            $this->info('All migrations have been started.');
            $this->info('Tenants will need to complete the Embedded Signup flow.');
        }

        return self::SUCCESS;
    }
}
