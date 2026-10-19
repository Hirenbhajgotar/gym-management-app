<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GymMember;
use App\Notifications\BirthdayNotification;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
    public function handle(): int
    {
        GymMember::query()->notArchived()->birthdayToday()
            ->with(['gym'])
            ->chunkById(200, function ($members) {
                foreach ($members as $member) {
                    $cacheKey = BirthdayNotification::birthdayCacheKey($member->id);

                    if (Cache::has($cacheKey)) {
                        continue;
                    }

                    try {
                        $member->notify(
                            new BirthdayNotification($member),
                        );

                        Cache::put($cacheKey, true, now()->endOfDay());
                    } catch (Exception $e) {
                        Log::error('Failed to send birthday notification', [
                            'member_id' => $member->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info('Birthday notifications queued.');

        return self::SUCCESS;
    }
}
