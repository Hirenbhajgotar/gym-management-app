<?php

namespace App\Console\Commands;

use App\Models\Gym;
use Illuminate\Console\Command;

/**
 * @deprecated Use real-time webhooks instead. Template sync is now handled via:
 *             - Meta: WhatsAppTemplateWebhookController (POST /api/webhooks/whatsapp/templates)
 *             - Twilio: TwilioTemplateWebhookController (POST /api/webhooks/twilio/templates)
 *             This polling command will be removed in a future release.
 */
class SyncWhatsAppTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-whats-app-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '[DEPRECATED] Syncs WhatsApp message templates from Twilio to the local database. Use webhooks instead.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->warn('WARNING: This command is deprecated. Template sync is now handled via real-time webhooks.');
        $this->warn('Use the webhook endpoints instead:');
        $this->warn('  - Meta: POST /api/webhooks/whatsapp/templates-status');
        $this->warn('  - Twilio: POST /api/webhooks/twilio/templates-status');
        $this->newLine();

        // Get all gyms with active and verified WhatsApp configurations
        Gym::whereHas('whatsAppAccount', function ($query) {
            $query->where('is_active', true)
                ->where('is_verified', true);
        })->chunkById(100, function ($gyms) {
            foreach ($gyms as $gym) {
                $this->info("Syncing templates for Gym ID: {$gym->id}, Name: {$gym->name}");

                $templateManager = new \App\Services\WhatsAppTemplateManager($gym->id);
                $result = $templateManager->syncTemplatesToDatabase();

                if ($result->success) {
                    $this->info("Synced {$result->synced} templates successfully!");
                } else {
                    $this->error("Failed to fetch templates for Gym ID: {$result->error}");
                }
            }
        });

        return 0;
    }
}
