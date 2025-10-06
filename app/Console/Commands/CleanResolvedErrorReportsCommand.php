<?php

namespace App\Console\Commands;

use App\Jobs\CleanResolvedErrorReportsJob;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanResolvedErrorReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'errors:clean-resolved';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up resolved error reports older than 15 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of resolved error reports...');

        try {
            // Dispatch the job
            CleanResolvedErrorReportsJob::dispatch();

            $this->info('The job will process resolved error reports older than 15 days.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to dispatch cleanup job: ' . $e->getMessage());
            Log::error('Failed to dispatch CleanResolvedErrorReportsJob', [
                'exception' => $e,
            ]);

            return self::FAILURE;
        }
    }
}
