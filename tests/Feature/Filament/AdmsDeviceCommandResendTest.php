<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\AdmsDeviceCommandResource\Pages\ListAdmsDeviceCommands;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdmsDeviceCommandResendTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $owner;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');

        $this->owner = User::factory()->create();
        $this->gym->users()->attach($this->owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->owner->assignRole($role);

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    public function test_single_resend_resets_attempt_count_and_enables_leasing(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '5001', 'Name' => 'Failed User'],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'sent_at' => now()->subHours(2),
            'leased_at' => now()->subHours(2),
            'expires_at' => now()->subHours(1),
            'acknowledged_at' => now()->subHours(1),
            'device_return_code' => '-1',
        ]);

        // Prior to resend, leaseNext must NOT lease an exhausted command (attempt_count >= 3)
        $unleased = AdmsDeviceCommand::leaseNext($this->device->id, 600);
        $this->assertNull($unleased);

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableAction('resend', $command);

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertSame(0, $command->attempt_count);
        $this->assertNull($command->sent_at);
        $this->assertNull($command->leased_at);
        $this->assertNull($command->expires_at);
        $this->assertNull($command->acknowledged_at);
        $this->assertNull($command->device_return_code);

        // After resend, leaseNext can lease the command
        $leased = AdmsDeviceCommand::leaseNext($this->device->id, 600);
        $this->assertNotNull($leased);
        $this->assertSame($command->id, $leased->id);
        $this->assertSame(1, $leased->attempt_count);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $leased->status);
    }

    public function test_bulk_resend_resets_attempt_count_for_selected_commands(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $cmd1 = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '6001'],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'device_return_code' => '-1',
        ]);

        $cmd2 = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '6002'],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'device_return_code' => '-2',
        ]);

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableBulkAction('resendSelected', [$cmd1, $cmd2]);

        $cmd1->refresh();
        $cmd2->refresh();

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $cmd1->status);
        $this->assertSame(0, $cmd1->attempt_count);

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $cmd2->status);
        $this->assertSame(0, $cmd2->attempt_count);
    }
}
