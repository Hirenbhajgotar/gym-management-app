<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Log;

enum WhatsAppTemplateStatusEnum: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DISABLED = 'disabled';
    case UNSUBMITTED = 'unsubmitted';
    case IN_REVIEW = 'in_review';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::DISABLED => 'Disabled',
            self::UNSUBMITTED => 'Unsubmitted',
            self::IN_REVIEW => 'In Review',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::DISABLED => 'gray',
            self::UNSUBMITTED => 'gray',
            self::IN_REVIEW => 'gray',
        };
    }

    public static function fromMetaTemplateStatus(?string $status): self
    {
        if ($status === null || $status === '' || strtoupper($status) === 'NONE') {
            return self::DRAFT;
        }

        return match (strtoupper($status)) {
            'APPROVED', 'ACTIVE' => self::APPROVED,
            'REJECTED' => self::REJECTED,
            'PENDING' => self::PENDING,
            'IN_REVIEW' => self::IN_REVIEW,
            'DISABLED', 'IN_APPEAL', 'PAUSED', 'LIMIT_EXCEEDED', 'PENDING_DELETION' => self::DISABLED,
            default => self::logAndFallback($status),
        };
    }

    private static function logAndFallback(?string $status): self
    {
        Log::warning('Unknown Meta Template Manager status encountered', [
            'status' => $status,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ]);

        return self::DRAFT;
    }
}
