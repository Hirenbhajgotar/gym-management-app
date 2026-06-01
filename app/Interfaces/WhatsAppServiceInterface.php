<?php

namespace App\Interfaces;

/**
 * Simple WhatsApp Service Interface (DEPRECATED).
 *
 * @deprecated Use WhatsAppProviderInterface instead. This interface only
 * supports the sendMessage method and is kept for backward compatibility.
 * @see WhatsAppProviderInterface For a comprehensive interface supporting
 * both Twilio and Meta WhatsApp Business API providers.
 */
interface WhatsAppServiceInterface
{
    /**
     * Send a WhatsApp message.
     *
     * @deprecated Use WhatsAppProviderInterface::sendMessage() instead.
     *             This method is maintained for backward compatibility.
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $message  Message body
     * @return array{
     *     success: bool,
     *     message_id?: string,
     *     status?: string,
     *     error?: string
     * }
     */
    public function sendMessage(string $to, string $message): array;
}
