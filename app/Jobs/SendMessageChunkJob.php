<?php

namespace App\Jobs;

use App\Providers\MetaWhatsAppProvider;
use App\Providers\TwilioWhatsAppProvider;
use App\Providers\WhatsAppProviderFactory;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendMessageChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 90;

    public int $tries = 3;

    /**
     * Maximum number of seconds to wait between retries (exponential backoff).
     */
    public int $maxBackoff = 60;

    /**
     * @param  int  $broadcastId  The broadcast this chunk belongs to
     * @param  array<int, array{id: int, phone_number: string, member_name: string, resolved_variables: array<string, string>}>  $recipientChunk  Pre-resolved recipient data (no DB lookups needed)
     * @param  array{provider_template_id: string, language: string, components: array, media_urls: array}  $templateData  Template data for provider API calls
     * @param  int  $gymId  Gym ID for provider resolution
     * @param  bool  $skipCreditDeduction  When true, per-message credit deduction is skipped (credits deducted upfront by BroadcastOrchestrator)
     */
    public function __construct(
        public int $broadcastId,
        public array $recipientChunk,
        public array $templateData,
        public int $gymId,
        public bool $skipCreditDeduction = false,
    ) {
        $this->onQueue(config('queue.broadcast_queue', 'broadcast'));
    }

    /**
     * Backoff calculation with exponential delay.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [2, 4, 8, 16, 30, $this->maxBackoff];
    }

    /**
     * Execute the job — send messages for this chunk of recipients.
     */
    public function handle(): void
    {
        $provider = $this->resolveProvider();

        if ($this->skipCreditDeduction) {
            $provider->credits()->setSkipDeduction(true);
        }

        $successIds = [];
        $failedIds = [];
        $failureReasons = [];
        $messageSids = [];

        $responses = $this->sendConcurrent($provider);

        foreach ($this->recipientChunk as $i => $recipient) {
            $response = $responses[$i] ?? null;

            if ($response !== null && ($response['success'] ?? false)) {
                $successIds[] = $recipient['id'];
                $messageSids[$recipient['id']] = $response['message_id'] ?? null;
            } else {
                $error = is_array($response) ? ($response['error'] ?? 'Unknown error') : 'Request failed';
                if ($this->isRateLimited($error)) {
                    throw new RateLimitExceededException(
                        "Rate limited: {$error}",
                        $this->getRetryAfter($error)
                    );
                }
                $failedIds[] = $recipient['id'];
                $failureReasons[$recipient['id']] = $error;
            }
        }

        $this->bulkUpdateRecipientStatuses($successIds, $failedIds, $failureReasons, $messageSids);

        $this->updateBroadcastCounts(count($successIds), count($failedIds));
    }

    /**
     * Send messages concurrently using Http::pool().
     * Falls back to sequential sending via the provider.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sendConcurrent(MetaWhatsAppProvider|TwilioWhatsAppProvider $provider): array
    {
        $isMeta = $provider instanceof MetaWhatsAppProvider;
        $responses = [];

        if ($isMeta) {
            $responses = $this->sendViaHttpPool($provider);
        } else {
            $responses = $this->sendSequential($provider);
        }

        return $responses;
    }

    /**
     * Send messages using Laravel Http::pool() for concurrent API calls.
     * Only applicable to Meta provider (direct HTTP API).
     *
     * @return array<int, array<string, mixed>>
     */
    private function sendViaHttpPool(MetaWhatsAppProvider $provider): array
    {
        $context = $provider->getContext();
        $wabaId = $context->getWabaId();
        $accessToken = $context->getAccessToken();
        $baseUrl = $context->getApiBaseUrl();

        if (! $wabaId || ! $accessToken) {
            return $this->sendSequential($provider);
        }

        $responses = Http::pool(function (Pool $pool) use ($baseUrl, $wabaId, $accessToken): array {
            $calls = [];

            foreach ($this->recipientChunk as $recipient) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient['phone_number'],
                    'type' => 'template',
                    'template' => [
                        'name' => $this->templateData['provider_template_id'],
                        'language' => [
                            'code' => $this->templateData['language'] ?? 'en',
                        ],
                    ],
                ];

                $variables = $recipient['resolved_variables'];
                if (! empty($variables)) {
                    $payload['template']['components'] = [
                        [
                            'type' => 'body',
                            'parameters' => array_map(
                                fn (string $value): array => ['type' => 'text', 'text' => $value],
                                array_values($variables)
                            ),
                        ],
                    ];
                }

                $calls[] = $pool->withToken($accessToken)
                    ->baseUrl($baseUrl)
                    ->acceptJson()
                    ->asJson()
                    ->post("/{$wabaId}/messages", $payload);
            }

            return $calls;
        });

        $results = [];
        foreach ($responses as $i => $response) {
            if ($response->successful()) {
                $body = $response->json();
                $results[$i] = [
                    'success' => true,
                    'message_id' => $body['messages'][0]['id'] ?? null,
                    'status' => 'sent',
                ];
            } else {
                $body = $response->json();
                $errorData = $body['error'] ?? [];
                $error = $errorData['message'] ?? $response->reason() ?: 'HTTP error';

                $results[$i] = [
                    'success' => false,
                    'error' => $error,
                    'status_code' => $response->status(),
                ];

                Log::warning('Meta API request failed in broadcast pool', [
                    'broadcast_id' => $this->broadcastId,
                    'index' => $i,
                    'phone' => substr($this->recipientChunk[$i]['phone_number'] ?? '', 0, 6) . '***',
                    'status' => $response->status(),
                    'error' => $error,
                ]);
            }
        }

        return $results;
    }

    /**
     * Send messages sequentially via the provider.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sendSequential(MetaWhatsAppProvider|TwilioWhatsAppProvider $provider): array
    {
        $results = [];

        foreach ($this->recipientChunk as $i => $recipient) {
            try {
                $result = $provider->sendTemplateMessage(
                    $recipient['phone_number'],
                    $this->templateData['provider_template_id'],
                    $recipient['resolved_variables'],
                );

                $results[$i] = [
                    'success' => $result['success'],
                    'message_id' => $result['message_id'] ?? null,
                    'error' => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
                ];
            } catch (\Throwable $e) {
                $results[$i] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Determine if the given error indicates a rate limit (429).
     */
    private function isRateLimited(string $response): bool
    {
        $needle = strtolower($response);

        return str_contains($needle, 'rate') || str_contains($needle, 'too many')
            || str_contains($needle, '429') || str_contains($needle, 'over_limit')
            || str_contains($needle, 'retry');

        // return ($response['status_code'] ?? 0) === 429
        //     || str_contains($response['error'] ?? '', 'rate_limit_exceeded');
    }

    /**
     * Extract retry-after seconds from error message, default 5.
     */
    private function getRetryAfter(string $error): int
    {
        if (preg_match('/(\d+)\s*second/', $error, $m)) {
            return (int) $m[1];
        }

        return 5;
    }

    /**
     * Resolve the WhatsApp provider for this job.
     * Override in tests to inject a mock provider.
     */
    protected function resolveProvider(): MetaWhatsAppProvider|TwilioWhatsAppProvider
    {
        return WhatsAppProviderFactory::create($this->gymId);
    }

    /**
     * Update recipient statuses using a single bulk UPDATE query.
     *
     * @param  int[]  $successIds
     * @param  int[]  $failedIds
     * @param  array<int, string>  $failureReasons
     * @param  array<int, string|null>  $messageSids
     */
    private function bulkUpdateRecipientStatuses(
        array $successIds,
        array $failedIds,
        array $failureReasons,
        array $messageSids,
    ): void {
        if (empty($successIds) && empty($failedIds)) {
            return;
        }

        $allIds = array_map('intval', array_merge($successIds, $failedIds));
        $now = now()->toDateTimeString();

        $whenStatus = '';
        $whenReason = '';
        $whenSid = '';
        $whenSentAt = '';

        $statusBinding = [];
        $reasonBinding = [];
        $sidBinding = [];
        $sentAtBinding = [];

        foreach ($allIds as $id) {
            $isSuccess = in_array($id, $successIds, true);

            $whenStatus .= " WHEN {$id} THEN ?";
            $statusBinding[] = $isSuccess ? 'sent' : 'failed';

            $whenReason .= " WHEN {$id} THEN ?";
            $reasonBinding[] = $failureReasons[$id] ?? null;

            $whenSid .= " WHEN {$id} THEN ?";
            $sidBinding[] = ($messageSids[$id] ?? null) ?: null;

            $whenSentAt .= " WHEN {$id} THEN ?";
            $sentAtBinding[] = $isSuccess ? $now : null;
        }

        $idPlaceholders = implode(',', array_fill(0, count($allIds), '?'));

        $bindings = array_merge($statusBinding, $reasonBinding, $sidBinding, $sentAtBinding, $allIds);

        DB::statement("
            UPDATE whatsapp_broadcast_recipients
            SET
                status = CASE id{$whenStatus} END,
                failure_reason = CASE id{$whenReason} END,
                twilio_message_sid = CASE id{$whenSid} END,
                sent_at = CASE id{$whenSentAt} END
            WHERE id IN ({$idPlaceholders})
        ", $bindings);
    }

    /**
     * Increment the broadcast's sent and failed counts atomically.
     */
    private function updateBroadcastCounts(int $sentCount, int $failedCount): void
    {
        if ($sentCount > 0) {
            DB::table('whatsapp_broadcasts')
                ->where('id', $this->broadcastId)
                ->update([
                    'sent_count' => DB::raw('sent_count + ' . $sentCount),
                    // 'credits_used' => DB::raw('credits_used + ' . $sentCount),
                ]);
        }

        if ($failedCount > 0) {
            DB::table('whatsapp_broadcasts')
                ->where('id', $this->broadcastId)
                ->increment('failed_count', $failedCount);
        }
    }
}
