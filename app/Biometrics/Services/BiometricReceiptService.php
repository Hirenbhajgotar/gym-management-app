<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\DTOs\AdmsEventResult;
use App\Biometrics\DTOs\BiometricReceiptExecutionResult;
use App\Enums\BiometricReceiptStatusEnum;
use App\Models\BiometricDevice;
use App\Models\BiometricEventReceipt;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BiometricReceiptService
{
    /**
     * Compute a deterministic SHA-256 fingerprint for an event using canonical UTC ISO-8601 formatting.
     *
     * SECURITY NOTE: This fingerprint provides at-rest deduplication across retried requests.
     * It is NOT a cryptographic proof of origin or replay prevention against an adversary on
     * an untrusted network. Transport-level authentication and replay prevention rely on
     * ADMS_REQUIRE_COMM_KEY=true (which requires a provisioned device comm key on all push requests).
     */
    public function generateFingerprint(int $deviceId, string $identifier, string|DateTimeInterface $timestamp, string|int $extra = ''): string
    {
        $normalizedTime = $timestamp instanceof DateTimeInterface
            ? Date::instance($timestamp)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z')
            : Date::parse($timestamp)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $cleanIdentifier = trim($identifier);
        $cleanExtra = trim((string) $extra);

        return hash('sha256', "{$deviceId}:{$cleanIdentifier}:{$normalizedTime}:{$cleanExtra}");
    }

    /**
     * Atomically claim and execute an event with isolated duplicate checking.
     *
     * Invariant:
     * - A receipt is claimed atomically before executing domain side effects.
     * - If the receipt was already terminal, the side effect is NOT executed.
     * - Exceptions from the domain side effect bubble up and are not misclassified as duplicate receipt races.
     *
     * @param  array<string, mixed>  $summary
     * @param  callable(): AdmsEventResult  $sideEffect
     *
     * @throws Throwable
     */
    public function executeIdempotent(
        BiometricDevice $device,
        string $fingerprint,
        array $summary,
        callable $sideEffect
    ): BiometricReceiptExecutionResult {
        try {
            return DB::transaction(function () use ($device, $fingerprint, $summary, $sideEffect): BiometricReceiptExecutionResult {
                /** @var BiometricEventReceipt|null $existing */
                $existing = BiometricEventReceipt::query()
                    ->where('device_id', $device->id)
                    ->where('event_fingerprint', $fingerprint)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status->isTerminal()) {
                    return BiometricReceiptExecutionResult::duplicate($existing);
                }

                $receipt = $existing ?? BiometricEventReceipt::query()->create([
                    'gym_id' => $device->gym_id,
                    'device_id' => $device->id,
                    'event_fingerprint' => $fingerprint,
                    'status' => BiometricReceiptStatusEnum::CLAIMED,
                    'payload_summary' => $summary,
                ]);

                if ($existing) {
                    $receipt->update([
                        'status' => BiometricReceiptStatusEnum::CLAIMED,
                        'payload_summary' => $summary,
                    ]);
                }

                $result = $sideEffect();

                $status = match (true) {
                    $result->action === 'ignored' => BiometricReceiptStatusEnum::IGNORED,
                    $result->granted => BiometricReceiptStatusEnum::PROCESSED,
                    default => BiometricReceiptStatusEnum::DENIED,
                };

                $receipt->update([
                    'status' => $status,
                    'attendance_id' => $result->attendance?->id,
                    'payload_summary' => array_merge($summary, [
                        'action' => $result->action,
                        'denial_reason' => $result->denialReason,
                    ]),
                ]);

                return BiometricReceiptExecutionResult::executed($receipt, $result);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Concurrent race condition: another worker inserted the receipt row
            /** @var BiometricEventReceipt|null $committed */
            $committed = BiometricEventReceipt::query()
                ->where('device_id', $device->id)
                ->where('event_fingerprint', $fingerprint)
                ->first();

            if ($committed && $committed->status->isTerminal()) {
                return BiometricReceiptExecutionResult::duplicate($committed);
            }

            Log::warning('BiometricReceiptService: Duplicate key race detected while claim was pending', [
                'device_id' => $device->id,
                'fingerprint' => $fingerprint,
            ]);

            throw $e;
        }
    }
}
