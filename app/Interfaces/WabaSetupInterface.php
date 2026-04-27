<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * WABA setup operations interface.
 *
 * This interface defines WhatsApp Business Account (WABA) setup operations.
 * Meta-specific interface - Twilio doesn't support WABA connections.
 */
interface WabaSetupInterface
{
    /**
     * Create a WABA for a gym.
     *
     * @param  int  $gymId  The gym ID
     * @param  array<string, string>  $businessProfile  Business profile data
     * @return array{
     *     success: bool,
     *     waba_id?: string,
     *     messaging_service_sid?: string,
     *     waba_mode?: string,
     *     message?: string,
     *     error?: string
     * }
     */
    public function createWaba(int $gymId, array $businessProfile): array;

    /**
     * Connect an individual WABA.
     *
     * @param  int  $gymId  The gym ID
     * @param  string  $wabaId  The WABA ID
     * @param  string  $phoneNumber  Phone number
     * @param  string|null  $metaAccessToken  Meta access token
     * @return array{
     *     success: bool,
     *     waba_id?: string,
     *     sender_sid?: string,
     *     message?: string,
     *     error?: string
     * }
     */
    public function connectIndividualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array;

    /**
     * Connect a manual WABA.
     *
     * This method handles complete WABA connection:
     * 1. If access token provided, verifies credentials via Meta API
     * 2. Creates or updates WhatsAppAccount with all fields
     * 3. Creates or updates WhatsAppPhoneNumber with all fields
     * 4. Uses Meta API authoritative data where available
     *
     * @param  int  $gymId  The gym ID
     * @param  string  $wabaId  The WABA ID
     * @param  string  $phoneNumber  Phone number
     * @param  string|null  $metaAccessToken  Meta access token (required for verification)
     * @param  string|null  $phoneNumberId  WABA phone number ID
     * @param  array{
     *     business_name?: string,
     *     business_email?: string,
     *     business_description?: string,
     *     verified_result?: array
     * }|null  $businessData  Optional business data from form
     * @return array{
     *     success: bool,
     *     waba_id?: string,
     *     phone_number_id?: string,
     *     display_phone_number?: string,
     *     business_name?: string,
     *     waba_status?: string,
     *     message?: string,
     *     error?: string
     * }
     */
    public function connectManualWaba(
        int $gymId,
        string $wabaId,
        string $phoneNumber,
        ?string $metaAccessToken = null,
        ?string $phoneNumberId = null,
        ?array $businessData = null
    ): array;

    /**
     * Verify WABA and phone number ID via Meta API.
     *
     * @param  string  $wabaId  The WhatsApp Business Account ID
     * @param  string  $phoneNumberId  The Phone Number ID
     * @param  string|null  $accessToken  Optional access token (uses account token if not provided)
     * @return array{
     *     success: bool,
     *     verified?: bool,
     *     phone_number_id?: string,
     *     display_phone_number?: string,
     *     verified_name?: string,
     *     code_verification_status?: string,
     *     certificate?: string,
     *     quality_score?: string,
     *     waba_id?: string,
     *     business_name?: string,
     *     waba_status?: string,
     *     currency?: string,
     *     timezone?: string,
     *     error?: string
     * }
     */
    public function verifyWabaConnection(string $wabaId, string $phoneNumberId, ?string $accessToken = null): array;
}
