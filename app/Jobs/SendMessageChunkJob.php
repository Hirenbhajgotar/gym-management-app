<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Exceptions\BroadcastConnectionException;
use App\Exceptions\RateLimitExceededException;
use App\Helpers\WhatsAppFailureClassifier;
use App\Helpers\WhatsAppSanitizer;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcastRecipient;
use App\Providers\MetaWhatsAppProvider;
use App\Providers\TwilioWhatsAppProvider;
use App\Providers\WhatsAppProviderFactory;
use App\Services\Meta\MetaTemplateService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMessageChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public const BACKOFF_SECONDS = [2, 4];

    public const CONNECTION_BACKOFF_SECONDS = [10, 30];

    public const STATUS_MISSING_RESPONSE = 0;

    public const STATUS_UNKNOWN_ERROR = -1;

    public const MIN_VARIABLE_MASK_LENGTH = 3;

    /**
     * @param  int  $broadcastId  The broadcast this chunk belongs to
     * @param  array<int, array{id: int, phone_number: string, member_name: string, resolved_variables: array<array-key, string>}>  $recipientChunk  Pre-resolved recipient data (no DB lookups needed)
     * @param  array{provider_template_id: string, language: string, components: array<mixed>, media_urls: array<mixed>}  $templateData  Template data for provider API calls
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
        // Re-index array keys to be sequential (0, 1, 2...) because Http::pool() maps pool results to sequential indices
        $this->recipientChunk = array_values($recipientChunk);
        $this->onQueue(config()->string('queue.broadcast_queue', 'broadcast'));
    }

    /**
     * Get the backoff timings for the job.
     *
     * @return array<int, int>|int
     */
    public function backoff(?Throwable $exception = null): array|int
    {
        if ($exception !== null) {
            $curr = $exception;
            while ($curr !== null) {
                if ($curr instanceof RateLimitExceededException) {
                    return $curr->getRetryAfter();
                }

                if ($curr instanceof ConnectionException || WhatsAppFailureClassifier::isConnectionException($curr)) {
                    return self::CONNECTION_BACKOFF_SECONDS;
                }

                $curr = $curr->getPrevious();
            }
        }

        // For tries = 3, only 2 retry attempts occur, matching the first 2 indices.
        return self::BACKOFF_SECONDS;
    }

    /**
     * @return array<int, RateLimitedWithRedis>
     */
    public function middleware(): array
    {
        return [new RateLimitedWithRedis('whatsapp-outbound-limit')];
    }

    /**
     * Execute the job — send messages for this chunk of recipients.
     */
    public function handle(): void
    {
        $broadcast = DB::table('whatsapp_broadcasts')->where('id', $this->broadcastId)->first(['id', 'gym_id']);
        if (! $broadcast || ! is_numeric($broadcast->gym_id) || (int) $broadcast->gym_id !== $this->gymId) {
            Log::warning('SendMessageChunkJob aborted: broadcast missing or gym_id mismatch', [
                'broadcast_id' => $this->broadcastId,
                'job_gym_id' => $this->gymId,
                'broadcast_gym_id' => $broadcast->gym_id ?? null,
            ]);

            return;
        }

        if ($this->attempts() > 1) {
            $sentIds = DB::table('whatsapp_broadcast_recipients')
                ->where('whatsapp_broadcast_id', $this->broadcastId)
                ->whereIn('id', array_column($this->recipientChunk, 'id'))
                ->whereIn('status', [WhatsAppBroadcastRecipientStatusEnum::SENT, WhatsAppBroadcastRecipientStatusEnum::FAILED])
                ->pluck('id')
                ->flip();

            $this->recipientChunk = array_values(array_filter(
                $this->recipientChunk,
                fn (array $r): bool => ! isset($sentIds[$r['id']])
            ));
        }

        // TOCTOU guard: re-verify opt-in at send time. A member may have
        // opted out, been archived, or been deleted after the broadcast was enqueued, so
        // re-check their current status/consent before dispatching any message.
        $chunkIds = array_column($this->recipientChunk, 'id');
        $recipientMembers = WhatsAppBroadcastRecipient::query()
            ->with('member:id,is_archived,allow_whatsapp_marketing')
            ->whereIn('id', $chunkIds)
            ->get(['id', 'member_id']);

        $optedOutIds = [];
        foreach ($recipientMembers as $recipientRow) {
            /** @var GymMember|null $member */
            $member = $recipientRow->member;

            if ($member === null) {
                $optedOutIds[] = $recipientRow->id;
            } elseif (! $member->canReceiveWhatsappNotifications()) {
                $optedOutIds[] = $recipientRow->id;
            }
        }

        if ($optedOutIds !== []) {
            $reasons = [];
            foreach ($optedOutIds as $id) {
                $recipientRow = $recipientMembers->firstWhere('id', $id);
                /** @var GymMember|null $member */
                $member = $recipientRow?->member;
                if ($member === null) {
                    $reasons[$id] = SendBroadcastJob::SKIPPED_MEMBER_NOT_FOUND;
                } else {
                    $reasons[$id] = SendBroadcastJob::SKIPPED_MEMBER_OPTED_OUT;
                }
            }

            $this->bulkUpdateRecipientStatuses([], $optedOutIds, $reasons, []);
            $this->updateBroadcastCounts(0, count($optedOutIds));

            $this->recipientChunk = array_values(array_filter(
                $this->recipientChunk,
                fn (array $r): bool => ! in_array($r['id'], $optedOutIds, true)
            ));
        }

        if ($this->recipientChunk === []) {
            return;
        }

        $provider = $this->resolveProvider();

        if ($this->skipCreditDeduction) {
            $provider->credits()->setSkipDeduction(true);
        }

        $responses = $this->sendConcurrent($provider);

        $classified = $this->classifyResponses($responses);

        $successIds = $classified['successIds'];
        $failedIds = $classified['failedIds'];
        $failureReasons = $classified['failureReasons'];
        $messageSids = $classified['messageSids'];
        $connectionFailedIds = $classified['connectionFailedIds'];
        $connectionErrors = $classified['connectionErrors'];
        $connectionExceptions = $classified['connectionExceptions'];
        $rateLimitError = $classified['rateLimitError'];
        $rateLimitException = $classified['rateLimitException'];

        if ($connectionFailedIds !== []) {
            if ($this->attempts() >= $this->tries) {
                // Last attempt: connection errors are terminal.
                // Acknowledged at-least-once delivery trade-off: client-side timeout may still have delivered,
                // as Graph API has no idempotency key support.
                foreach ($connectionFailedIds as $id) {
                    $failedIds[] = $id;
                    $failureReasons[$id] = $connectionErrors[$id] ?? 'Connection error';
                }
            }
        }

        if ($rateLimitError !== null) {
            if ($this->attempts() >= $this->tries) {
                // Last attempt: rate limit error is terminal.
                foreach ($this->recipientChunk as $recipient) {
                    if (! in_array($recipient['id'], $successIds, true) && ! in_array($recipient['id'], $failedIds, true)) {
                        $failedIds[] = $recipient['id'];
                        $failureReasons[$recipient['id']] = $rateLimitError;
                    }
                }
            }
        }

        $this->bulkUpdateRecipientStatuses($successIds, $failedIds, $failureReasons, $messageSids);
        $this->updateBroadcastCounts(count($successIds), count($failedIds));

        if ($rateLimitError !== null && $this->attempts() < $this->tries) {
            throw new RateLimitExceededException("Rate limited: {$rateLimitError}", $this->getRetryAfter($rateLimitError), $rateLimitException);
        }

        if ($connectionFailedIds !== [] && $this->attempts() < $this->tries) {
            $firstError = reset($connectionErrors);
            $firstException = reset($connectionExceptions) ?: null;
            throw new BroadcastConnectionException("Connection error: {$firstError}", 0, $firstException);
        }
    }

    /**
     * Send messages concurrently using Http::pool().
     * Falls back to sequential sending via the provider.
     *
     * @return array<int, array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null}>
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
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendMessageChunkJob failed permanently', [
            'broadcast_id' => $this->broadcastId,
            'exception' => $this->sanitizeError($exception->getMessage()),
        ]);

        /** @var array<int, int> $pendingIds */
        $pendingIds = DB::table('whatsapp_broadcast_recipients')
            ->where('whatsapp_broadcast_id', $this->broadcastId)
            ->whereIn('id', array_column($this->recipientChunk, 'id'))
            ->where('status', WhatsAppBroadcastRecipientStatusEnum::PENDING->value)
            ->pluck('id')
            ->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->all();

        if ($pendingIds !== []) {
            $reason = 'Broadcast chunk job failed: ' . $this->sanitizeError($exception->getMessage());

            $reasons = array_fill_keys($pendingIds, $reason);

            $this->bulkUpdateRecipientStatuses([], $pendingIds, $reasons, []);
            $this->updateBroadcastCounts(0, count($pendingIds));
        }
    }

    /**
     * Send messages using Laravel Http::pool() for concurrent API calls.
     * Only applicable to Meta provider (direct HTTP API).
     *
     * @return array<int, array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null}>
     */
    private function sendViaHttpPool(MetaWhatsAppProvider $provider): array
    {
        $context = $provider->getContext();
        $phoneNumberId = $context->getPhoneNumberId();
        $accessToken = $context->getAccessToken();
        $baseUrl = $context->getApiBaseUrl();

        if (! $phoneNumberId || ! $accessToken) {
            return $this->sendSequential($provider);
        }

        $responses = Http::pool(function (Pool $pool) use ($baseUrl, $phoneNumberId, $accessToken): array {
            $calls = [];

            foreach ($this->recipientChunk as $recipient) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient['phone_number'],
                    'type' => 'template',
                    'template' => [
                        'name' => $this->templateData['provider_template_id'],
                        'language' => [
                            'code' => $this->templateData['language'] ?? MetaTemplateService::defaultTemplateLanguage(),
                        ],
                    ],
                ];

                $variables = $recipient['resolved_variables'];
                if (filled($variables)) {
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
                    ->post("/{$phoneNumberId}/messages", $payload);
            }

            return $calls;
        });

        if (count($responses) !== count($this->recipientChunk)) {
            Log::warning('Meta HTTP pool response count mismatch', [
                'broadcast_id' => $this->broadcastId,
                'expected' => count($this->recipientChunk),
                'actual' => count($responses),
            ]);
        }

        /** @var array<int, array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null, exception?: Throwable|null}> $results */
        $results = [];
        foreach ($this->recipientChunk as $index => $recipient) {
            $response = $responses[$index] ?? null;

            if ($response === null) {
                $results[$index] = $this->failureResult('Provider response missing from batch', self::STATUS_UNKNOWN_ERROR);

                continue;
            }

            if ($response instanceof Throwable) {
                $isConnection = $response instanceof ConnectionException || WhatsAppFailureClassifier::isConnectionException($response);
                $sanitizedError = $this->sanitizeError($response->getMessage() ?: ($isConnection ? 'Connection error' : 'Request failed'));
                $statusCode = $isConnection ? self::STATUS_MISSING_RESPONSE : self::STATUS_UNKNOWN_ERROR;
                $results[$index] = $this->failureResult($sanitizedError, $statusCode, $response);

                $this->logSendFailure(
                    $isConnection ? 'Meta API connection error in broadcast pool' : 'Meta API request failed in broadcast pool',
                    $index,
                    null,
                    $sanitizedError
                );
            } else {
                /** @var Response $response */
                $is5xx = WhatsAppFailureClassifier::isTransientServerError($response->status());

                if ($is5xx) {
                    $body = $response->json();
                    $errorData = is_array($body) && isset($body['error']) && is_array($body['error']) ? $body['error'] : [];
                    $errorMsg = isset($errorData['message']) && is_string($errorData['message'])
                        ? $errorData['message']
                        : ($response->reason() ?: '5xx Server Error');
                    $sanitizedError = $this->sanitizeError($errorMsg);

                    $results[$index] = $this->failureResult($sanitizedError, $response->status());

                    $this->logSendFailure('Meta API server error in broadcast pool', $index, $response->status(), $sanitizedError);
                } elseif ($response->successful()) {
                    $body = $response->json();
                    $messages = is_array($body) && isset($body['messages']) && is_array($body['messages']) ? $body['messages'] : [];
                    $firstMessage = isset($messages[0]) && is_array($messages[0]) ? $messages[0] : [];
                    $messageId = is_string($firstMessage['id'] ?? null) ? $firstMessage['id'] : null;

                    $results[$index] = $this->successResult($messageId);
                } else {
                    $body = $response->json();
                    $errorData = is_array($body) && isset($body['error']) && is_array($body['error']) ? $body['error'] : [];
                    $errorMsg = isset($errorData['message']) && is_string($errorData['message'])
                        ? $errorData['message']
                        : ($response->reason() ?: 'HTTP error');

                    $retryAfterHeader = $response->header('Retry-After');
                    if (filled($retryAfterHeader) && WhatsAppFailureClassifier::isRateLimitStatusCode($response->status())) {
                        $errorMsg .= " (Retry-After: {$retryAfterHeader})";
                    }

                    $sanitizedError = $this->sanitizeError($errorMsg);

                    $results[$index] = $this->failureResult($sanitizedError, $response->status());

                    $this->logSendFailure('Meta API request failed in broadcast pool', $index, $response->status(), $sanitizedError);
                }
            }
        }

        return $results;
    }

    /**
     * Send messages sequentially via the provider.
     *
     * @return array<int, array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null, exception?: Throwable|null}>
     */
    private function sendSequential(MetaWhatsAppProvider|TwilioWhatsAppProvider $provider): array
    {
        /** @var array<int, array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null, exception?: Throwable|null}> $results */
        $results = [];

        foreach ($this->recipientChunk as $i => $recipient) {
            try {
                /** @var array<string, mixed> $result */
                $result = $provider->sendTemplateMessage(
                    $recipient['phone_number'],
                    $this->templateData['provider_template_id'],
                    $recipient['resolved_variables'],
                );

                if (($result['success'] ?? false) === true) {
                    $messageId = is_string($result['message_id'] ?? null) ? $result['message_id'] : null;

                    $results[$i] = $this->successResult($messageId);
                } else {
                    $rawError = is_string($result['error'] ?? null) ? $result['error'] : 'Unknown error';
                    $error = $this->sanitizeError($rawError);
                    $rawStatusCode = $result['status_code'] ?? null;
                    $statusCode = is_numeric($rawStatusCode) ? (int) $rawStatusCode : self::STATUS_UNKNOWN_ERROR;
                    $results[$i] = $this->failureResult($error, $statusCode);

                    $this->logSendFailure('Meta API/Twilio request failed in broadcast sequential', $i, $statusCode, $error);
                }
            } catch (Throwable $e) {
                $sanitizedError = $this->sanitizeError($e->getMessage());
                $isConnection = WhatsAppFailureClassifier::isConnectionException($e);
                $statusCode = $isConnection ? self::STATUS_MISSING_RESPONSE : self::STATUS_UNKNOWN_ERROR;
                $results[$i] = $this->failureResult($sanitizedError, $statusCode, $e);

                $this->logSendFailure(
                    $isConnection ? 'Meta API/Twilio connection error in broadcast sequential' : 'Meta API/Twilio request failed in broadcast sequential',
                    $i,
                    null,
                    $sanitizedError
                );
            }
        }

        return $results;
    }

    /**
     * Helper to log failures with consistent context.
     */
    private function logSendFailure(string $channel, int $index, ?int $status, string $error): void
    {
        $payload = [
            'broadcast_id' => $this->broadcastId,
            'index' => $index,
            'phone' => $this->maskedPhoneForIndex($index),
            'error' => $error,
        ];

        if ($status !== null && $status !== self::STATUS_MISSING_RESPONSE && $status !== self::STATUS_UNKNOWN_ERROR) {
            $payload['status'] = $status;
        }

        Log::warning($channel, $payload);
    }

    /**
     * Determine if the given error message indicates a rate limit (429).
     */
    private function isRateLimited(string $errorMessage): bool
    {
        return WhatsAppFailureClassifier::isRateLimitMessage($errorMessage);
    }

    /**
     * Get the masked phone number for a recipient index.
     */
    private function maskedPhoneForIndex(int $index): string
    {
        $phone = $this->recipientChunk[$index]['phone_number'] ?? '';

        return WhatsAppSanitizer::maskPhoneNumber($phone);
    }

    /**
     * Helper to build a success result array.
     *
     * @return array{success: true, message_id: string|null, status: 'sent', error: null, status_code: null, exception: null}
     */
    private function successResult(?string $messageId): array
    {
        return [
            'success' => true,
            'message_id' => $messageId,
            'status' => 'sent',
            'error' => null,
            'status_code' => null,
            'exception' => null,
        ];
    }

    /**
     * Helper to build a failure result array.
     *
     * @return array{success: false, message_id: null, status: 'failed', error: string, status_code: int, exception: Throwable|null}
     */
    private function failureResult(string $error, int $statusCode, ?Throwable $exception = null): array
    {
        return [
            'success' => false,
            'message_id' => null,
            'status' => 'failed',
            'error' => $error,
            'status_code' => $statusCode,
            'exception' => $exception,
        ];
    }

    /** @var array<int, string>|null */
    private ?array $memoizedCustomMasks = null;

    private function sanitizeError(string $error): string
    {
        if ($this->memoizedCustomMasks === null) {
            $customMasks = [];
            foreach ($this->recipientChunk as $recipient) {
                if (filled($recipient['member_name'])) {
                    $customMasks[] = $recipient['member_name'];
                }
                if (filled($recipient['resolved_variables'])) {
                    foreach ($recipient['resolved_variables'] as $val) {
                        if (strlen(trim($val)) >= self::MIN_VARIABLE_MASK_LENGTH) {
                            $customMasks[] = $val;
                        }
                    }
                }
            }
            foreach ($this->templateData['media_urls'] as $url) {
                if (is_string($url) && trim($url) !== '') {
                    $customMasks[] = $url;
                }
            }
            $this->memoizedCustomMasks = $customMasks;
        }

        return WhatsAppSanitizer::sanitizeError($error, $this->memoizedCustomMasks);
    }

    /**
     * Extract retry-after seconds from error message, clamped between 1 and 300 seconds.
     */
    private function getRetryAfter(string $error): int
    {
        $err = strtolower($error);
        $seconds = RateLimitExceededException::DEFAULT_RETRY_AFTER;

        if (preg_match('/retry\-after:\s*(\d+)/i', $err, $m)) {
            $seconds = (int) $m[1];
        } elseif (preg_match('/retry\s*after\s*(\d+)/i', $err, $m)) {
            $seconds = (int) $m[1];
        } elseif (preg_match('/(\d+)\s*min/', $err, $m)) {
            $seconds = (int) $m[1] * 60;
        } elseif (preg_match('/(\d+)\s*second/', $err, $m)) {
            $seconds = (int) $m[1];
        } elseif (is_numeric(trim($error))) {
            $seconds = (int) trim($error);
        }

        return max(1, min(300, $seconds));
    }

    /**
     * Classify API responses into successes, connection failures, and rate limits.
     *
     * @param  array<int, array<string, mixed>>  $responses
     * @return array{successIds: int[], failedIds: int[], failureReasons: array<int, string>, messageSids: array<int, string|null>, connectionFailedIds: int[], connectionErrors: array<int, string>, connectionExceptions: array<int, Throwable>, rateLimitError: string|null, rateLimitException: Throwable|null}
     */
    private function classifyResponses(array $responses): array
    {
        $successIds = [];
        $failedIds = [];
        $failureReasons = [];
        $messageSids = [];
        $connectionFailedIds = [];
        $connectionErrors = [];
        $connectionExceptions = [];
        $rateLimitError = null;
        $rateLimitException = null;

        foreach ($this->recipientChunk as $i => $recipient) {
            $response = $responses[$i] ?? null;

            if ($response !== null && ($response['success'] ?? false)) {
                $successIds[] = $recipient['id'];
                $messageId = $response['message_id'] ?? null;
                $messageSids[$recipient['id']] = is_string($messageId) ? $messageId : null;
            } else {
                $error = is_array($response) ? (is_scalar($response['error'] ?? null) ? (string) $response['error'] : 'Unknown error') : 'Request failed';
                $rawStatusCode = is_array($response) ? ($response['status_code'] ?? null) : null;
                $statusCode = is_numeric($rawStatusCode) ? (int) $rawStatusCode : self::STATUS_UNKNOWN_ERROR;
                $exception = is_array($response) && isset($response['exception']) && $response['exception'] instanceof Throwable
                    ? $response['exception']
                    : null;

                $is5xx = is_array($response) && WhatsAppFailureClassifier::isTransientServerError($statusCode);

                if ($statusCode === self::STATUS_MISSING_RESPONSE
                    || $is5xx
                    || WhatsAppFailureClassifier::isConnectionMessage($error)
                ) {
                    $connectionFailedIds[] = $recipient['id'];
                    $connectionErrors[$recipient['id']] = $error;
                    if ($exception !== null) {
                        $connectionExceptions[$recipient['id']] = $exception;
                    }
                } elseif ($this->isRateLimited($error) || WhatsAppFailureClassifier::isRateLimitStatusCode($statusCode)) {
                    if ($rateLimitError === null) {
                        $rateLimitError = $error;
                        $rateLimitException = $exception;
                    }
                    $failureReasons[$recipient['id']] = $error;
                } else {
                    $failedIds[] = $recipient['id'];
                    $failureReasons[$recipient['id']] = $error;
                }
            }
        }

        return [
            'successIds' => $successIds,
            'failedIds' => $failedIds,
            'failureReasons' => $failureReasons,
            'messageSids' => $messageSids,
            'connectionFailedIds' => $connectionFailedIds,
            'connectionErrors' => $connectionErrors,
            'connectionExceptions' => $connectionExceptions,
            'rateLimitError' => $rateLimitError,
            'rateLimitException' => $rateLimitException,
        ];
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
     * Update recipient statuses using grouped update queries.
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
        if (blank($successIds) && blank($failedIds)) {
            return;
        }

        $now = now();

        if (filled($successIds)) {
            $hasSids = false;
            foreach ($successIds as $id) {
                if (filled($messageSids[$id])) {
                    $hasSids = true;
                    break;
                }
            }

            if ($hasSids) {
                $cases = [];
                $params = [];
                foreach ($successIds as $id) {
                    $cases[] = 'WHEN id = ? THEN ?';
                    $params[] = $id;
                    $params[] = $messageSids[$id] ?? null;
                }
                $params[] = $now;
                $params[] = $now;

                $placeholders = implode(',', array_fill(0, count($successIds), '?'));
                foreach ($successIds as $id) {
                    $params[] = $id;
                }

                DB::statement(
                    'UPDATE whatsapp_broadcast_recipients SET '
                    . "status = '" . WhatsAppBroadcastRecipientStatusEnum::SENT->value . "', "
                    . 'twilio_message_sid = (CASE ' . implode(' ', $cases) . ' ELSE NULL END), '
                    . 'sent_at = ?, '
                    . 'updated_at = ? '
                    . "WHERE id IN ({$placeholders})",
                    $params
                );
            } else {
                DB::table('whatsapp_broadcast_recipients')
                    ->whereIn('id', $successIds)
                    ->update([
                        'status' => WhatsAppBroadcastRecipientStatusEnum::SENT->value,
                        'twilio_message_sid' => null,
                        'sent_at' => $now,
                        'updated_at' => $now,
                    ]);
            }
        }

        if (filled($failedIds)) {
            $hasReasons = false;
            foreach ($failedIds as $id) {
                if (filled($failureReasons[$id])) {
                    $hasReasons = true;
                    break;
                }
            }

            if ($hasReasons) {
                $cases = [];
                $params = [];
                foreach ($failedIds as $id) {
                    $cases[] = 'WHEN id = ? THEN ?';
                    $params[] = $id;
                    $params[] = $failureReasons[$id] ?? null;
                }
                $params[] = $now;

                $placeholders = implode(',', array_fill(0, count($failedIds), '?'));
                foreach ($failedIds as $id) {
                    $params[] = $id;
                }

                DB::statement(
                    'UPDATE whatsapp_broadcast_recipients SET '
                    . "status = '" . WhatsAppBroadcastRecipientStatusEnum::FAILED->value . "', "
                    . 'failure_reason = (CASE ' . implode(' ', $cases) . ' ELSE NULL END), '
                    . 'updated_at = ? '
                    . "WHERE id IN ({$placeholders})",
                    $params
                );
            } else {
                DB::table('whatsapp_broadcast_recipients')
                    ->whereIn('id', $failedIds)
                    ->update([
                        'status' => WhatsAppBroadcastRecipientStatusEnum::FAILED->value,
                        'failure_reason' => null,
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    /**
     * Increment the broadcast's sent and failed counts atomically.
     */
    private function updateBroadcastCounts(int $sentCount, int $failedCount): void
    {
        if ($sentCount > 0) {
            DB::table('whatsapp_broadcasts')
                ->where('id', $this->broadcastId)
                ->increment('sent_count', $sentCount);
        }

        if ($failedCount > 0) {
            DB::table('whatsapp_broadcasts')
                ->where('id', $this->broadcastId)
                ->increment('failed_count', $failedCount);
        }
    }
}
