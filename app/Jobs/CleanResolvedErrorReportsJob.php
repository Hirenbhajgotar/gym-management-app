<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Date;
use App\Models\ErrorReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanResolvedErrorReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout

    public int $tries = 3;

    public int $backoff = 60; // 1 minute backoff

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
        try {
            $cutoffDate = Date::now()->subDays(15);

            $deletedCount = ErrorReport::resolved()
                ->where('updated_at', '<', $cutoffDate)
                ->delete();

            Log::info('Cleaned resolved error reports', [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clean resolved error reports', [
                'exception' => $e,
            ]);

            throw $e; // Re-throw to trigger job retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CleanResolvedErrorReportsJob failed permanently', [
            'exception' => $exception,
        ]);
    }
}
