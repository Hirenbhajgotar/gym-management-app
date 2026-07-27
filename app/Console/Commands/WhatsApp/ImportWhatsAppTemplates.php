<?php

declare(strict_types=1);

namespace App\Console\Commands\WhatsApp;

use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use Illuminate\Console\Command;

/**
 * @deprecated Use `whatsapp:import-templates` (ImportTenantWhatsAppTemplates) instead.
 *             This command imports templates from the Twilio main account for gym_id=null.
 *             The new command supports both Meta and Twilio, and scopes to individual gyms.
 */
class ImportWhatsAppTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:import-templates-legacy {--gym= : Import for specific gym ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '[DEPRECATED] Legacy import from Twilio main account. Use whatsapp:import-templates instead.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('⚠️  This command is deprecated. Use `php artisan whatsapp:import-templates` instead.');
        $this->warn('   The new command supports both Meta and Twilio providers with per-gym scoping.');
        $this->newLine();

        $gymId = $this->option('gym');

        if ($gymId === null) {
            $this->error('Please specify a gym ID using --gym option.');
            $this->newLine();
            $this->info('Example: php artisan whatsapp:import-templates-legacy --gym=1');
            $this->info('         php artisan whatsapp:import-templates --gym=1  (recommended)');

            return self::FAILURE;
        }

        $gym = Gym::query()->find($gymId);

        if (! $gym) {
            $this->error("Gym with ID {$gymId} not found.");

            return self::FAILURE;
        }

        $this->info("Importing templates for Gym ID: {$gymId}...");

        dispatch(new ImportWhatsAppTemplatesJob((int) $gymId));

        $this->info('Job dispatched to queue: whatsapp-templates');
        $this->info('Run `php artisan queue:work` to process.');

        return self::SUCCESS;
    }
}
