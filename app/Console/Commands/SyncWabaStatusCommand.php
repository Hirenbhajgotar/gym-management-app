<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Jobs\SyncWabaStatus;
use App\Models\WhatsAppAccount;
use Illuminate\Console\Command;

/**
 * Sync WhatsApp Business Account (WABA) status from Meta API.
 *
 * This command syncs the account_review_status and business_verification_status
 * from Meta Graph API for all WABA provider accounts.
 *
 * Usage:
 *   php artisan whatsapp:sync-waba-status
 *   php artisan whatsapp:sync-waba-status --dry-run
 *   php artisan whatsapp:sync-waba-status --force
 */
class SyncWabaStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:sync-waba-status
                            {--dry-run : Show which accounts would be synced without actually syncing}
                            {--force : Force sync even if account has been synced recently (within 1 hour)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync WhatsApp Business Account status from Meta API for all WABA accounts.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Starting WABA status sync...');
        $this->newLine();

        // Get all WABA provider accounts with required fields
        $query = WhatsAppAccount::query()
            ->where('provider_type', WhatsAppProviderTypeEnum::WABA)
            ->whereNotNull('waba_id')
            ->whereNotNull('meta_access_token');

        // Exclude recently synced accounts unless --force is used
        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('waba_status_synced_at')
                    ->orWhere('waba_status_synced_at', '<', now()->subHour());
            });
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('No WABA accounts found that need syncing.');
            $this->info('Use --force to sync recently updated accounts.');

            return Command::SUCCESS;
        }

        $this->info("Found {$accounts->count()} account(s) to sync.");

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run mode - no changes will be made:');
            $this->newLine();

            $this->table(
                ['ID', 'Gym ID', 'WABA ID', 'Current Status', 'Last Synced'],
                $accounts->map(function (WhatsAppAccount $account) {
                    return [
                        $account->id,
                        $account->gym_id,
                        $account->waba_id,
                        $account->status?->value ?? 'pending',
                        $account->waba_status_synced_at?->diffForHumans() ?? 'never',
                    ];
                })->toArray()
            );

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($accounts->count());
        $bar->start();

        foreach ($accounts as $account) {
            // Dispatch the sync job
            SyncWabaStatus::dispatch($account->id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Summary
        $this->info('Sync completed:');
        $this->info("  - Successfully dispatched: {$accounts->count()}");

        $this->newLine();
        $this->info('All sync jobs have been queued. They will be processed by the queue worker.');

        return Command::SUCCESS;
    }
}
