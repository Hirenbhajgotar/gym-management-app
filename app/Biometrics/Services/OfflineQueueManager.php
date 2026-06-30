<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Models\Attendances;
use App\Models\GymMember;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OfflineQueueManager
{
    protected string $queuePath;

    protected int $maxQueueSize = 1000;

    protected int $batchSize = 50;

    public function __construct()
    {
        $this->queuePath = storage_path('app/biometric-offline-queue');
        $this->ensureQueueDirectoryExists();
    }

    protected function ensureQueueDirectoryExists(): void
    {
        if (! File::exists($this->queuePath)) {
            File::makeDirectory($this->queuePath, 0755, true);
        }
    }

    /**
     * Get all queue file paths sorted by date (oldest first)
     *
     * @return array<string, string> Key is file path, value is date
     */
    protected function getAllQueueFilePaths(): array
    {
        $pattern = $this->queuePath . '/queue-*.jsonl';
        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        $queueFiles = [];

        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/queue-(\d{4}-\d{2}-\d{2})\.jsonl/', $filename, $matches)) {
                $date = $matches[1];
                $queueFiles[$file] = $date;
            }
        }

        // Sort by date (oldest first)
        asort($queueFiles);

        return $queueFiles;
    }

    /**
     * Get today's queue file path
     */
    protected function getQueueFilePath(): string
    {
        $date = now()->format('Y-m-d');

        return $this->queuePath . '/queue-' . $date . '.jsonl';
    }

    /**
     * Acquire exclusive lock on a specific queue file
     *
     * @return resource|false
     */
    protected function acquireLock(string $queueFile)
    {
        $handle = fopen($queueFile, 'c+');

        if ($handle === false) {
            return false;
        }

        if (! flock($handle, LOCK_EX)) {
            fclose($handle);

            return false;
        }

        return $handle;
    }

    /**
     * Release lock on queue file
     *
     * @param  resource|false  $handle
     */
    protected function releaseLock($handle): void
    {
        if ($handle !== false && $handle !== null) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Queue an offline check-in event for later sync
     */
    public function queueCheckIn(array $data): bool
    {
        $handle = null;
        $queueFile = $this->getQueueFilePath();

        try {
            $handle = $this->acquireLock($queueFile);

            if ($handle === false) {
                Log::error('Failed to acquire lock for queue check-in', $data);

                return false;
            }

            // Check total queue size across all files while holding lock
            $totalQueueSize = $this->getTotalQueueCountWithLock($handle, $queueFile);
            if ($totalQueueSize >= $this->maxQueueSize) {
                Log::warning('Biometric offline queue full, dropping event', $data);

                return false;
            }

            $event = [
                'type' => 'check_in',
                'data' => $data,
                'captured_at' => now()->toISOString(),
                'queued_at' => now()->toISOString(),
            ];

            // Append to today's queue file while holding lock
            fseek($handle, 0, SEEK_END);
            fwrite($handle, json_encode($event) . "\n");
            fflush($handle);

            Log::info('Biometric check-in queued for offline sync', [
                'member_id' => $data['gym_member_id'] ?? 'unknown',
                'captured_at' => $event['captured_at'],
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to queue offline check-in', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return false;
        } finally {
            $this->releaseLock($handle);
        }
    }

    /**
     * Get total queue count across all queue files (must hold lock on current file)
     */
    protected function getTotalQueueCountWithLock($handle, string $currentFile): int
    {
        // Count current file
        $count = $this->getQueueCountFromHandle($handle);

        // Add count from other files
        $allFiles = $this->getAllQueueFilePaths();
        foreach ($allFiles as $file => $date) {
            if ($file !== $currentFile && File::exists($file)) {
                $count += $this->getQueueCountFromFile($file);
            }
        }

        return $count;
    }

    /**
     * Get count from a file (no lock)
     */
    protected function getQueueCountFromFile(string $file): int
    {
        if (! File::exists($file)) {
            return 0;
        }

        $count = 0;
        $handle = fopen($file, 'r');

        if ($handle === false) {
            return 0;
        }

        flock($handle, LOCK_SH);

        while (($line = fgets($handle)) !== false) {
            if (trim($line)) {
                $count++;
            }
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        return $count;
    }

    /**
     * Get all queued events from all queue files (oldest first)
     */
    public function getQueuedEvents(): array
    {
        $allFiles = $this->getAllQueueFilePaths();
        $events = [];

        foreach ($allFiles as $file => $date) {
            $fileEvents = $this->getEventsFromFile($file);
            $events = array_merge($events, $fileEvents);
        }

        return $events;
    }

    /**
     * Get events from a single file
     */
    protected function getEventsFromFile(string $file): array
    {
        if (! File::exists($file)) {
            return [];
        }

        $events = [];
        $handle = fopen($file, 'r');

        if ($handle === false) {
            return [];
        }

        flock($handle, LOCK_SH);

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (blank($line)) {
                continue;
            }

            $event = json_decode($line, true);
            if ($event) {
                $events[] = $event;
            }
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        return $events;
    }

    /**
     * Get total count of queued events across all files
     */
    public function getQueueCount(): int
    {
        $allFiles = $this->getAllQueueFilePaths();
        $total = 0;

        foreach ($allFiles as $file => $date) {
            $total += $this->getQueueCountFromFile($file);
        }

        return $total;
    }

    /**
     * Get count from open file handle (must hold lock)
     *
     * @param  resource  $handle
     */
    protected function getQueueCountFromHandle($handle): int
    {
        fseek($handle, 0, SEEK_SET);
        $count = 0;

        while (($line = fgets($handle)) !== false) {
            if (trim($line)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clear all queued events (after successful sync of all files)
     */
    public function clearQueue(): bool
    {
        try {
            $allFiles = $this->getAllQueueFilePaths();

            foreach ($allFiles as $file => $date) {
                if (File::exists($file)) {
                    File::delete($file);
                }
            }

            $this->ensureQueueDirectoryExists();

            Log::info('Biometric offline queue cleared (all files)');

            return true;
        } catch (Exception $e) {
            Log::error('Failed to clear offline queue', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Remove specific events from current queue file (for partial sync)
     */
    public function removeEvents(array $eventIndices): bool
    {
        $handle = null;
        $queueFile = $this->getQueueFilePath();

        try {
            $handle = $this->acquireLock($queueFile);

            if ($handle === false) {
                Log::error('Failed to acquire lock for remove events');

                return false;
            }

            // Read current events while holding lock
            $events = [];
            fseek($handle, 0, SEEK_SET);

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (blank($line)) {
                    continue;
                }

                $event = json_decode($line, true);
                if ($event) {
                    $events[] = $event;
                }
            }

            $remainingEvents = array_values(array_diff_key($events, array_flip($eventIndices)));

            // Write to temp file while holding lock
            $tempFile = $queueFile . '.tmp.' . uniqid('', true);
            $tempHandle = fopen($tempFile, 'w');

            if ($tempHandle === false) {
                Log::error('Failed to create temp file for queue rewrite');

                return false;
            }

            foreach ($remainingEvents as $event) {
                fwrite($tempHandle, json_encode($event) . "\n");
            }

            fflush($tempHandle);
            fclose($tempHandle);

            // Rename temp file to queue file (atomic on POSIX) while still holding lock
            // This prevents race condition where another process could write between release and rename
            if (! rename($tempFile, $queueFile)) {
                Log::error('Failed to atomic replace queue file');
                @unlink($tempFile);
                $this->releaseLock($handle);

                return false;
            }

            // Only release lock after atomic rename succeeds
            $this->releaseLock($handle);
            $handle = null;

            Log::info('Biometric offline queue updated after sync', [
                'removed' => count($eventIndices),
                'remaining' => count($remainingEvents),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to remove events from queue', ['error' => $e->getMessage()]);

            return false;
        } finally {
            $this->releaseLock($handle);
        }
    }

    /**
     * Process and sync queued events from all queue files (oldest first)
     */
    public function syncQueue(string $apiUrl, string $apiToken): array
    {
        $allFiles = $this->getAllQueueFilePaths();

        if (blank($allFiles)) {
            return [
                'success' => true,
                'synced' => 0,
                'failed' => 0,
                'message' => 'Queue is empty',
            ];
        }

        $totalSynced = 0;
        $totalFailed = 0;
        $filesProcessed = 0;
        $filesDeleted = 0;

        // Process each file in chronological order (oldest first)
        foreach ($allFiles as $file => $date) {
            $events = $this->getEventsFromFile($file);

            if (blank($events)) {
                // Empty file - can be safely deleted
                File::delete($file);
                $filesDeleted++;

                continue;
            }

            $fileSyncedCount = 0;
            $fileFailedCount = 0;
            $successfulIndices = [];

            // Process in batches
            $batches = array_chunk($events, $this->batchSize);

            foreach ($batches as $batch) {
                $response = $this->sendBatchToServer($batch, $apiUrl, $apiToken);

                if ($response['success']) {
                    $fileSyncedCount += count($batch);

                    // Track successful indices
                    foreach ($batch as $event) {
                        $eventIndex = array_search($event, $events, true);
                        if ($eventIndex !== false) {
                            $successfulIndices[] = $eventIndex;
                        }
                    }

                    $this->markAttendanceAsSynced($batch);
                } else {
                    $fileFailedCount += count($batch);
                }
            }

            // Handle file after processing
            if ($fileFailedCount === 0) {
                // All events synced - delete the file
                File::delete($file);
                $filesDeleted++;
                Log::info('Processed and deleted queue file', ['file' => $date, 'synced' => $fileSyncedCount]);
            } else {
                // Some failed - remove only successful events, keep failed
                $remainingEvents = array_values(array_diff_key($events, array_flip($successfulIndices)));

                if (blank($remainingEvents)) {
                    // All events synced - delete the file
                    File::delete($file);
                    $filesDeleted++;
                } else {
                    // Rewrite file with remaining events
                    $this->rewriteQueueFile($file, $remainingEvents);
                }
            }

            $totalSynced += $fileSyncedCount;
            $totalFailed += $fileFailedCount;
            $filesProcessed++;
        }

        // Update last sync time
        cache()->put('biometric_last_sync', now()->toISOString(), now()->addDay());

        return [
            'success' => $totalFailed === 0,
            'synced' => $totalSynced,
            'failed' => $totalFailed,
            'remaining' => $this->getQueueCount(),
            'files_processed' => $filesProcessed,
            'files_deleted' => $filesDeleted,
        ];
    }

    /**
     * Rewrite queue file with remaining events (internal use)
     */
    protected function rewriteQueueFile(string $file, array $events): bool
    {
        $handle = $this->acquireLock($file);

        if ($handle === false) {
            Log::error('Failed to acquire lock for rewrite', ['file' => $file]);

            return false;
        }

        try {
            $tempFile = $file . '.tmp.' . uniqid('', true);
            $tempHandle = fopen($tempFile, 'w');

            if ($tempHandle === false) {
                return false;
            }

            foreach ($events as $event) {
                fwrite($tempHandle, json_encode($event) . "\n");
            }

            fflush($tempHandle);
            fclose($tempHandle);

            // Rename temp file to queue file (atomic on POSIX) while still holding lock
            // This prevents race condition where another process could write between release and rename
            if (! rename($tempFile, $file)) {
                @unlink($tempFile);
                $this->releaseLock($handle);

                return false;
            }

            // Only release lock after atomic rename succeeds
            $this->releaseLock($handle);
            $handle = null;

            return true;
        } catch (Exception $e) {
            Log::error('Failed to rewrite queue file', ['error' => $e->getMessage()]);

            return false;
        } finally {
            $this->releaseLock($handle);
        }
    }

    /**
     * Send a batch of events to the sync API
     */
    protected function sendBatchToServer(array $batch, string $apiUrl, string $apiToken): array
    {
        try {
            $client = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]);

            $response = $client->post($apiUrl, [
                'events' => $batch,
                'device_id' => config('biometrics.device_id'),
                'synced_at' => now()->toISOString(),
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => $response->getStatusCode() === 200,
                'body' => $body,
            ];
        } catch (Exception $e) {
            Log::error('Failed to sync offline batch', [
                'error' => $e->getMessage(),
                'batch_size' => count($batch),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark attendance records as synced
     */
    protected function markAttendanceAsSynced(array $events): void
    {
        foreach ($events as $event) {
            if ($event['type'] !== 'check_in' || ! isset($event['data']['captured_at'])) {
                continue;
            }

            $capturedAt = $event['data']['captured_at'] ?? null;
            $memberId = $event['data']['gym_member_id'] ?? null;

            if ($capturedAt && $memberId) {
                Attendances::query()->where('attendanceable_id', $memberId)
                    ->where('attendanceable_type', GymMember::class)
                    ->where('check_in_time', $capturedAt)
                    ->whereNull('synced_at')
                    ->update([
                        'synced_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Clean up old queue files (only empty files or older than retention policy)
     */
    public function cleanupOldQueues(int $daysToKeep = 7): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        $deletedCount = 0;

        $allFiles = $this->getAllQueueFilePaths();

        foreach ($allFiles as $file => $date) {
            $fileDate = Date::parse($date);

            // Only delete files older than retention policy
            if ($fileDate->lt($cutoffDate)) {
                // Check if file is empty before deleting
                $count = $this->getQueueCountFromFile($file);

                if ($count === 0) {
                    File::delete($file);
                    $deletedCount++;
                } else {
                    Log::warning('Skipping cleanup of non-empty queue file', [
                        'file' => $date,
                        'events' => $count,
                    ]);
                }
            }
        }

        Log::info('Biometric offline queue cleanup completed', [
            'deleted_files' => $deletedCount,
            'days_kept' => $daysToKeep,
        ]);

        return $deletedCount;
    }

    /**
     * Check if device should operate in offline mode based on recent failures
     */
    public function shouldUseOfflineMode(int $failureThreshold = 3): bool
    {
        $recentFailures = cache()->get('biometric_api_failures', 0);

        return $recentFailures >= $failureThreshold;
    }

    /**
     * Record an API failure
     */
    public function recordApiFailure(): void
    {
        $currentFailures = cache()->get('biometric_api_failures', 0);
        cache()->put('biometric_api_failures', $currentFailures + 1, now()->addHours(1));
    }

    /**
     * Reset API failure count on successful connection
     */
    public function resetApiFailures(): void
    {
        cache()->forget('biometric_api_failures');
    }

    /**
     * Get queue status information
     */
    public function getQueueStatus(): array
    {
        return [
            'queue_count' => $this->getQueueCount(),
            'queue_size_bytes' => $this->getTotalQueueSizeBytes(),
            'should_use_offline' => $this->shouldUseOfflineMode(),
            'last_sync' => cache()->get('biometric_last_sync'),
            'files' => $this->getAllQueueFilePaths(),
        ];
    }

    /**
     * Get total queue size in bytes across all files
     */
    protected function getTotalQueueSizeBytes(): int
    {
        $total = 0;

        $allFiles = $this->getAllQueueFilePaths();
        foreach ($allFiles as $file => $date) {
            if (File::exists($file)) {
                $total += File::size($file);
            }
        }

        return $total;
    }
}
