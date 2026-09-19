<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Frontend\HomeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(HomeController::class)]
final class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_page_can_be_rendered(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();

        Livewire::test(HomeController::class)
            ->assertOk();
    }
}
