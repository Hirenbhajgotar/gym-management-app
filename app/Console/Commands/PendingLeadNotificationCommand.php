<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DefaultRole;
use App\Enums\LeadStatus;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\PendingLeadNotification;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class PendingLeadNotificationCommand extends Command
{
    protected $signature = 'leads:send-pending-reminders';

    protected $description = 'Send reminders for leads that have been pending for 2+ days with no follow-ups';

    private const CHUNK_SIZE = 100;

    public function handle(): int
    {
        try {
            $sent = 0;
            $skipped = 0;

            $this->info('Scanning for pending leads requiring reminders...');

            Lead::with(['gym.users', 'gym.whatsAppAccount'])
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
                ->where(function (Builder $query) {
                    $query
                        ->whereNull('last_reminded_at')
                        ->orWhere('last_reminded_at', '<', now()->subDays(2));
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
        } catch (Exception $exception) {
            Log::error('Failed to send pending lead reminders: ', ['exception' => $exception]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function notifyLeadOwner(Lead $lead): bool
    {
        /** @var Gym|null $gym */
        $gym = $lead->gym;

        if ($gym === null) {
            Log::warning("Gym #{$lead->gym_id} not found for lead #{$lead->id}, skipping.");
            $this->warn("Gym #{$lead->gym_id} not found for lead #{$lead->id}, skipping.");

            return false;
        }

        $owner = $gym->users->first(
            fn (User $user) => $user->pivot->role === DefaultRole::GYM_OWNER->value
        );

        if ($owner === null) {
            Log::warning("No owner found for gym {$gym->name}, skipping lead {$lead->name}");
            $this->warn("No owner found for gym {$lead->gym->name}, skipping lead {$lead->name}");

            return false;
        }

        $owner->notify(new PendingLeadNotification($lead));
        $lead->update(['last_reminded_at' => now()]);

        $this->line("Sent reminder for lead: {$lead->name}");

        return true;
    }
}
