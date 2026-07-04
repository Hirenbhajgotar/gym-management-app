<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Models\Gym;
use App\Models\Lead;
use App\Notifications\PendingLeadNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PendingLeadNotificationCommand extends Command
{
    protected $signature = 'leads:send-pending-reminders';

    protected $description = 'Send reminders for leads that have been pending for 2+ days with no follow-ups';

    private const CHUNK_SIZE = 100;

    public function handle(): int
    {
        $sent = 0;
        $skipped = 0;

        $this->info('Scanning for pending leads requiring reminders...');

        Lead::with('gym')
            ->whereIn('status', [
                LeadStatus::NEW,
                LeadStatus::CONTACTED,
            ])
            ->where('created_at', '<', now()->subDays(2))
            ->where(function (Builder $query) {
                $query
                    ->whereDoesntHave('followUps')
                    ->orWhere('last_follow_up_date', '<', now()->subDays(2))
                    ->orWhereNull('last_follow_up_date');
            })
            ->chunkById(self::CHUNK_SIZE, function ($leads) use (&$sent, &$skipped) {
                foreach ($leads as $lead) {
                    if ($this->notifyLeadOwner($lead)) {
                        $sent++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Done. Sent: {$sent}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    private function notifyLeadOwner(Lead $lead): bool
    {
        /** @var Gym|null $gym */
        $gym = $lead->gym;

        if ($gym === null) {
            $this->warn("Gym #{$lead->gym_id} not found for lead #{$lead->id}, skipping.");

            return false;
        }

        $owner = $lead->gym->owner();

        if ($owner === null) {
            $this->warn("No owner found for gym {$lead->gym->name}, skipping lead {$lead->name}");

            return false;
        }

        $owner->notify(new PendingLeadNotification($lead));
        $this->line("Sent reminder for lead: {$lead->name}");

        return true;
    }
}
