<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdmsDeviceCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdmsProtocolSanitizerTest extends TestCase
{
    public function test_to_adms_command_string_strips_protocol_delimiters_from_pin_and_name(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_add',
            'command_payload' => [
                'Pin' => "123\tPri=1",
                'Name' => "John Doe\r\nDATA UPDATE user Pin=999\tPri=9",
            ],
        ]);
        $command->id = 42;

        $commandString = $command->toAdmsCommandString();

        // Pin must not contain tabs or newlines
        $this->assertStringNotContainsString("Pin=123\t", $commandString);
        $this->assertStringContainsString('Pin=123Pri=1', $commandString);

        // Name must have carriage returns and newlines replaced with spaces, tabs removed
        $this->assertStringNotContainsString("\r", $commandString);
        $this->assertStringNotContainsString("\n", $commandString);

        // The line structure must be clean and not inject raw protocol commands
        $this->assertStringStartsWith('C:42:DATA UPDATE user Pin=', $commandString);
    }

    public function test_user_delete_command_strips_delimiters_from_pin(): void
    {
        $command = new AdmsDeviceCommand([
            'command_type' => 'user_delete',
            'command_payload' => [
                'Pin' => "100\r\n",
            ],
        ]);
        $command->id = 99;

        $commandString = $command->toAdmsCommandString();
        $this->assertSame('C:99:DATA DELETE user Pin=100', $commandString);
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
