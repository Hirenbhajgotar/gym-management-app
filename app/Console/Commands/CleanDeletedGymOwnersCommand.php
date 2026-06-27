<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DefaultRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanDeletedGymOwnersCommand extends Command
{
    protected $signature = 'owners:clean-deleted {--dry-run : Show what would be cleaned without actually doing it}';

    protected $description = 'Permanently delete gym owners that were soft-deleted more than 15 days ago, including all related data (gyms, Twilio subaccounts, phone numbers, subscriptions, payments, roles, permissions, notifications, leads, etc.)';

    public function handle(): int
    {
        $this->info('Starting cleanup of deleted gym owners...');

        $cutoffDate = now()->subDays(15);

        $deletedGymOwners = User::onlyTrashed()
            ->whereHas('gyms', function ($query) {
                $query->where('gym_user.role', DefaultRole::GYM_OWNER->value);
            })
            ->whereDate('deleted_at', '<=', $cutoffDate)
            ->with(['gyms', 'subscriptions', 'employeeDetails', 'address'])
            ->get();

        if ($deletedGymOwners->isEmpty()) {
            $this->info('No deleted gym owners found older than 15 days.');

            return self::SUCCESS;
        }

        $this->info("Found {$deletedGymOwners->count()} deleted gym owner(s) older than 15 days:");

        foreach ($deletedGymOwners as $owner) {
            $this->line("- User ID: {$owner->id}");
            $this->line("  Email: {$owner->email}");
            $this->line("  Name: {$owner->name}");
            $this->line("  Deleted: {$owner->deleted_at}");
            $this->line("  Gyms owned: {$owner->gyms->count()}");
            $this->line("  Subscriptions: {$owner->subscriptions->count()}");
            $this->line('');
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run mode - no owners were deleted.');

            return self::SUCCESS;
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($deletedGymOwners as $owner) {
            try {
                DB::beginTransaction();

                Log::info('Force deleting gym owner and all related data', [
                    'user_id' => $owner->id,
                    'user_email' => $owner->email,
                    'gyms_count' => $owner->gyms->count(),
                ]);

                // Force delete the owner - this will trigger UserObserver::deleting()
                // which handles cascade deletion of all related data
                $owner->forceDelete();

                DB::commit();

                $deletedCount++;
                $this->line("✓ Permanently deleted gym owner: {$owner->email}");

                Log::info('Successfully force deleted gym owner', [
                    'user_id' => $owner->id,
                    'user_email' => $owner->email,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();

                $errorMessage = "Failed to delete gym owner {$owner->email}: {$e->getMessage()}";
                $this->error($errorMessage);
                $errors[] = $errorMessage;

                Log::error('Failed to force delete gym owner', [
                    'user_id' => $owner->id,
                    'user_email' => $owner->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Successfully deleted {$deletedCount} gym owner(s).");

        if (filled($errors)) {
            $this->error('Some deletions failed:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
