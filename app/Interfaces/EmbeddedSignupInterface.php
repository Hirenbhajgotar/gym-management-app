<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Embedded signup operations interface.
 *
 * This interface defines Meta embedded signup OAuth flow operations.
 * Meta-specific interface - Twilio doesn't support embedded signup.
 */
interface EmbeddedSignupInterface
{
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
}
