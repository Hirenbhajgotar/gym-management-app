<?php

declare(strict_types=1);

namespace App\Console\Commands\WhatsApp;

use App\Services\WhatsAppTemplateService;
use Illuminate\Console\Command;

/**
 * @deprecated
 * No longer in use
 */
class ImportWhatsAppTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:import-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import WhatsApp templates from Twilio main account with gym_id=null';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppTemplateService $service)
    {
        $this->info('Starting WhatsApp template import from Twilio main account...');

        $result = $service->syncTemplatesFromTwilio();

        if ($result['success']) {
            $this->info('Successfully synced ' . ($result['synced_count'] ?? 0) . ' templates.');

            if (isset($result['error_count']) && $result['error_count'] > 0) {
                $this->warn("{$result['error_count']} templates failed to sync or were skipped.");
                foreach ($result['errors'] as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }
        } else {
            $this->error('Template sync failed: ' . ($result['error'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
