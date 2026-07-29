<?php

declare(strict_types=1);

namespace App\Console\Commands\WhatsApp;

use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Import WhatsApp templates from Meta/Twilio for tenant gyms.
 *
 * This command dispatches queue jobs to import templates from a tenant's
 * existing Meta/Twilio WhatsApp account. Templates are imported as DRAFT
 * status to allow manual review before submission for approval.
 *
 * Usage:
 *   php artisan whatsapp:import-templates              # Import for all gyms with WhatsApp accounts
 *   php artisan whatsapp:import-templates --gym=1       # Import for specific gym
 *   php artisan whatsapp:import-templates --sync       # Run synchronously (not recommended for production)
 */
class ImportTenantWhatsAppTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:import-templates
                            {--gym= : Import templates for specific gym ID}
                            {--sync : Run synchronously instead of dispatching jobs (not recommended)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import WhatsApp templates from Meta/Twilio for tenant gyms';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $gymId = $this->option('gym');
        $sync = $this->option('sync');

        if ($sync) {
            $this->warn('Running in synchronous mode. For production, use async job dispatch instead.');
            $this->newLine();
        }

        try {
            if ($gymId !== null) {
                return $this->importForSingleGym((int) $gymId, $sync);
            }

            return $this->importForAllGyms($sync);
        } catch (Throwable $e) {
            // A manual run for a specific gym should fail loudly.
            if ($gymId !== null) {
                $this->error("Import failed: {$e->getMessage()}");

                return self::FAILURE;
            }

            // The scheduled (all-gyms) run is best-effort: log the failure so it
            // is still observable, but do not crash the scheduler with exit code 1.
            $this->error("Scheduled template import encountered an error: {$e->getMessage()}");
            report($e);

            return self::SUCCESS;
        }
    }

    /**
     * Import templates for a single gym.
     */
    private function importForSingleGym(int $gymId, bool $sync): int
    {
        $gym = Gym::query()->find($gymId);

        if (! $gym) {
            $this->error("Gym with ID {$gymId} not found.");

            return self::FAILURE;
        }

        $account = $this->getWhatsAppAccount($gymId);

        if (! $account) {
            $this->error("Gym {$gym->name} does not have a WhatsApp account configured.");

            return self::FAILURE;
        }

        if ($account->isWabaRestricted() || $account->isWabaBlocked() || $account->isWabaSuspended()) {
            Log::warning('ImportWhatsAppTemplatesJob: WhatsApp account is in a non-functional state', [
                'gym_id' => $gymId,
                'status' => $account->status->value ?? 'unknown',
                'verification_status' => $account->verification_status->value ?? 'unknown',
            ]);
            $this->error("Gym {$gym->name} does not have an active or verified WhatsApp account.");

            return self::FAILURE;
        }

        $providerLabel = $account->provider_type?->getLabel() ?? 'Unknown';
        $this->info("Importing templates for Gym: {$gym->name}");
        $this->info("  Provider: {$providerLabel}");

        if ($sync) {
            return $this->runSynchronously($gymId);
        }

        try {
            dispatch(new ImportWhatsAppTemplatesJob($gymId));
        } catch (Throwable $e) {
            $this->error("Failed to dispatch import job for Gym {$gym->name}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('  Job dispatched to queue: whatsapp-templates');

        return self::SUCCESS;
    }

    /**
     * Import templates for all gyms with WhatsApp accounts.
     */
    private function importForAllGyms(bool $sync): int
    {
        $gyms = $this->getGymsWithWhatsAppAccounts();

        if ($gyms->isEmpty()) {
            $this->warn('No gyms with WhatsApp accounts found.');

            return self::SUCCESS;
        }

        $this->info("Found {$gyms->count()} gym(s) with WhatsApp accounts.");
        $this->newLine();

        $dispatchedCount = 0;
        $skippedCount = 0;

        foreach ($gyms as $gym) {
            $account = $gym->whatsAppAccount;

            if (! $account) {
                $skippedCount++;

                continue;
            }

            if ($account->isWabaRestricted() || $account->isWabaBlocked() || $account->isWabaSuspended()) {
                Log::warning('ImportWhatsAppTemplatesJob: WhatsApp account is in a non-functional state', [
                    'gym_id' => $gym->id,
                    'status' => $account->status->value ?? 'unknown',
                    'verification_status' => $account->verification_status->value ?? 'unknown',
                ]);
                $this->warn("Gym {$gym->name} does not have an active or verified WhatsApp account.");

                $skippedCount++;

                continue;
            }

            $this->line("  [{$gym->id}] {$gym->name} ({$account->provider_type?->getLabel()})");

            if ($sync) {
                $result = $this->runSynchronously($gym->id);
                if ($result === self::FAILURE) {
                    $skippedCount++;

                    continue;
                }
            } else {
                try {
                    dispatch(new ImportWhatsAppTemplatesJob($gym->id));
                    $this->line('    → Job dispatched to queue');
                } catch (Throwable $e) {
                    $this->warn("  Failed to dispatch import job for Gym {$gym->name}: {$e->getMessage()}");
                    $skippedCount++;

                    continue;
                }
            }

            $dispatchedCount++;
        }

        $this->newLine();
        $this->info('Import summary:');
        $this->info("  - Jobs dispatched/synced: {$dispatchedCount}");

        if ($skippedCount > 0) {
            $this->warn("  - Skipped: {$skippedCount}");
        }

        if (! $sync) {
            $this->info('  - Queue: whatsapp-templates');
            $this->info('  - Run `php artisan queue:work` to process jobs');
        }

        return self::SUCCESS;
    }

    /**
     * Run the import synchronously for a gym.
     */
    private function runSynchronously(int $gymId): int
    {
        $this->info('Running synchronously...');

        try {
            $job = new ImportWhatsAppTemplatesJob($gymId);
            $job->handle();

            $this->info('Import completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Import failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Get all gyms with active WhatsApp accounts.
     *
     * @return Collection<int, Gym>
     */
    private function getGymsWithWhatsAppAccounts(): Collection
    {
        return Gym::query()->whereHas('whatsAppAccount')->with('whatsAppAccount')->orderBy('id')->get();
    }

    /**
     * Get WhatsApp account for a gym.
     */
    private function getWhatsAppAccount(int $gymId): ?WhatsAppAccount
    {
        return WhatsAppAccount::query()->where('gym_id', $gymId)->first();
    }
}
