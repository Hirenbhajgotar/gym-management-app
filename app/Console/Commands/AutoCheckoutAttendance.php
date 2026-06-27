<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AttendanceService;
use Illuminate\Console\Command;

class AutoCheckoutAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:auto-checkout 
                            {--gym-id= : Specific gym ID to auto checkout (optional)}
                            {--hours=12 : Hours threshold for auto checkout (default: 12)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto check-out gym members and trainers who forgot to check out';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $gymId = $this->option('gym-id');
        $hoursThreshold = (int) $this->option('hours');

        $this->info('Starting auto check-out process...');
        $this->info("Hours threshold: {$hoursThreshold}");

        try {
            if ($gymId) {
                // Auto check-out for specific gym
                $this->info("Processing gym ID: {$gymId}");
                $count = AttendanceService::autoCheckOut($gymId, $hoursThreshold);
                $this->info("Auto checked out {$count} attendances for gym {$gymId}");
            } else {
                // Auto check-out for all gyms
                $this->info('Processing all gyms...');
                $results = AttendanceService::autoCheckOutAllGyms($hoursThreshold);

                $totalCount = 0;
                foreach ($results as $gymId => $count) {
                    if ($count > 0) {
                        $this->info("Gym {$gymId}: {$count} attendances auto checked out");
                        $totalCount += $count;
                    }
                }

                if ($totalCount === 0) {
                    $this->info('No attendances required auto check-out');
                } else {
                    $this->info("Total auto checked out: {$totalCount} attendances across " . count($results) . ' gyms');
                }
            }

            $this->info('Auto check-out process completed successfully!');
        } catch (\Exception $e) {
            $this->error('Error during auto check-out: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }
}
