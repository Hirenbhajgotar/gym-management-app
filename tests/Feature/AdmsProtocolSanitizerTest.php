<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsUserTableEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdmsProtocolSanitizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_delimiters_are_replaced_without_injecting_a_second_command(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_add',
            'command_payload' => [
                'Pin' => '123',
                'Name' => "John Doe\r\nDATA UPDATE USERINFO PIN=999\tPri=9",
            ],
        ]);
        $command->id = 42;

        $commandString = $command->toAdmsCommandString();

        // Name is free text: delimiters become spaces, never a new protocol line.
        $this->assertStringNotContainsString("\r", $commandString);
        $this->assertStringNotContainsString("\n", $commandString);
        $this->assertStringNotContainsString("\tDATA UPDATE", $commandString);
        $this->assertStringStartsWith('C:42:DATA UPDATE USERINFO PIN=123', $commandString);
        $this->assertStringContainsString('Name=John Doe  DATA UPDATE USERINFO PIN=999 Pri=9', $commandString);
        $this->assertStringContainsString('NAME=John Doe  DATA UPDATE USERINFO PIN=999 Pri=9', $commandString);
    }

    /**
     * `Pin` is an identity field. Stripping the delimiter from "123\tPri=1"
     * produced "123Pri=1" — a different terminal record — so the command must be
     * rejected instead of repaired.
     */
    public function test_pin_containing_control_characters_is_rejected_instead_of_retargeted(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_add',
            'command_payload' => [
                'Pin' => "123\tPri=1",
                'Name' => 'John Doe',
            ],
        ]);
        $command->id = 42;

        $this->assertSame('', $command->toAdmsCommandString());
    }

    public function test_user_delete_serialises_a_clean_pin_verbatim(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_delete',
            'command_payload' => [
                'Pin' => '100',
            ],
        ]);
        $command->id = 99;

        $this->assertSame('C:99:DATA DELETE USERINFO PIN=100', $command->toAdmsCommandString());
    }

    public function test_optional_password_and_card_are_omitted_when_unspecified(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_add',
            'command_payload' => [
                'Pin' => '369',
                'Name' => 'Hiren Sarla',
            ],
        ]);
        $command->id = 10;

        // Emitting `Passwd=`/`Card=` unconditionally turned "unspecified" into
        // "explicitly empty", which can clear a card already on the terminal.
        $this->assertSame(
            "C:10:DATA UPDATE USERINFO PIN=369\tName=Hiren Sarla\tNAME=Hiren Sarla\tPri=0\tPrivilege=0",
            $command->toAdmsCommandString()
        );
    }

    public function test_payload_accepts_case_insensitive_name_key(): void
    {
        foreach (['name', 'NAME', 'Name'] as $key) {
            $command = new AdmsDeviceCommand([
                'command_type' => 'user_add',
                'command_payload' => [
                    'Pin' => '369',
                    $key => 'Hiren Sarla',
                ],
            ]);
            $command->id = 10;

            $this->assertSame(
                "C:10:DATA UPDATE USERINFO PIN=369\tName=Hiren Sarla\tNAME=Hiren Sarla\tPri=0\tPrivilege=0",
                $command->toAdmsCommandString(),
                "Failed asserting key [{$key}] is recognized as user name"
            );
        }
    }

    public function test_password_and_card_are_never_emitted_because_nothing_produces_them(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_add',
            'command_payload' => [
                'Pin' => '369',
                'Name' => 'Hiren Sarla',
                'Passwd' => "12\t34",
                'Card' => "99\r\n",
            ],
        ]);
        $command->id = 10;

        // No producer writes `Passwd`/`Card`; forwarding them would put terminal
        // state the application never set onto the wire, so the fields are ignored
        // rather than sanitised and appended.
        $this->assertSame(
            "C:10:DATA UPDATE USERINFO PIN=369\tName=Hiren Sarla\tNAME=Hiren Sarla\tPri=0\tPrivilege=0",
            $command->toAdmsCommandString()
        );
    }

    public function test_the_global_config_selects_the_legacy_user_schema(): void
    {
        $originalUserTable = config('biometrics.adms.user_table');
        config(['biometrics.adms.user_table' => 'user']);

        try {
            $addCommand = new AdmsDeviceCommand([
                'command_type' => 'user_add',
                'command_payload' => [
                    'Pin' => '369',
                    'Name' => 'Hiren Sarla',
                ],
            ]);
            $addCommand->id = 10;
            $this->assertSame(
                "C:10:DATA UPDATE user Pin=369\tName=Hiren Sarla\tPri=0",
                $addCommand->toAdmsCommandString()
            );

            $deleteCommand = new AdmsDeviceCommand([
                'command_type' => 'user_delete',
                'command_payload' => [
                    'Pin' => '369',
                ],
            ]);
            $deleteCommand->id = 11;
            $this->assertSame('C:11:DATA DELETE user Pin=369', $deleteCommand->toAdmsCommandString());
        } finally {
            config(['biometrics.adms.user_table' => $originalUserTable]);
        }
    }

    public function test_a_blank_or_invalid_global_schema_falls_back_to_userinfo_not_the_legacy_schema(): void
    {
        $originalUserTable = config('biometrics.adms.user_table');

        try {
            foreach (['', '   ', 'USERINF', 'nonsense'] as $invalidValue) {
                config(['biometrics.adms.user_table' => $invalidValue]);

                $command = new AdmsDeviceCommand([
                    'command_type' => 'user_delete',
                    'command_payload' => ['Pin' => '369'],
                ]);
                $command->id = 11;

                $this->assertSame(
                    'C:11:DATA DELETE USERINFO PIN=369',
                    $command->toAdmsCommandString(),
                    "Invalid schema [{$invalidValue}] must not select the legacy user table."
                );
            }
        } finally {
            config(['biometrics.adms.user_table' => $originalUserTable]);
        }
    }

    public function test_the_per_device_override_is_snapshotted_when_the_command_is_queued(): void
    {
        $device = BiometricDevice::factory()->create([
            'provider_config' => ['adms_user_table' => 'user'],
        ]);

        $command = AdmsDeviceCommand::factory()->userAdd('369', 'Hiren Sarla')->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
        ]);

        $command->refresh();

        $this->assertSame(AdmsUserTableEnum::User, $command->user_table);
        $this->assertSame(
            "C:{$command->id}:DATA UPDATE user Pin=369\tName=Hiren Sarla\tPri=0",
            $command->toAdmsCommandString()
        );

        // The global default is untouched by the per-device override.
        $this->assertSame('USERINFO', config('biometrics.adms.user_table'));
    }

    public function test_a_device_without_an_override_inherits_the_global_default(): void
    {
        $device = BiometricDevice::factory()->create(['provider_config' => []]);

        $command = AdmsDeviceCommand::factory()->userDelete('369')->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
        ]);

        $this->assertSame(AdmsUserTableEnum::UserInfo, $command->fresh()->user_table);
        $this->assertSame('C:' . $command->id . ':DATA DELETE USERINFO PIN=369', $command->fresh()->toAdmsCommandString());
    }

    public function test_the_schema_is_not_re_read_from_the_device_at_delivery(): void
    {
        $device = BiometricDevice::factory()->create([
            'provider_config' => ['adms_user_table' => 'USERINFO'],
        ]);

        $command = AdmsDeviceCommand::factory()->userAdd('369', 'Hiren Sarla')->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
        ]);

        // Flipping the selector after the command was queued must not rewrite a
        // command the terminal has already been told to expect.
        $device->update(['provider_config' => ['adms_user_table' => 'user']]);

        $this->assertStringStartsWith(
            'C:' . $command->id . ':DATA UPDATE USERINFO PIN=369',
            $command->fresh()->toAdmsCommandString()
        );
    }

    public function test_a_pin_keyed_payload_alias_is_not_supported(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_delete',
            'command_payload' => ['PIN' => '369'],
        ]);
        $command->id = 12;

        // Nothing writes `PIN` into command_payload; accepting it only in the
        // serializer created a payload that could be serialized but never leased.
        $this->assertSame('', $command->toAdmsCommandString());
    }

    public function test_schema_independent_commands_serialise_identically_under_both_schemas(): void
    {
        foreach ([AdmsUserTableEnum::UserInfo, AdmsUserTableEnum::User] as $schema) {
            $reboot = new AdmsDeviceCommand(['command_type' => 'reboot', 'command_payload' => []]);
            $reboot->id = 3;
            $reboot->user_table = $schema;
            $this->assertSame('C:3:REBOOT', $reboot->toAdmsCommandString());

            $clearLog = new AdmsDeviceCommand(['command_type' => 'clear_log', 'command_payload' => []]);
            $clearLog->id = 4;
            $clearLog->user_table = $schema;
            $this->assertSame('C:4:CLEAR LOG', $clearLog->toAdmsCommandString());
        }
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function unusablePinProvider(): array
    {
        return [
            'empty string' => [['Pin' => ''], 'user_add'],
            'absent key' => [[], 'user_add'],
            'null value' => [['Pin' => null], 'user_add'],
            'array value' => [['Pin' => ['1']], 'user_delete'],
            'delimiters only' => [['Pin' => "\t\r\n"], 'user_delete'],
            'tab inside the value' => [['Pin' => "12\t3"], 'user_delete'],
            'newline inside the value' => [['Pin' => "12\n3"], 'user_add'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('unusablePinProvider')]
    public function test_commands_addressed_by_pin_are_not_serialised_without_a_usable_pin(
        array $payload,
        string $commandType
    ): void {
        $command = new AdmsDeviceCommand([
            'command_type' => $commandType,
            'command_payload' => $payload,
        ]);
        $command->id = 7;

        $this->assertSame('', $command->toAdmsCommandString());
    }

    public function test_non_provisioning_commands_still_serialise_without_a_pin(): void
    {
        $reboot = new AdmsDeviceCommand(['command_type' => 'reboot', 'command_payload' => []]);
        $reboot->id = 3;

        $this->assertSame('C:3:REBOOT', $reboot->toAdmsCommandString());

        $clearLog = new AdmsDeviceCommand(['command_type' => 'clear_log', 'command_payload' => []]);
        $clearLog->id = 4;

        $this->assertSame('C:4:CLEAR LOG', $clearLog->toAdmsCommandString());
    }
}
