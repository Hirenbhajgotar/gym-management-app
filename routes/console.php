<?php

use App\Jobs\CheckWhatsAppBalanceJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup Schedule using spatie/laravel-backup
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:00');

// Error Schedule
Schedule::command('errors:clean-resolved')->dailyAt('01:00');

// Notification Schedule
Schedule::command('notification:send-subscription-expiry-alerts')->dailyAt('09:00');

// Run subscription renewal job daily at 2 AM
Schedule::job(new \App\Jobs\SubscriptionRenewalJob)->dailyAt('02:00');

// Clean invalid exports weekly
Schedule::command('exports:clean-invalid')->weekly();

// Send followup reminders daily
Schedule::command('followups:send-reminders')->daily();

// Send pending lead reminders daily
Schedule::command('leads:send-pending-reminders')->daily();

// Check WhatsApp balance daily
Schedule::job(new CheckWhatsAppBalanceJob)->daily();

// Sync WhatsApp templates to database daily at 3 AM
Schedule::command('app:sync-whats-app-templates')->dailyAt('03:00');

// Auto checkout gym members and trainers attendance daily at 11 PM and 1 AM
Schedule::command('attendance:auto-checkout')->twiceDailyAt(23, 1);

// Check for expired memberships daily
Schedule::command('gym:check-membership-expiry')->daily();

// Clean deleted gym owners daily
Schedule::command('owners:clean-deleted')->dailyAt('02:00');

// Clean old converted and lost leads older than 2 months
Schedule::command('leads:cleanup-old')->dailyAt('02:00');

// Send birthday notifications daily at 7 AM
Schedule::command('notification:send-birthday-notifications')->dailyAt('07:00');

// Process Scheduled Reports
Schedule::call(function () {
    $currentHeight = now()->format('H:i:00');

    // Find active reports scheduled for now
    $reports = \App\Models\ScheduledReport::where('is_active', true)
        ->where('scheduled_time', $currentHeight)
        ->get();

    foreach ($reports as $report) {
        $shouldRun = match ($report->frequency) {
            'daily' => true,
            'weekly' => now()->isMonday(),
            'monthly' => now()->day === 1,
            default => false,
        };

        if ($shouldRun) {
            \App\Jobs\ScheduledReportJob::dispatch($report);
        }
    }
})->everyTenMinutes();
