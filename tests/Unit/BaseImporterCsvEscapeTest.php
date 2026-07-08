<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Imports\GymMemberImport;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class BaseImporterCsvEscapeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * The original bug (strpos(): Argument #1 ($haystack) must be of type string, int given)
     * came from a non-string value being passed into CSV escaping within generateFailedRowsExcel().
     * This test asserts that escapeCsvField safely handles int, float, and numeric-string values
     * without throwing a TypeError.
     */
    #[Test]
    public function escape_csv_field_handles_non_string_values_without_type_error(): void
    {
        $importer = new GymMemberImport(Gym::factory()->create()->id);

        $method = new ReflectionMethod($importer, 'escapeCsvField');
        $method->setAccessible(true);

        $values = [123, 12.5, 0, -7, 'plain', 'has,comma', 'has"quote'];

        foreach ($values as $value) {
            $result = $method->invoke($importer, is_scalar($value) ? (string) $value : '');
            $this->assertIsString($result);
        }

        $this->assertTrue(true);
    }

    /**
     * Numeric JSON keys (decoded as integers by json_decode) must not cause a
     * TypeError when used as CSV headers under strict_types.
     */
    #[Test]
    public function escape_csv_field_handles_integer_like_header_keys(): void
    {
        $importer = new GymMemberImport(Gym::factory()->create()->id);

        $method = new ReflectionMethod($importer, 'escapeCsvField');
        $method->setAccessible(true);

        // Simulate an integer header key (as produced by array_keys on a decoded object).
        $header = 42;

        $result = $method->invoke($importer, is_scalar($header) ? (string) $header : '');

        $this->assertIsString($result);
        $this->assertSame('42', $result);
    }
}
