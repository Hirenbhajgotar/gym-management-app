<?php

declare(strict_types=1);

namespace Tests\Feature\Unit;

use Tests\TestCase;

class FilamentPermissionsDiscoveryTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
