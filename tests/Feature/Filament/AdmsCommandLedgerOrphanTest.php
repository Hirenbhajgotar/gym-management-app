<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\AdmsDeviceCommandResource;
use App\Filament\Admin\Resources\AdmsDeviceCommandResource\Pages\ListAdmsDeviceCommands;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Deleting a terminal nulls the command ledger's `device_id` while keeping the
 * `device_serial` snapshot. Those rows can never be delivered again, so deleting the
 * terminal must terminalise every undelivered row it leaves behind, and the UI must say
 * why a resend is refused instead of silently disabling it.
 */
class AdmsCommandLedgerOrphanTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $operator;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');

        $this->operator = User::factory()->create();
        $this->gym->users()->attach($this->operator->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->operator->assignRole($role);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'device_serial' => 'SN-LEDGER',
        ]);

        $this->actingAs($this->operator);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * The surviving half of the orphan lifecycle: a PENDING row is never leased (the FK is
     * gone), never pruned (PENDING is not a terminal state) and never resent, so it would
     * advertise "Pending" forever. It must be cancelled in the delete transaction itself.
     */
    public function test_deleting_a_terminal_cancels_its_undelivered_commands_and_audits_the_count(): void
    {
        $pending = $this->command([
            'status' => AdmsCommandStatusEnum::PENDING,
            'command_payload' => ['Pin' => '41'],
        ]);

        $sent = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now(),
            'command_payload' => ['Pin' => '42'],
        ]);

        $deviceId = $this->device->id;

        $this->assertTrue($this->device->delete());

        $pending->refresh();
        $sent->refresh();

        $this->assertSame(
            AdmsCommandStatusEnum::CANCELLED,
            $pending->status,
            'An undelivered command for a deleted terminal must be terminalised at deletion time.'
        );
        $this->assertSame(
            AdmsCommandStatusEnum::FAILED,
            $sent->status,
            'A delivered command is terminalised as FAILED: the terminal received it but can never finish it.'
        );

        foreach ([$pending, $sent] as $orphan) {
            $this->assertNull($orphan->device_id);
            $this->assertNull($orphan->leased_at);
            $this->assertNull($orphan->expires_at);
            $this->assertSame(AdmsDeviceCommand::TERMINAL_DELETED_CODE, $orphan->device_return_code);
            $this->assertSame('SN-LEDGER', $orphan->device_serial, 'The serial snapshot must survive the deletion.');
        }

        $activity = Activity::query()
            ->where('subject_type', BiometricDevice::class)
            ->where('subject_id', $deviceId)
            ->where('description', 'Biometric terminal deleted')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(2, (int) $activity->properties['cancelled_command_count']);
        $this->assertArrayNotHasKey(
            'cancelled_command_ids',
            $activity->properties->toArray(),
            'The audit entry intentionally omits the id list: a bounded sample is not reconstructible.'
        );
        $this->assertNotEmpty($activity->properties['evidence_recorded_at']);

        // The full set is reconstructed from the ledger, scoped to the deleted terminal's
        // gym and serial snapshot, rather than from a truncated sample in the audit entry.
        $this->assertSame(
            2,
            AdmsDeviceCommand::query()
                ->where('gym_id', $this->gym->id)
                ->whereNull('device_id')
                ->where('device_serial', 'SN-LEDGER')
                ->where('device_return_code', AdmsDeviceCommand::TERMINAL_DELETED_CODE)
                ->whereIn('id', [$pending->id, $sent->id])
                ->count()
        );
    }

    public function test_a_failed_orphan_is_visible_and_the_resend_action_explains_the_refusal(): void
    {
        $orphan = $this->failedOrphanCommand();

        // `canResend()` is not a deliverability check: the missing terminal is the action's
        // job to explain, and it must not be hidden behind a disabled button.
        $this->assertTrue(
            AdmsDeviceCommandResource::isAuthorizedToResend($orphan),
            'The permission guard stays permission-only; a missing terminal is not an authorization failure.'
        );

        Livewire::test(ListAdmsDeviceCommands::class)
            ->assertTableColumnStateSet('device_serial', 'SN-LEDGER', $orphan)
            ->assertSee('SN-LEDGER')
            ->assertTableActionVisible('resend', $orphan)
            ->callTableAction('resend', $orphan);

        Notification::assertNotified('Resend Refused');

        $orphan->refresh();

        $this->assertSame(AdmsCommandStatusEnum::FAILED, $orphan->status);
        $this->assertSame(3, $orphan->attempt_count);
        $this->assertSame(3, $orphan->max_attempt_count, 'A refused resend must not touch the doubled evidence.');
    }

    public function test_bulk_resend_skips_an_orphan_row(): void
    {
        $orphan = $this->failedOrphanCommand();

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableBulkAction('resendSelected', [$orphan]);

        Notification::assertNotified('1 command(s) skipped');

        $this->assertSame(AdmsCommandStatusEnum::FAILED, $orphan->refresh()->status);
    }

    public function test_a_stale_sent_command_with_a_live_terminal_is_reclaimed_to_pending(): void
    {
        $stale = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now()->subHour(),
        ]);

        $this->artisan('app:reclaim-stale-device-commands')->assertExitCode(0);

        $stale->refresh();

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $stale->status);
        $this->assertNull($stale->leased_at);
        $this->assertNull($stale->expires_at);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function command(array $overrides = []): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::PENDING,
            ...$overrides,
        ]);
    }

    private function failedOrphanCommand(): AdmsDeviceCommand
    {
        $command = $this->command([
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
        ]);

        // Deleting the terminal leaves a FAILED row untouched (it is already terminal), and
        // nulls its foreign key while keeping the serial snapshot.
        $this->device->delete();

        $command->refresh();

        $this->assertNull($command->device_id);
        $this->assertSame('SN-LEDGER', $command->device_serial);
        $this->assertSame(3, $command->max_attempt_count);

        return $command;
    }
}
