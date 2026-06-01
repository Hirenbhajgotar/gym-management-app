<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Credit management operations interface.
 *
 * This interface defines credit and usage tracking operations.
 * Both Twilio and Meta WABA providers implement this interface.
 */
interface CreditManagementInterface
{
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
}
