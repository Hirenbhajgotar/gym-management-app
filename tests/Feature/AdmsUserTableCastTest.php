<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Casts\AdmsUserTableCast;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\AdmsUserTableEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The `user_table` snapshot is read on every poll, from a column an operator can
 * edit out of band. A value the enum cannot parse must degrade to the configured
 * default rather than 500 the whole ADMS surface.
 */
class AdmsUserTableCastTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'biometrics.adms.require_comm_key' => false,
            'biometrics.adms.user_table' => AdmsUserTableEnum::UserInfo->value,
        ]);

        $this->gym = Gym::factory()->create();

        $this->device = BiometricDevice::factory()
            ->admsEnabled('SN-USER-TABLE-CAST')
            ->create(['gym_id' => $this->gym->id]);
    }

    #[Test]
    public function an_out_of_band_user_table_value_does_not_break_polling(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '369'],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        // Bypass the cast exactly as a restored dump, manual SQL fix or a rollback
        // across a schema change would.
        DB::table('adms_device_commands')
            ->where('id', $command->id)
            ->update(['user_table' => 'bogus']);

        $this->assertNull(
            $command->fresh()?->user_table,
            'An unparseable stored schema must hydrate as null, not throw.'
        );

        $response = $this->get('/api/iclock/getrequest?SN=SN-USER-TABLE-CAST');

        $response->assertOk();
        $this->assertSame(
            "C:{$command->id}:DATA DELETE USERINFO PIN=369\r\n",
            (string) $response->getContent(),
            'An unrecognised stored schema must serialise with the configured default.'
        );

        $fresh = $command->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $fresh->status);
        $this->assertSame(1, $fresh->attempt_count);
    }

    #[Test]
    public function the_cast_stores_the_raw_schema_value_on_write(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '369'],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->assertSame(AdmsUserTableEnum::UserInfo, $command->user_table);

        $command->user_table = AdmsUserTableEnum::User;
        $command->save();

        $this->assertSame(
            'user',
            DB::table('adms_device_commands')->where('id', $command->id)->value('user_table')
        );
        $this->assertSame(AdmsUserTableEnum::User, $command->fresh()?->user_table);
    }

    #[Test]
    public function try_parse_returns_an_enum_candidate_unchanged(): void
    {
        $this->assertSame(AdmsUserTableEnum::User, AdmsUserTableEnum::tryParse(AdmsUserTableEnum::User));
        $this->assertSame(AdmsUserTableEnum::UserInfo, AdmsUserTableEnum::tryParse(AdmsUserTableEnum::UserInfo));
    }

    #[Test]
    public function the_cast_is_registered_for_the_user_table_attribute(): void
    {
        $command = new AdmsDeviceCommand();

        $this->assertSame(
            AdmsUserTableCast::class,
            $command->getCasts()['user_table'] ?? null
        );
    }
}
