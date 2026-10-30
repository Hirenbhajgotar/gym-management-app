<?php

declare(strict_types=1);

namespace Tests\Unit\Biometric;

use App\Models\BiometricDevice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;

class BiometricCommKeyHelpersTest extends TestCase
{
    #[Test]
    public function generate_comm_key_produces_a_six_digit_numeric_string_without_a_leading_zero(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $key = BiometricDevice::generateCommKey();

            $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}$/', $key);
            $this->assertSame(BiometricDevice::COMM_KEY_LENGTH, strlen($key));
            $this->assertGreaterThanOrEqual(BiometricDevice::COMM_KEY_MIN_VALUE, (int) $key);
            $this->assertLessThanOrEqual(BiometricDevice::COMM_KEY_MAX_VALUE, (int) $key);
        }
    }

    #[Test]
    public function generation_boundaries_are_constrained_to_the_documented_range(): void
    {
        $this->assertSame(100000, BiometricDevice::COMM_KEY_MIN_VALUE);
        $this->assertSame(999999, BiometricDevice::COMM_KEY_MAX_VALUE);
        $this->assertSame(6, BiometricDevice::COMM_KEY_LENGTH);
        $this->assertSame(20, BiometricDevice::COMM_KEY_PASTE_BUFFER);
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function sanitizationCases(): array
    {
        return [
            'ascii spaces' => [' 123 456 ', '123456'],
            'tabs' => ["\t123\t456\t", '123456'],
            'newlines' => ["\n123\n456\r\n", '123456'],
            'nul bytes' => ["12\0003456", '123456'],
            'non-breaking space' => ["123\u{00A0}456", '123456'],
            'narrow non-breaking space' => ["123\u{202F}456", '123456'],
            'full-width space' => ["123\u{3000}456", '123456'],
            'zero-width space' => ["123\u{200B}456", '123456'],
            'zero-width non-joiner' => ["123\u{200C}456", '123456'],
            'zero-width joiner' => ["123\u{200D}456", '123456'],
            'soft hyphen' => ["123\u{00AD}456", '123456'],
            'word joiner' => ["123\u{2060}456", '123456'],
            'byte-order mark' => ["\u{FEFF}123456", '123456'],
            'next-line' => ["123\u{0085}456", '123456'],
            'alphanumeric with spaces' => [' ABC 123 ', 'ABC123'],
            'stringable object' => [new class() implements Stringable
            {
                public function __toString(): string
                {
                    return ' ABC 123 ';
                }
            }, 'ABC123'],
            'integer input' => [123456, '123456'],
            'null' => [null, ''],
            'array input' => [[123456], ''],
            'whitespace only' => [" \t\n\u{00A0}", ''],
            'invalid UTF-8' => ["12\xC3\x28 34", ''],
        ];
    }

    #[Test]
    #[DataProvider('sanitizationCases')]
    public function sanitize_comm_key_normalizes_the_documented_inputs(mixed $rawKey, string $expected): void
    {
        $this->assertSame($expected, BiometricDevice::sanitizeCommKey($rawKey));
    }
}
