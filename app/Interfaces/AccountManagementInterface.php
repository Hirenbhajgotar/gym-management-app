<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Account management operations interface.
 *
 * This interface defines account management operations.
 * Implemented by Twilio provider - Meta WABA uses WhatsAppAccount model.
 */
interface AccountManagementInterface
{
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
     * Check if embedded signup is required.
     *
     * @param  int  $gymId  The gym ID
     */
    public function requiresEmbeddedSignup(int $gymId): bool;
}
