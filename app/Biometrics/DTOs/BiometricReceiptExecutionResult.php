<?php

declare(strict_types=1);

namespace App\Biometrics\DTOs;

use App\Models\BiometricEventReceipt;

readonly class BiometricReceiptExecutionResult
{
    public function __construct(
        public bool $isDuplicate,
        public BiometricEventReceipt $receipt,
        public ?AdmsEventResult $eventResult = null,
    ) {}

    public static function executed(BiometricEventReceipt $receipt, AdmsEventResult $eventResult): self
    {
        return new self(
            isDuplicate: false,
            receipt: $receipt,
            eventResult: $eventResult,
        );
    }

    public static function duplicate(BiometricEventReceipt $receipt): self
    {
        return new self(
            isDuplicate: true,
            receipt: $receipt,
        );
    }
}
