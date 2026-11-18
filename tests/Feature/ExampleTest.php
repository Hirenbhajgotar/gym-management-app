<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use ReusesMigratedTestDatabase;

    public function test_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
