<?php

namespace App\Console\Commands;

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Notifications\FollowUpReminderNotification;
use Illuminate\Console\Command;

class SendFollowUpRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'followups:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send follow-up reminders to assignees for follow-ups scheduled today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get follow-ups scheduled for today and pending
        $followUps = FollowUp::with(['user', 'lead', 'gym.whatsAppAccount', 'gym.users'])
            ->whereDate('scheduled_date', today())
            ->where('status', FollowUpStatus::PENDING->value)
            ->get();

        foreach ($followUps as $followUp) {
            // Assuming FollowUp has a relationship 'assignees'
            $assignee = $followUp?->user;

            $assignee->notify(new FollowUpReminderNotification($followUp));
        }

        $this->info('Follow-up reminders sent successfully.');
    }
}
