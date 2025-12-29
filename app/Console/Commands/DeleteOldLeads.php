<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Console\Command;

class DeleteOldLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:cleanup-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete converted and lost leads older than 2 months';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoffDate = now()->subMonths(2);

        $count = Lead::whereIn('status', [LeadStatus::CONVERTED, LeadStatus::LOST])
            ->where('updated_at', '<', $cutoffDate)
            ->delete();

        $this->info("Deleted {$count} old leads.");
    }
}
