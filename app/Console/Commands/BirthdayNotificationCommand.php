<?php

namespace App\Console\Commands;

use App\Enums\DefaultRole;
use App\Models\GymMember;
use Illuminate\Console\Command;

class BirthdayNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:send-birthday-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends birthday notifications to members on their birthday.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        GymMember::whereMonth('dob', now()->month)
            ->whereDay('dob', now()->day)
            ->with(['gym', 'gym.users' => function ($query) {
                $query->wherePivot('role', DefaultRole::GYM_OWNER->value);
            }])
            ->chunkById(200, function ($members) {
                foreach ($members as $member) {
                    $owner = $member->gym->users->first();
                    $member->notify(
                        new \App\Notifications\BirthdayNotification(
                            $member,
                            $owner?->email,
                            $owner?->phone
                        ),
                    );
                }
            });

        $this->info('Birthday notifications queued.');

        return self::SUCCESS;
    }
}
