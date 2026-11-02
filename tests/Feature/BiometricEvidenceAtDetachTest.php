<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The delivery evidence must survive detaching the row from its terminal.
 *
 * A null `enrolled_by_device_id` is produced by the tombstone hook (which attaches the
 * obligation flag first), by a restored dump, and by any writer that nulls the foreign key
 * directly. `wasDeliveredToHardware()` used to short-circuit to `false` on a null key, so the
 * sweep converged a credential the terminal was holding to `Deleted` and then hard-deleted it.
 *
 * The ledger is the durable record: every canonical `user_add` key ends with the template id
 * it enrolled, so the evidence is still addressable after the foreign key is gone.
 */
class BiometricEvidenceAtDetachTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected GymMember $member;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    #[Test]
    public function a_detached_pending_row_keeps_its_ledger_delivery_evidence(): void
    {
        $template = $this->pendingPlaceholder();
        $this->recordSentUserAdd($template);

        $this->detachWithoutHook($template);

        $template->refresh();

        $this->assertNull($template->enrolled_by_device_id);
        $this->assertFalse($template->mayBeHeldByHardware(), 'The row state alone proves nothing.');
        $this->assertTrue($template->wasDeliveredToHardware(), 'The ledger still proves delivery.');
        $this->assertTrue($template->hasDurableHardwareEvidence());
    }

    #[Test]
    public function the_sweep_parks_a_detached_delivered_pending_row_instead_of_deleting_it(): void
    {
        $template = $this->pendingPlaceholder();
        $this->recordSentUserAdd($template);
        $this->detachWithoutHook($template);

        $this->artisan('biometrics:cleanup-superseded-templates', ['--pending-days' => 1])
            ->assertExitCode(0);

        $fresh = BiometricTemplate::withTrashed()->find($template->id);

        $this->assertNotNull($fresh, 'A credential the terminal may hold must never be retired.');
        $this->assertNull($fresh->deleted_at);
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $fresh->sync_status);
        $this->assertTrue((bool) $fresh->is_erasure_obligation);
    }

    #[Test]
    public function the_sweep_still_retires_a_detached_placeholder_with_no_evidence(): void
    {
        $template = $this->pendingPlaceholder();
        $this->detachWithoutHook($template);

        $this->artisan('biometrics:cleanup-superseded-templates', ['--pending-days' => 1])
            ->assertExitCode(0);

        // A placeholder that provably never reached hardware is retired as before.
        $this->assertSoftDeleted('biometric_templates', ['id' => $template->id]);
    }

    private function pendingPlaceholder(): BiometricTemplate
    {
        $template = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
        ]);

        $template->created_at = now()->subDays(3);
        $template->saveQuietly();

        return $template;
    }

    private function recordSentUserAdd(BiometricTemplate $template): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $template->gym_member_id],
            'idempotency_key' => AdmsDeviceCommand::idempotencyKeyFor(
                $this->gym->id,
                $this->device->id,
                $template->gym_member_id,
                $template->id,
            ),
            'status' => AdmsCommandStatusEnum::SENT,
        ]);
    }

    /**
     * Reproduce a detach that bypassed the tombstone hook.
     */
    private function detachWithoutHook(BiometricTemplate $template): void
    {
        BiometricTemplate::query()
            ->whereKey($template->getKey())
            ->update(['enrolled_by_device_id' => null]);
    }
}
