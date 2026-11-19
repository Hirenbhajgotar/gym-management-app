<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Frontend\HomeController;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\TestCase;

#[CoversClass(HomeController::class)]
final class HomeControllerTest extends TestCase
{
    use ReusesMigratedTestDatabase;

    #[Test]
    public function home_page_can_be_rendered(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();

        Livewire::test(HomeController::class)
            ->assertOk();
    }
}
