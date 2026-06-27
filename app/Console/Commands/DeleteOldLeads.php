<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadConversion;
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
        $totalDeleted = 0;

        $this->info("Starting cleanup of leads older than {$cutoffDate->toDateTimeString()}...");

        // Process in chunks to manage memory usage
        Lead::query()
            ->whereIn('status', [LeadStatus::CONVERTED, LeadStatus::LOST])
            ->whereDate('updated_at', '<', $cutoffDate)
            ->chunkById(500, function ($leads) use (&$totalDeleted) {
                $leadIds = $leads->pluck('id')->toArray();

                if (blank($leadIds)) {
                    return;
                }

                $count = count($leadIds);

                // Delete related FollowUps
                FollowUp::query()->whereIn('lead_id', $leadIds)->delete();

                // Delete related LeadConversions
                LeadConversion::query()->whereIn('lead_id', $leadIds)->delete();

                // Delete Leads
                Lead::query()->whereIn('id', $leadIds)->delete();

                $totalDeleted += $count;
                $this->info("Deleted batch of {$count} leads and related data.");
            });

        $this->info("Cleanup complete. Total leads deleted: {$totalDeleted}.");
    }
}
