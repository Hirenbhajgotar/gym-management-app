<?php

namespace App\Biometrics\Services;

use App\Models\Attendances;
use App\Models\GymMember;
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
     * Queue an offline check-in event for later sync
     */
    public function queueCheckIn(array $data): bool
    {
        try {
            $queueFile = $this->getQueueFilePath();

            // Check queue size limit
            if ($this->getQueueCount() >= $this->maxQueueSize) {
                Log::warning('Biometric offline queue full, dropping event', $data);

                return false;
            }

            $event = [
                'type' => 'check_in',
                'data' => $data,
                'captured_at' => now()->toISOString(),
                'queued_at' => now()->toISOString(),
            ];

            // Append to queue file (JSON lines format)
            File::append($queueFile, json_encode($event) . "\n");

            Log::info('Biometric check-in queued for offline sync', [
                'member_id' => $data['gym_member_id'] ?? 'unknown',
                'captured_at' => $event['captured_at'],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to queue offline check-in', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return false;
        }
    }

    /**
     * Get all queued events from the queue file
     */
    public function getQueuedEvents(): array
    {
        $queueFile = $this->getQueueFilePath();

        if (! File::exists($queueFile)) {
            return [];
        }

        $events = [];
        $handle = fopen($queueFile, 'r');

        if ($handle === false) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $event = json_decode($line, true);
            if ($event) {
                $events[] = $event;
            }
        }

        fclose($handle);

        return $events;
    }

    /**
     * Get count of queued events
     */
    public function getQueueCount(): int
    {
        $queueFile = $this->getQueueFilePath();

        if (! File::exists($queueFile)) {
            return 0;
        }

        $count = 0;
        $handle = fopen($queueFile, 'r');

        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            if (trim($line)) {
                $count++;
            }
        }

        fclose($handle);

        return $count;
    }

    /**
     * Clear all queued events (after successful sync)
     */
    public function clearQueue(): bool
    {
        try {
            $queueFile = $this->getQueueFilePath();

            if (File::exists($queueFile)) {
                File::delete($queueFile);
            }

            $this->ensureQueueDirectoryExists();

            Log::info('Biometric offline queue cleared');

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear offline queue', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Remove specific events from queue (after successful sync)
     */
    public function removeEvents(array $eventIndices): bool
    {
        try {
            $events = $this->getQueuedEvents();
            $remainingEvents = array_values(array_diff_key($events, array_flip($eventIndices)));

            // Rewrite queue file with remaining events
            $queueFile = $this->getQueueFilePath();
            File::put($queueFile, '');

            foreach ($remainingEvents as $event) {
                File::append($queueFile, json_encode($event) . "\n");
            }

            Log::info('Biometric offline queue updated after sync', [
                'removed' => count($eventIndices),
                'remaining' => count($remainingEvents),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to remove events from queue', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Process and sync queued events to the server
     */
    public function syncQueue(string $apiUrl, string $apiToken): array
    {
        $events = $this->getQueuedEvents();

        if (empty($events)) {
            return [
                'success' => true,
                'synced' => 0,
                'failed' => 0,
                'message' => 'Queue is empty',
            ];
        }

        $syncedCount = 0;
        $failedCount = 0;
        $failedIndices = [];
        $successfulIndices = [];

        // Process in batches
        $batches = array_chunk($events, $this->batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $response = $this->sendBatchToServer($batch, $apiUrl, $apiToken);

            if ($response['success']) {
                $syncedCount += count($batch);

                // Track successful batch indices
                foreach ($batch as $event) {
                    $eventIndex = array_search($event, $events, true);
                    if ($eventIndex !== false) {
                        $successfulIndices[] = $eventIndex;
                    }
                }

                // Mark as synced in attendance records
                $this->markAttendanceAsSynced($batch);
            } else {
                $failedCount += count($batch);

                // Track failed batch indices
                foreach ($batch as $event) {
                    $eventIndex = array_search($event, $events, true);
                    if ($eventIndex !== false) {
                        $failedIndices[] = $eventIndex;
                    }
                }
            }
        }

        // Remove successfully synced events from queue
        if ($failedCount === 0) {
            // All events synced successfully - clear entire queue
            $this->clearQueue();
        } else {
            // Some events failed - remove only successful ones, keep failures for retry
            $this->removeEvents($successfulIndices);
        }

        return [
            'success' => $failedCount === 0,
            'synced' => $syncedCount,
            'failed' => $failedCount,
            'remaining' => $this->getQueueCount(),
        ];
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
        } catch (\Exception $e) {
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

            // Find attendance by captured_at timestamp and member
            $capturedAt = $event['data']['captured_at'] ?? null;
            $memberId = $event['data']['gym_member_id'] ?? null;

            if ($capturedAt && $memberId) {
                Attendances::where('attendanceable_id', $memberId)
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
     * Get queue file path with date-based rotation
     */
    protected function getQueueFilePath(): string
    {
        $date = now()->format('Y-m-d');

        return $this->queuePath . '/queue-' . $date . '.jsonl';
    }

    /**
     * Clean up old queue files (older than specified days)
     */
    public function cleanupOldQueues(int $daysToKeep = 7): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        $deletedCount = 0;

        $files = File::files($this->queuePath);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Extract date from filename (queue-YYYY-MM-DD.jsonl)
            if (preg_match('/queue-(\d{4}-\d{2}-\d{2})\.jsonl/', $filename, $matches)) {
                $fileDate = \Carbon\Carbon::parse($matches[1]);

                if ($fileDate->lt($cutoffDate)) {
                    File::delete($file->getPathname());
                    $deletedCount++;
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
            'queue_size_bytes' => $this->getQueueSizeBytes(),
            'should_use_offline' => $this->shouldUseOfflineMode(),
            'last_sync' => cache()->get('biometric_last_sync'),
        ];
    }

    /**
     * Get total queue size in bytes
     */
    protected function getQueueSizeBytes(): int
    {
        $queueFile = $this->getQueueFilePath();

        if (! File::exists($queueFile)) {
            return 0;
        }

        return File::size($queueFile);
    }
}
