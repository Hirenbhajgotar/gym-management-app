<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Phone number management operations interface.
 *
 * This interface defines phone number management operations.
 * Implemented by Twilio provider - Meta WABA uses Business Suite.
 */
interface PhoneNumberManagementInterface
{
    /**
     * Search for available phone numbers.
     *
     * @param  string  $country  Country code (e.g., 'US', 'IN')
     * @param  string  $type  Number type: 'local' or 'tollfree'
     * @param  int  $limit  Maximum results
     * @return array{
     *     success: bool,
     *     numbers?: array,
     *     error?: string
     * }
     */
    public function getAvailableNumbers(string $country, string $type = 'local', int $limit = 5): array;

    /**
     * Purchase a phone number for a gym.
     *
     * @param  int  $gymId  The gym ID
     * @param  string  $phoneNumber  The phone number to purchase
     * @return array{
     *     success: bool,
     *     phone_number?: string,
     *     message?: string,
     *     error?: string
     * }
     */
    public function purchaseNumber(int $gymId, string $phoneNumber): array;

    /**
     * Register a owned phone number (BYOD).
     *
     * @param  int  $gymId  The gym ID
     * @param  string  $phoneNumber  The phone number to register
     * @return array{
     *     success: bool,
     *     sender_sid?: string,
     *     status?: string,
     *     message?: string,
     *     error?: string
     * }
     */
    public function registerOwnNumber(int $gymId, string $phoneNumber): array;

    /**
     * Verify ownership of a phone number with OTP.
     *
     * @param  int  $gymId  The gym ID
     * @param  string  $senderSid  The sender SID
     * @param  string  $otpCode  The OTP code
     * @return array{
     *     success: bool,
     *     message?: string,
     *     error?: string
     * }
     */
    public function verifyOwnNumber(int $gymId, string $senderSid, string $otpCode): array;

    /**
     * Resend OTP for phone number verification.
     *
     * @param  int  $gymId  The gym ID
     * @param  string  $senderSid  The sender SID
     * @param  string  $channel  OTP channel (sms/whatsapp)
     * @return array{
     *     success: bool,
     *     message?: string,
     *     error?: string
     * }
     */
    public function resendOwnNumberOtp(int $gymId, string $senderSid, string $channel = 'sms'): array;
}
