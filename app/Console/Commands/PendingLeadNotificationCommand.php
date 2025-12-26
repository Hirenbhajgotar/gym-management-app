<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Notifications\PendingLeadNotification;
use Illuminate\Console\Command;

class PendingLeadNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:send-pending-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for leads that have been pending for 2+ days with no follow-ups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get leads that are pending for 2+ days with no recent follow-ups
        $pendingLeads = Lead::whereIn('status', [
            LeadStatus::NEW,
            LeadStatus::CONTACTED,
        ])
            ->where('created_at', '<', now()->subDays(2))
            ->where(function ($query) {
                $query
                    ->whereDoesntHave('followUps')
                    ->orWhere('last_follow_up_date', '<', now()->subDays(2))
                    ->orWhereNull('last_follow_up_date');
            })
            ->get();

        $this->info(
            "Found {$pendingLeads->count()} pending leads requiring reminders.",
        );

        foreach ($pendingLeads as $lead) {
            // Notify the gym owner
            $owner = $lead->gym->owner();

            if ($owner) {
                $owner->notify(new PendingLeadNotification($lead));
                $this->line("Sent reminder for lead: {$lead->name}");
            } else {
                $this->warn(
                    "No owner found for gym {$lead->gym->name}, skipping lead {$lead->name}",
                );
            }
        }

        $this->info('Pending lead reminders sent successfully.');
    }
}
