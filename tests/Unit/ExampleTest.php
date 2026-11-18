<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Concerns\ReusesMigratedTestDatabase;

class ExampleTest extends TestCase
{
    use ReusesMigratedTestDatabase;

    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
