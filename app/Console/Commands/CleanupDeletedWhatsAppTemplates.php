<?php

namespace App\Console\Commands;

use App\Models\WhatsAppTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupDeletedWhatsAppTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-whatsapp-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete WhatsApp templates that have been soft-deleted for more than 15 days';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\WhatsAppTemplateService $service)
    {
        // 15 days threshold
        $threshold = now()->subDays(15);
        $templates = WhatsAppTemplate::onlyTrashed()->where('deleted_at', '<=', $threshold)->get();

        if ($templates->isEmpty()) {
            $this->info('No deleted templates older than 15 days found.');

            return;
        }

        foreach ($templates as $template) {
            $this->info("Processing template {$template->id} ({$template->name}) deleted on {$template->deleted_at}");

            // Try to delete from Twilio/Meta if there is a twilio_template_sid
            if ($template->twilio_template_sid && ! $template->is_default) {
                $deleted = $service->deleteFromTwilio($template);
                if ($deleted) {
                    $this->info('Successfully deleted from Twilio');

                    // Force delete locally
                    $template->forceDelete();
                    Log::info("Force deleted WhatsAppTemplate {$template->id} locally");
                    $this->info("Permanently deleted template {$template->id} locally.");
                } else {
                    $this->error('Twilio deletion failed or skipped.');
                }
            }
        }

        $this->info('Cleanup complete.');
    }
}
