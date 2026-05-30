# WhatsApp Broadcast Scaling & Performance Plan

This plan outlines the specific architectural and implementation steps to scale the WhatsApp broadcast system to handle **100,000+ messages** for a single tenant, and up to **300,000 concurrent messages** across 2,000 to 3,000 tenants, eliminating memory exhaustion, database bottlenecks, and network latency issues.

---

## Solutions Scope (Excluding Queue Driver Config)

1. **Memory Optimization via Database Cursors/Chunking**: Change `SendBroadcastJob` to query and process recipients incrementally instead of loading all 100,000 records into memory via `->get()`.
2. **Upfront Bulk Credit Deduction & Batch-End Refunds**: Change credit deduction from $O(N)$ sequential database updates inside queue loops to a single bulk deduction upfront ($O(1)$) and a single bulk refund operation ($O(1)$) for failed recipients on batch completion.
3. **Concurrent HTTP Request Pooling**: Modify `SendMessageChunkJob` to send the chunk's 50 API requests concurrently via Guzzle Async / `Http::pool()` rather than sequentially.
4. **Rate Limit Handling & Exponential Queue Backoff**: Implement backoff logic for HTTP 429 (Too Many Requests) errors instead of hard-failing immediately.
5. **Database Indexing**: Add migrations for high-throughput indexes on critical foreign keys and state columns.

---

## 1. Memory Optimization: Cursors/Chunking in `SendBroadcastJob`

### Current Bottleneck
In `SendBroadcastJob.php`, calling `$recipients->get()` on 100,000 recipients loads all records with their eager-loaded relationships into memory, causing PHP to run out of memory (OOM).

### Implementation Plan
- Replace `->get()` with `->chunkById()` or `->lazy()` to load recipients in small, manageable batches (e.g., 500 at a time).
- Process each memory-chunk, resolve template variables, and aggregate the `SendMessageChunkJob` payloads in memory before dispatching to the batch.
- Explicitly clear Eloquent's model query cache or flush variables using garbage collection where appropriate.

#### Code Pattern Example
```php
$chunkJobs = [];
WhatsAppBroadcastRecipient::where('whatsapp_broadcast_id', $this->broadcastId)
    ->where('status', 'pending')
    ->with(['member.gym', 'member.membershipPlan', 'member.trainer', 'member.latestPayment'])
    ->chunkById(500, function ($recipients) use ($broadcast, $template, $resolver, &$chunkJobs) {
        $resolvedRecipients = [];
        foreach ($recipients as $recipient) {
            $context = [
                'member' => $recipient->member,
                'custom' => $broadcast->message_variables ?? [],
            ];
            $variables = $resolver->resolve($template, $context);

            $resolvedRecipients[] = [
                'id' => $recipient->id,
                'phone_number' => $recipient->phone_number,
                'member_name' => $recipient->member_name,
                'resolved_variables' => $variables,
            ];
        }

        foreach (array_chunk($resolvedRecipients, self::CHUNK_SIZE) as $chunk) {
            $chunkJobs[] = new SendMessageChunkJob(
                broadcastId: $this->broadcastId,
                recipientChunk: $chunk,
                templateData: $this->getTemplateData($template),
                gymId: $this->gymId,
            );
        }
    });

// Dispatch the integrated batch
if (!empty($chunkJobs)) {
    $batch = Bus::batch($chunkJobs)->dispatch();
}
```

---

## 2. Upfront Bulk Credit Deduction & Refunds on Completion

### Current Bottleneck
Every single template message checks and decrements the credit row individually. For 100,000 messages, this triggers 100,000 SELECT and 100,000 UPDATE queries, locking the `whatsapp_message_credits` row sequentially and causing severe thread lockouts and deadlocks.

### Implementation Plan
1. **Upfront Bulk Deduction**:
   - In `BroadcastOrchestrator::createAndDispatch()`, perform a single atomic deduction of `target_count` credits from `WhatsAppMessageCredit`.
   - Wrap the check and deduction in a pessimistic row lock (`sharedLock()` or `lockForUpdate()`) to ensure absolute data consistency under concurrent requests.
2. **Remove Sequential Deduction**:
   - Disable or bypass individual `checkAndDeduct(1)` calls when sending messages inside the chunk job. Provide an option or flag (e.g., `$isBroadcast = true`) to skip sequential billing.
3. **Batch-End Refund Callback**:
   - Implement the `then()` and `catch()` callbacks on the `Bus::batch()` in `SendBroadcastJob` to calculate the final failed message count and issue a single bulk credit refund to the gym's balance.

#### Code Pattern Example
```php
// In BroadcastOrchestrator.php
DB::transaction(function () use ($gymId, $targetCount) {
    $credit = WhatsAppMessageCredit::where('gym_id', $gymId)->lockForUpdate()->first();
    if (!$credit || !$credit->canSendMessage($targetCount)) {
        throw new \Exception('Insufficient credits.');
    }
    $credit->deductCredits($targetCount); // Single bulk deduction
});

// In SendBroadcastJob.php (Batch Callback)
->finally(function (Batch $batch) use ($broadcastId, $gymId) {
    $broadcast = WhatsAppBroadcast::find($broadcastId);
    $failedCount = $broadcast->failed_count;

    if ($failedCount > 0) {
        // Refund failed messages in one bulk operation
        WhatsAppMessageCredit::where('gym_id', $gymId)->increment('remaining_credits', $failedCount);
        WhatsAppMessageCredit::where('gym_id', $gymId)->decrement('used_credits', $failedCount);
    }
})
```

---

## 3. Concurrent HTTP Request Pooling

### Current Bottleneck
In `SendMessageChunkJob.php`, messages are sent inside a synchronous, blocking loop (`foreach`). For a chunk of 50, this takes ~10 seconds. Across 300,000 messages, this generates 16.6 hours of sequential blocking time.

### Implementation Plan
- Leverage Guzzle's asynchronous promises or Laravel's concurrent HTTP client pooling (`Http::pool()`) to fire the 50 API requests concurrently.
- Re-map the concurrent responses back to recipient IDs to execute a single bulk database update.

#### Code Pattern Example (Laravel HTTP Pool)
```php
use Illuminate\Support\Facades\Http;

$responses = Http::pool(function ($pool) use ($provider, $recipientChunk) {
    $calls = [];
    foreach ($recipientChunk as $recipient) {
        $calls[] = $pool->async()->post($provider->getEndpoint(), $provider->getPayload($recipient));
    }
    return $calls;
});

// Process responses concurrently
foreach ($responses as $index => $response) {
    $recipientId = $recipientChunk[$index]['id'];
    if ($response->successful()) {
        $successIds[] = $recipientId;
    } else {
        $failedIds[] = $recipientId;
        $failureReasons[$recipientId] = $response->json('error.message') ?? 'API Error';
    }
}
```

---

## 4. Rate Limit Handling & Exponential Queue Backoff

### Current Bottleneck
When concurrent jobs hit Meta/Twilio rate limits (HTTP 429), the current code logs a hard failure, resulting in massive message loss.

### Implementation Plan
- Configure `SendMessageChunkJob` to be rate-limit aware.
- Catch HTTP 429 status codes or API-specific rate limit exceptions.
- Release the job back into the Redis queue with an exponential delay using `$this->release($delaySeconds)`.
- Use the queue's retry mechanics instead of failing the recipients immediately.

#### Code Pattern Example
```php
// In SendMessageChunkJob.php
public function handle(): void
{
    try {
        // Execute API calls
    } catch (RateLimitException $e) {
        // Log backoff attempt and release job back to Redis with custom decay (e.g. 15s, 30s, 60s)
        $attempts = $this->attempts();
        $delay = min(60, 15 * $attempts);
        
        Log::warning("Rate limit hit on broadcast chunk. Requeuing in {$delay}s.", [
            'broadcast_id' => $this->broadcastId,
            'attempt' => $attempts
        ]);
        
        $this->release($delay);
        return;
    }
}
```

---

## 5. Database Indexing

### Current Bottleneck
High-frequency JOINs, status queries, and updates on `whatsapp_broadcast_recipients` will cause query bottlenecks and table scans if proper indexes are not in place.

### Implementation Plan
Create a database migration to add performance-optimizing indexes:
1. Composite index on `[whatsapp_broadcast_id, status]` in `whatsapp_broadcast_recipients` to speed up chunk queries.
2. Index on `[gym_id]` in `whatsapp_message_credits` for fast balance checking and deduction locks.
3. Index on `[status]` in `whatsapp_broadcasts` for fast command lookups.

#### Migration Schema Definition
```php
Schema::table('whatsapp_broadcast_recipients', function (Blueprint $table) {
    $table->index(['whatsapp_broadcast_id', 'status'], 'idx_broadcast_status');
});

Schema::table('whatsapp_message_credits', function (Blueprint $table) {
    $table->index('gym_id', 'idx_credits_gym_id');
});

Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
    $table->index('status', 'idx_broadcast_status');
});
```

---

## 6. Execution Waves & Critical Path

```
Wave 1 (Database & Structural Foundation):
├── Task 1: Create Indexing Migration [quick]
└── Task 2: Refactor Credit Deduction to Bulk Upfront + Refund [deep]

Wave 2 (Job and Queue Refactoring):
├── Task 3: Refactor SendBroadcastJob to use ChunkById/lazy() [deep]
└── Task 4: Refactor SendMessageChunkJob with HTTP Pooling & Async Requests [deep]

Wave 3 (Failure Resilience):
└── Task 5: Integrate Exponential Backoff on 429 for SendMessageChunkJob [quick]

Wave 4 (Verification & Verification Testing):
└── Task 6: Run benchmark suite and verify zero N+1 database queries [unspecified-high]
```
