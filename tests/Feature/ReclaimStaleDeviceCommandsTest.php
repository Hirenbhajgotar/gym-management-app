<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ledger rows for a deleted terminal have `device_id = null` and no poll will ever
 * lease them again. The reclaim command must terminalise them instead of
 * returning them to PENDING, and the UI must stop promising a resend.
 */
class ReclaimStaleDeviceCommandsTest extends TestCase
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
        ]);
    }

    public function test_a_stale_command_for_a_deleted_terminal_is_cancelled_and_can_no_longer_be_resent(): void
    {
        $orphan = $this->orphanedSentCommand('SN-ORPHAN-001');

        $this->artisan('app:reclaim-stale-device-commands')
            ->expectsOutputToContain('cancelled 1 orphaned commands')
            ->assertExitCode(0);

        $orphan->refresh();

        $this->assertSame(
            AdmsCommandStatusEnum::CANCELLED,
            $orphan->status,
            'A stale SENT row with no terminal must be terminalised, not resurrected.'
        );
        $this->assertNotSame(AdmsCommandStatusEnum::PENDING, $orphan->status);
        $this->assertNull($orphan->leased_at);
        $this->assertNull($orphan->expires_at);

        $this->actingAs($this->operator);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // `canResend()` is deliberately not a deliverability check: the missing terminal is
        // explained by the action, which stays reachable on a FAILED orphan. A CANCELLED row
        // is hidden by the status predicate alone, so this asserts the composition instead.
        $this->assertTrue(AdmsDeviceCommandResource::canResend($orphan));

        Livewire::test(ListAdmsDeviceCommands::class)
            ->assertTableColumnStateSet('device_serial', 'SN-ORPHAN-001', $orphan)
            ->assertSee('SN-ORPHAN-001')
            ->assertTableActionHidden('resend', $orphan);
    }

    public function test_a_stale_command_bound_to_an_existing_terminal_still_returns_to_pending(): void
    {
        $stale = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now()->subHour(),
        ]);

        $this->artisan('app:reclaim-stale-device-commands')
            ->expectsOutputToContain('Reclaimed 1 commands to pending')
            ->assertExitCode(0);

        $stale->refresh();

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $stale->status);
        $this->assertNull($stale->leased_at);
    }

    private function orphanedSentCommand(string $serial): AdmsDeviceCommand
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => null,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => null,
        ]);

        // `device_serial` is a non-fillable snapshot written by the model hook for
        // a live device; an orphan has to seed it the way the FK null-on-delete
        // path leaves it behind.
        $command->forceFill(['device_serial' => $serial])->saveQuietly();

        return $command;
    }
}
