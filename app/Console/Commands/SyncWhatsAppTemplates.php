<?php

namespace App\Console\Commands;

use App\Models\Gym;
use Illuminate\Console\Command;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all gyms with Twilio settings
        Gym::whereHas('notificationSetting', function ($query) {
            $query->whereNotNull('twilio_account_sid')
                ->whereNotNull('twilio_auth_token');
        })->chunkById(100, function ($gyms) {
            foreach ($gyms as $gym) {
                $this->info("Syncing templates for Gym ID: {$gym->id}, Name: {$gym->name}");

                $templateManager = new \App\Services\WhatsAppTemplateManager($gym->id);
                $result = $templateManager->syncTemplatesToDatabase();

                if ($result['success']) {
                    $this->info("Synced {$result['synced']} templates successfully!");
                } else {
                    $this->error("Failed to fetch templates for Gym ID: {$result['error']}");
                }
            }
        });


        return 0;
    }
}
