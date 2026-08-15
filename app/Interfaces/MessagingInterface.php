<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Core messaging operations interface.
 *
 * This interface defines all core messaging operations required
 * for sending WhatsApp messages. Both Twilio and Meta WABA providers
 * implement this interface.
 */
interface MessagingInterface
{
    /**
     * Send a WhatsApp message.
     *
     * @param  string  $to  Recipient phone number (with country code)
     * @param  string  $message  Message body
     * @return array{
     *     success: bool,
     *     message_id?: ?string,
     *     status?: ?string,
     *     error?: ?string,
     *     data?: mixed
     * }
     */
    public function sendMessage(string $to, string $message): array;

    /**
     * Send a WhatsApp message using a template.
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $templateName  Template name or SID
     * @param  array<array-key, string>  $variables  Template variables
     * @return array{
     *     success: bool,
     *     message_id?: ?string,
     *     status?: ?string,
     *     error?: ?string
     * }
     */
    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array;

    /**
     * Send a test message to verify configuration.
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $message  Test message content
     * @return array{
     *     success: bool,
     *     message_id?: ?string,
     *     status?: ?string,
     *     error?: ?string
     * }
     */
    public function sendTestMessage(string $to, string $message): array;

    /**
     * Send a broadcast to multiple recipients.
     *
     * Dispatches the broadcast asynchronously via a queued job.
     *
     * @param  int  $broadcastId  The broadcast ID
     */
    public function sendBroadcast(int $broadcastId): void;

    /**
     * Get the status of a sent message.
     *
     * Note: Not all providers support direct status lookup.
     *
     * @param  string  $messageId  Message SID or ID
     * @return array{
     *     success: bool,
     *     status?: string,
     *     delivered_at?: ?string,
     *     read_at?: ?string,
     *     error?: string,
     *     data?: array<array-key, mixed>
     * }
     */
    public function getMessageStatus(string $messageId): array;
}
