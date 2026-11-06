<?php

declare(strict_types=1);

namespace Tests\Unit\Biometrics;

use App\Enums\AdmsUserTableEnum;
use App\Models\AdmsDeviceCommand;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Wire-format specification tests for ADMS terminal commands.
 *
 * Enforces ZKTeco / eSSL Push SDK v2.4.1 / Athwari standard compliance:
 * - Single PascalCase `Name=` attribute (no duplicate or all-caps `NAME=`).
 * - Privilege attribute `Privilege=0` under `USERINFO`.
 * - Trailing `Card=` attribute safely present without empty syntax faults.
 * - Hardware introspection `DATA QUERY USERINFO PIN={pin}` support.
 */
class AdmsDeviceCommandWireFormatTest extends TestCase
{
    #[Test]
    public function user_add_with_userinfo_schema_produces_athwari_standard_wire_format(): void
    {
        $command = new AdmsDeviceCommand([
            'id' => 11,
            'command_type' => 'user_add',
            'command_payload' => [
                'Pin' => '369',
                'Name' => 'Hiren',
            ],
        ]);
        $command->id = 11;
        $command->user_table = AdmsUserTableEnum::UserInfo;

        $commandString = $command->toAdmsCommandString();

        $expected = "C:11:DATA UPDATE USERINFO PIN=369\tName=Hiren\tPrivilege=0\tCard=";
        $this->assertSame($expected, $commandString);

        // Explicit negative assertions against experimental / defective tokens
        $this->assertStringNotContainsString('NAME=', $commandString, 'Must not carry duplicate all-caps NAME=');
        $this->assertStringNotContainsString('Pri=', $commandString, 'USERINFO must not carry short Pri= token');
        $this->assertStringNotContainsString('Passwd=', $commandString, 'Must not carry bare Passwd= token');
        $this->assertStringNotContainsString('Grp=', $commandString, 'Must not carry bare Grp= token');
    }

    #[Test]
    public function user_add_with_legacy_user_schema_produces_legacy_wire_format(): void
    {
        $command = new AdmsDeviceCommand([
            'id' => 12,
            'command_type' => 'user_add',
            'command_payload' => [
                'Pin' => '369',
                'Name' => 'Hiren',
            ],
        ]);
        $command->id = 12;
        $command->user_table = AdmsUserTableEnum::User;

        $commandString = $command->toAdmsCommandString();

        $expected = "C:12:DATA UPDATE user Pin=369\tName=Hiren\tPri=0";
        $this->assertSame($expected, $commandString);
    }

    #[Test]
    public function user_delete_with_userinfo_schema_produces_standard_delete_command(): void
    {
        $command = new AdmsDeviceCommand([
            'id' => 13,
            'command_type' => 'user_delete',
            'command_payload' => [
                'Pin' => '369',
            ],
        ]);
        $command->id = 13;
        $command->user_table = AdmsUserTableEnum::UserInfo;

        $this->assertSame('C:13:DATA DELETE USERINFO PIN=369', $command->toAdmsCommandString());
    }

    #[Test]
    public function user_delete_with_legacy_user_schema_produces_legacy_delete_command(): void
    {
        $command = new AdmsDeviceCommand([
            'id' => 14,
            'command_type' => 'user_delete',
            'command_payload' => [
                'Pin' => '369',
            ],
        ]);
        $command->id = 14;
        $command->user_table = AdmsUserTableEnum::User;

        $this->assertSame('C:14:DATA DELETE user Pin=369', $command->toAdmsCommandString());
    }

    #[Test]
    public function user_query_is_recognized_as_pin_addressed_command(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_query',
            'command_payload' => ['Pin' => '369'],
        ]);

        $this->assertTrue($command->isPinAddressed());
        $this->assertTrue($command->hasUsablePin());
    }

    #[Test]
    public function user_query_with_userinfo_schema_produces_hardware_introspection_command(): void
    {
        $command = new AdmsDeviceCommand([
            'id' => 15,
            'command_type' => 'user_query',
            'command_payload' => [
                'Pin' => '369',
            ],
        ]);
        $command->id = 15;
        $command->user_table = AdmsUserTableEnum::UserInfo;

        $this->assertSame('C:15:DATA QUERY USERINFO PIN=369', $command->toAdmsCommandString());
    }

    #[Test]
    public function user_query_with_legacy_user_schema_produces_hardware_introspection_command(): void
    {
        $command = new AdmsDeviceCommand([
            'id' => 16,
            'command_type' => 'user_query',
            'command_payload' => [
                'Pin' => '369',
            ],
        ]);
        $command->id = 16;
        $command->user_table = AdmsUserTableEnum::User;

        $this->assertSame('C:16:DATA QUERY user Pin=369', $command->toAdmsCommandString());
    }

    #[Test]
    public function query_all_users_produces_unfiltered_query_command(): void
    {
        $command = new AdmsDeviceCommand([
            'id' => 17,
            'command_type' => 'query_all_users',
        ]);
        $command->id = 17;
        $command->user_table = AdmsUserTableEnum::UserInfo;

        $this->assertFalse($command->isPinAddressed());
        $this->assertSame('C:17:DATA QUERY USERINFO', $command->toAdmsCommandString());
    }

    #[Test]
    public function query_all_users_with_legacy_user_schema_produces_unfiltered_query_command(): void
    {
        $command = new AdmsDeviceCommand([
            'id' => 18,
            'command_type' => 'query_all_users',
        ]);
        $command->id = 18;
        $command->user_table = AdmsUserTableEnum::User;

        $this->assertFalse($command->isPinAddressed());
        $this->assertSame('C:18:DATA QUERY user', $command->toAdmsCommandString());
    }
}
