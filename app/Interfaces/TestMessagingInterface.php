<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Test messaging operations interface.
 *
 * This interface defines test message operations for verifying setup.
 * Both Twilio and Meta WABA providers implement this interface.
 */
interface TestMessagingInterface
{
    /**
     * Send a test WhatsApp message to verify setup.
     *
     * @param  int  $gymId  The gym ID
     * @param  string|null  $to  Recipient number (optional)
     * @return array{
     *     success: bool,
     *     message_id?: string,
     *     status?: string,
     *     to?: string,
     *     mode?: string,
     *     error?: string,
     *     requires_template?: bool
     * }
     */
    public function sendTestWhatsAppMessage(int $gymId, ?string $to = null): array;
}
