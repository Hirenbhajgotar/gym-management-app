<?php

namespace App\Console\Commands;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanInvalidExportsCommand extends Command
{
    protected $signature = 'exports:clean-invalid {--dry-run : Show what would be cleaned without actually doing it}';

    protected $description = 'Clean up invalid export records that are causing download errors';

    public function handle(): int
    {
        $this->info('Starting export cleanup...');

        $invalidExports = $this->findInvalidExports();

        if ($invalidExports->isEmpty()) {
            $this->info('No invalid exports found.');

            return self::SUCCESS;
        }

        $this->info("Found {$invalidExports->count()} invalid export(s):");

        foreach ($invalidExports as $export) {
            $this->line("- Export ID: {$export->id}");
            $this->line("  File: {$export->file_name}");
            $this->line("  Disk: {$export->file_disk}");
            $this->line("  Created: {$export->created_at}");
            $this->line("  Completed: {$export->completed_at}");
            $this->line('');
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run mode - no exports were deleted.');

            return self::SUCCESS;
        }

        if ($this->confirm('Do you want to delete these invalid exports?')) {
            $deletedCount = 0;

            foreach ($invalidExports as $export) {
                try {
                    $export->delete();
                    $deletedCount++;
                    $this->line("Deleted export ID: {$export->id}");
                } catch (\Exception $e) {
                    $this->error("Failed to delete export ID {$export->id}: {$e->getMessage()}");
                }
            }

            $this->info("Successfully deleted {$deletedCount} invalid export(s).");
        } else {
            $this->info('Cleanup cancelled.');
        }

        return self::SUCCESS;
    }

    protected function findInvalidExports()
    {
        return Export::query()->where(function (Builder $query) {
            // Find exports with missing file information
            $query->whereNull('file_name')
                ->orWhereNull('file_disk')
                ->orWhere('file_name', '')
                ->orWhere('file_disk', '');
        })->orWhere(function (Builder $query) {
            // Find exports where the file doesn't exist on disk
            $query->whereNotNull('file_name')
                ->whereNotNull('file_disk')
                ->where('file_name', '!=', '')
                ->where('file_disk', '!=', '');
        })->get()->filter(function ($export) {
            // Additional check for file existence
            if ($export->file_name && $export->file_disk) {
                try {
                    $disk = Storage::disk($export->file_disk);

                    return ! $disk->exists($export->file_name);
                } catch (\Exception $e) {
                    Log::error('Error checking export file existence', [
                        'export_id' => $export->id,
                        'exception' => $e->getMessage(),
                    ]);

                    return true; // Consider it invalid if we can't check
                }
            }

            return true;
        });
    }
}
