<?php

namespace App\Jobs;

use App\Models\Gym;
use App\Models\GymWhatsAppBalance;
use App\Models\GymSetting;
use App\Notifications\LowWhatsAppBalanceNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckWhatsAppBalanceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get all gyms with WhatsApp balances
        $balances = GymWhatsAppBalance::with('gym')->get();
        if ($balances->isEmpty()) {
            return;
        }

        // Get all relevant settings for all gyms at once
        $settings = GymSetting::whereIn('gym_id', $balances->pluck('gym_id'))
            ->whereIn('key', ['enable_low_balance_alerts', 'low_balance_threshold'])
            ->get()
            ->groupBy('gym_id');

        foreach ($balances as $balance) {
            $gymSettings = $settings->get($balance->gym_id);

            $alertEnabled = $gymSettings?->firstWhere('key', 'enable_low_balance_alerts')?->value ?? true;
            $threshold = (int) ($gymSettings?->firstWhere('key', 'low_balance_threshold')?->value ?? 15);

            $remainingMessages = $balance->remaining_messages;

            // Check if balance is low
            if ($remainingMessages <= $threshold) {
                if ($alertEnabled) {
                    // Check if we haven't already sent an alert for this low balance
                    if (!$balance->low_balance_alert_sent) {
                        $this->sendLowBalanceAlert($balance->gym, $remainingMessages);

                        // Mark alert as sent
                        $balance->low_balance_alert_sent = true;
                        $balance->save();

                        Log::info("Low WhatsApp balance alert sent for gym {$balance->gym->name} with {$remainingMessages} messages remaining");
                    }
                }
            } else {
                // Reset alert flag if balance is no longer low
                if ($balance->low_balance_alert_sent) {
                    $balance->low_balance_alert_sent = false;
                    $balance->save();
                }
            }
        }
    }

    /**
     * Send low balance alert to gym owner
     */
    private function sendLowBalanceAlert(Gym $gym, int $remainingMessages): void
    {
        // Get gym owner (assuming gym has an owner relationship)
        if ($gym->owner) {
            $gym->owner->notify(new LowWhatsAppBalanceNotification($gym, $remainingMessages));
        }

        // Also notify all gym staff members with appropriate permissions
        // Note: This assumes gym has staffMembers relationship - adjust as needed
        // foreach ($gym->staffMembers as $staff) {
        //     if ($staff->hasPermission('manage_settings')) {
        //         $staff->notify(new LowWhatsAppBalanceNotification($gym, $remainingMessages));
        //     }
        // }
    }
}
