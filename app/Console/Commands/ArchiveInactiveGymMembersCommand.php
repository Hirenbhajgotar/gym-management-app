<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GymMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveInactiveGymMembersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:archive-inactive-gym-members {--years=2 : Number of years of inactivity before archiving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically archive gym members with no active membership plan or payment in over 2 years';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $years = (int) $this->option('years');
        $cutoffDate = now()->subYears($years)->startOfDay();

        $this->info("Searching for inactive members with membership_end prior to {$cutoffDate->toDateString()}...");

        $query = GymMember::query()
            ->notArchived()
            ->whereNotNull('membership_end')
            ->where('membership_end', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No inactive members found matching archive criteria.');

            return self::SUCCESS;
        }

        $archivedCount = 0;

        $query->chunkById(100, function ($members) use (&$archivedCount) {
            /** @var GymMember $member */
            foreach ($members as $member) {
                $member->archive();
                $archivedCount++;
            }
        });

        $this->info("Successfully archived {$archivedCount} inactive member(s).");

        Log::info('ArchiveInactiveGymMembersCommand executed', [
            'years_cutoff' => $years,
            'archived_count' => $archivedCount,
        ]);

        return self::SUCCESS;
    }
}
