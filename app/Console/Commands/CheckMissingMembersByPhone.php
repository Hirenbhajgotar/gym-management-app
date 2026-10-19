<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GymMember;
use Illuminate\Console\Command;

class CheckMissingMembersByPhone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-missing-members-by-phone {file : Path to the file containing phone numbers} {--gym=1 : Gym ID to check against}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $rawContent = file_get_contents($filePath);
        if ($rawContent === false) {
            $this->error("Could not read file: {$filePath}");

            return self::FAILURE;
        }

        $excelNumbers = preg_split('/[\\s,]+/', $rawContent);
        if ($excelNumbers === false) {
            $excelNumbers = [];
        }

        $excelNumbers = collect($excelNumbers)
            ->map(fn ($n) => preg_replace('/\D/', '', $n))
            ->map(fn ($n) => substr($n, -10))
            ->unique()
            ->values();

        $gymId = (int) $this->option('gym');

        // Deliberately NOT notArchived(). This command answers "which phone
        // numbers from the sheet have no GymMember row at all", so the lookup is
        // a presence test over the gym's whole ledger. An archived member is
        // still on the books — that is what archiving means, unlike deletion —
        // so their number must stay in $importedNumbers and count as present.
        // Filtering archived rows out here would invert the report and tell the
        // operator to re-import members the gym already has.
        $importedNumbers = GymMember::query()->where('gym_id', $gymId)
            ->pluck('phone')
            ->map(fn ($n) => preg_replace('/\D/', '', $n))
            ->map(fn ($n) => substr($n, -10));

        $missingNumbers = $excelNumbers->diff($importedNumbers)->values();

        $this->info('Missing numbers count: ' . $missingNumbers->count());
        foreach ($missingNumbers as $number) {
            $this->line($number);
        }
    }
}
