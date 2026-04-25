<?php

namespace App\Interfaces;

use App\Models\WhatsAppTemplate;

/**
 * Comprehensive WhatsApp Provider Interface.
 *
 * This interface defines all operations required for WhatsApp messaging
 * across different providers (Twilio, Meta WABA).
 *
 * Methods are grouped by functionality:
 * - Messaging: Send and manage messages
 * - Account: Create and manage sub-accounts
 * - Phone Numbers: Search, purchase, and verify phone numbers
 * - Templates: Create and manage message templates
 * - Credits: Manage message credits and usage
 */
interface WhatsAppProviderInterface
{
    // =========================
    // Messaging Operations
    // =========================

    /**
     * Send a WhatsApp message.
     *
     * @param  string  $to  Recipient phone number (with country code)
     * @param  string  $message  Message body or template reference
     * @return array{
     *     success: bool,
     *     message_id?: string,
     *     status?: string,
     *     error?: string,
     *     data?: array
     * }
     */
    public function sendMessage(string $to, string $message): array;

    /**
     * Send a WhatsApp message using a template.
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $templateName  Template name or SID
     * @param  array<string, string>  $variables  Template variables
     * @return array{
     *     success: bool,
     *     message_id?: string,
     *     status?: string,
     *     error?: string
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
     *     message_id?: string,
     *     status?: string,
     *     error?: string
     * }
     */
    public function sendTestMessage(string $to, string $message): array;

    /**
     * Send a broadcast to multiple recipients.
     *
     * @param  int  $broadcastId  The broadcast ID
     * @return array{
     *     total: int,
     *     sent: int,
     *     failed: int
     * }
     */
    public function sendBroadcast(int $broadcastId): array;

    /**
     * Get the status of a sent message.
     *
     * @param  string  $messageId  Message SID or ID
     * @return array{
     *     success: bool,
     *     status?: string,
     *     delivered_at?: ?string,
     *     read_at?: ?string,
     *     error?: string
     * }
     */
    public function getMessageStatus(string $messageId): array;

    // =========================
    // Account Management
    // =========================

    /**
     * Create a sub-account for a gym.
     *
     * @param  int  $gymId  The gym ID
     * @param  string  $gymName  The gym name
     * @param  string  $email  Business email
     * @return array{
     *     success: bool,
     *     account_sid?: string,
     *     message?: string,
     *     error?: string
     * }
     */
    public function createSubAccount(int $gymId, string $gymName, string $email): array;

    /**
     * Get the status of the WhatsApp account.
     *
     * @param  int  $gymId  The gym ID
     * @return array{
     *     success: bool,
     *     status?: string,
     *     verification_status?: string,
     *     messages_sent?: int,
     *     monthly_cost?: float,
     *     error?: string
     * }
     */
    public function getAccountStatus(int $gymId): array;

    /**
     * Clean up all resources for a gym account.
     *
     * @param  int  $gymId  The gym ID
     * @return array{
     *     success: bool,
     *     message?: string,
     *     cleaned?: array,
     *     errors?: array
     * }
     */
    public function cleanupAccount(int $gymId): array;

    /**
     * Get the WABA mode for a gym.
     *
     * @param  int  $gymId  The gym ID
     * @return string One of: 'shared', 'individual', 'manual', 'not_created'
     */
    public function getWabaMode(int $gymId): string;

    /**
     * Check if embedded signup is required.
     *
     * @param  int  $gymId  The gym ID
     */
    public function requiresEmbeddedSignup(int $gymId): bool;

    // =========================
    // Phone Number Management
    // =========================

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

    // =========================
    // Template Management
    // =========================

    /**
     * Create a new WhatsApp template.
     *
     * @param  int  $gymId  The gym ID
     * @param  array<string, mixed>  $data  Template data
     */
    public function createTemplate(int $gymId, array $data): WhatsAppTemplate;

    /**
     * Submit a template for verification/approval.
     *
     * @param  WhatsAppTemplate  $template  The template to submit
     * @return array{
     *     success: bool,
     *     status?: string,
     *     sid?: string,
     *     error?: string
     * }
     */
    public function submitTemplate(WhatsAppTemplate $template): array;

    /**
     * Sync template status from provider.
     *
     * @param  WhatsAppTemplate  $template  The template to sync
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void;

    /**
     * Get all available templates.
     *
     * @param  int  $gymId  The gym ID
     * @return array{
     *     success: bool,
     *     templates?: array,
     *     count?: int,
     *     error?: string
     * }
     */
    public function getTemplates(int $gymId): array;

    // =========================
    // Credit Management
    // =========================

    /**
     * Get credit balance for a gym.
     *
     * @return array{
     *     total_credits: int,
     *     used_credits: int,
     *     remaining_credits: int,
     *     welcome_credits_remaining: int,
     *     can_send: bool,
     *     usage_percentage: float
     * }
     */
    public function getCreditBalance(): array;

    /**
     * Get monthly usage statistics.
     *
     * @param  int  $year  Year
     * @param  int  $month  Month (1-12)
     * @return array{
     *     total_messages: int,
     *     delivered: int,
     *     failed: int,
     *     total_cost: float
     * }
     */
    public function getMonthlyUsage(int $year, int $month): array;

    // =========================
    // WABA Setup (Meta-specific)
    // =========================

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

    // =========================
    // WABA Verification
    // =========================

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

    // =========================
    // Embedded Signup (Meta-specific)
    // =========================

    /**
     * Start the embedded signup flow for a gym.
     *
     * Creates a WABA setup session and returns the Meta OAuth login URL.
     *
     * @param  int  $gymId  The gym ID
     * @return string The Facebook OAuth login URL for embedded signup
     */
    public function startEmbeddedSignup(int $gymId): string;

    /**
     * Get the embedded signup login URL.
     *
     * @param  int  $gymId  The gym ID
     * @return string The Facebook OAuth login URL
     */
    public function getEmbeddedSignupLoginUrl(int $gymId): string;

    /**
     * Handle the embedded signup OAuth callback.
     *
     * Exchanges the authorization code for an access token, verifies the token,
     * and retrieves the WABA and phone number IDs.
     *
     * @param  string  $code  The authorization code from Meta
     * @param  string  $state  The session state
     * @return array{
     *     success: bool,
     *     waba_id?: string,
     *     phone_number_id?: string,
     *     access_token?: string,
     *     error?: string
     * }
     */
    public function handleEmbeddedSignupCallback(string $code, string $state): array;

    // =========================
    // Test Messages
    // =========================

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
