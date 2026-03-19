<?php

namespace App\Console\Commands;

use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncWhatsAppTemplateStatusesCommand extends Command
{
    protected $signature = 'whatsapp:sync-template-statuses';

    protected $description = 'Sync the approval status of all pending WhatsApp templates from Twilio/Meta';

    public function __construct(private WhatsAppTemplateService $templateService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $pendingTemplates = WhatsAppTemplate::where('status', 'pending')
            ->whereNotNull('twilio_template_sid')
            ->get();

        if ($pendingTemplates->isEmpty()) {
            $this->info('No pending templates to sync.');

            return self::SUCCESS;
        }

        $this->info("Syncing {$pendingTemplates->count()} pending templates...");

        $synced = 0;
        $errors = 0;

        foreach ($pendingTemplates as $template) {
            try {
                $oldStatus = $template->status->value;
                $this->templateService->syncTemplateStatus($template);
                $template->refresh();

                if ($oldStatus !== $template->status->value) {
                    $this->line("  ✓ {$template->display_name}: {$oldStatus} → {$template->status->value}");
                } else {
                    $this->line("  · {$template->display_name}: still {$template->status->value}");
                }

                $synced++;
            } catch (\Exception $e) {
                $this->error("  ✗ {$template->display_name}: {$e->getMessage()}");
                Log::error('Template sync failed in command', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Done. Synced: {$synced}, Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
