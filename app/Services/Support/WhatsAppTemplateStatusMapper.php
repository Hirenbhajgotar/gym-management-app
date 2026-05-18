<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\WhatsAppTemplateStatusEnum;

/**
 * Shared status mapper for WhatsApp template status updates.
 *
 * Maps provider-specific status strings (Meta events, Twilio statuses)
 * to the internal WhatsAppTemplateStatusEnum. Used by both
 * ProcessMetaTemplateWebhook and ProcessTwilioTemplateWebhook jobs.
 */
final class WhatsAppTemplateStatusMapper
{
    /**
     * Map a provider status string to WhatsAppTemplateStatusEnum.
     *
     * Handles both Meta webhook events (APPROVED, REJECTED, PENDING, etc.)
     * and Twilio API statuses (approved, rejected, pending, etc.).
     * Case-insensitive matching.
     */
    public static function mapStatus(string $status): ?WhatsAppTemplateStatusEnum
    {
        return match (strtoupper($status)) {
            'APPROVED' => WhatsAppTemplateStatusEnum::APPROVED,
            'REJECTED' => WhatsAppTemplateStatusEnum::REJECTED,
            'FLAGGED' => WhatsAppTemplateStatusEnum::REJECTED,
            'PENDING' => WhatsAppTemplateStatusEnum::PENDING,
            'PENDING_DELETION' => WhatsAppTemplateStatusEnum::PENDING,
            'UNAPPROVED' => WhatsAppTemplateStatusEnum::PENDING,
            'DISABLED' => WhatsAppTemplateStatusEnum::DISABLED,
            'PAUSED' => WhatsAppTemplateStatusEnum::DISABLED,
            'UNSUBMITTED' => WhatsAppTemplateStatusEnum::UNSUBMITTED,
            default => null,
        };
    }
}
