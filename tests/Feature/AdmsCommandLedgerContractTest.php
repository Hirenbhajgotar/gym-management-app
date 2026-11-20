<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdmsDeviceCommand;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\TestCase;

/**
 * The ADMS command ledger contract: one transition interface, no superseded seams.
 */
class AdmsCommandLedgerContractTest extends TestCase
{
    use ReusesMigratedTestDatabase;

    public function test_superseded_cancel_in_flight_for_seam_is_removed_from_the_ledger(): void
    {
        $this->assertFalse(
            method_exists(AdmsDeviceCommand::class, 'cancelInFlightFor'),
            'cancelInFlightFor() was superseded by cancelActive() and must no longer be part of the ledger interface.'
        );
    }

    public function test_canonical_transition_seams_remain_on_the_ledger(): void
    {
        foreach ([
            'leaseNextForDelivery',
            'acknowledge',
            'reclaimExpiredLeases',
            'terminaliseForDeletedDevice',
            'cancelActive',
            'requeueForOperator',
            'requeueTerminalUserDelete',
            'requeueFailedReconciliation',
        ] as $method) {
            $this->assertTrue(
                method_exists(AdmsDeviceCommand::class, $method),
                "The ledger must still expose {$method}()."
            );
        }
    }
}
