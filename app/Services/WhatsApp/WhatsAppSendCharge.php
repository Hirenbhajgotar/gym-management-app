<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Throwable;

/**
 * The single owner of the deduct/refund pair for a one-credit WhatsApp send.
 *
 * A send costs one credit, and it costs that credit only when the provider
 * accepted the message. Those two facts must be enforced in one place: a refund
 * issued from a second layer cannot know whether the layer beneath already
 * refunded, so the pair drifts into refunding twice for one deduction and the
 * gym's balance grows with every failed send.
 *
 * Refunds therefore never originate outside this class — both messaging services
 * run every post-deduction call through attempt(). The broadcast flow is
 * unaffected: it sets skipDeduction, where checkAndDeduct() and refund() are both
 * deliberate no-ops and the broadcast record owns its own reconciliation.
 */
final class WhatsAppSendCharge
{
    /**
     * Deduct one credit, run the send, and hand the credit back unless it landed.
     *
     * @param  callable(): array<string, mixed>  $send
     * @return array<string, mixed>
     *
     * @throws Throwable Whatever $send threw, unchanged.
     */
    public static function attempt(WhatsAppCreditService $credits, callable $send): array
    {
        // checkAndDeduct() throws when the balance is short, before anything is
        // taken, so it must not arm the refund.
        $deducted = false;

        try {
            $credits->checkAndDeduct(1);
            $deducted = true;

            $result = $send();
        } catch (Throwable $e) {
            if ($deducted) {
                $credits->refund(1);
            }

            throw $e;
        }

        if (($result['success'] ?? false) !== true) {
            $credits->refund(1);
        }

        return $result;
    }
}
