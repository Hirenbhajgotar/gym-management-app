<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\Permission;
use App\Services\RolesAndPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_discovers_permissions_via_dry_run(): void
    {
        $output = Artisan::call('permissions:sync', ['--dry-run' => true]);

        $this->assertSame(0, $output);

        $consoleOutput = Artisan::output();
        $this->assertStringContainsString('DRY RUN MODE', $consoleOutput);
        $this->assertStringContainsString('Discovered', $consoleOutput);
    }

    #[Test]
    public function it_does_not_create_permissions_in_dry_run(): void
    {
        $countBefore = Permission::count();

        Artisan::call('permissions:sync', ['--dry-run' => true]);

        $this->assertSame($countBefore, Permission::count());
    }

    #[Test]
    public function it_creates_missing_permissions(): void
    {
        $service = app(RolesAndPermissionsService::class);
        $discovered = $service->discoverFilamentPermissions();

        $before = Permission::pluck('name')->toArray();
        $missingBefore = array_diff($discovered, $before);
        $this->assertNotEmpty($missingBefore, 'Should have missing permissions before sync');

        Artisan::call('permissions:sync');

        $after = Permission::pluck('name')->toArray();
        $stillMissing = array_diff($discovered, $after);
        $this->assertEmpty($stillMissing, 'All discovered permissions should exist after sync');
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        Artisan::call('permissions:sync');
        $countAfterFirst = Permission::count();

        Artisan::call('permissions:sync');
        $countAfterSecond = Permission::count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    #[Test]
    public function it_output_shows_discovered_permission_count(): void
    {
        Artisan::call('permissions:sync', ['--dry-run' => true]);

        $output = Artisan::output();
        $this->assertStringContainsString('permissions from Filament classes', $output);
    }

    #[Test]
    public function it_output_shows_missing_permission_count(): void
    {
        Artisan::call('permissions:sync', ['--dry-run' => true]);

        $output = Artisan::output();
        $this->assertStringContainsString('missing permissions', $output);
    }

    #[Test]
    public function it_shows_summary_table(): void
    {
        Artisan::call('permissions:sync', ['--dry-run' => true]);

        $output = Artisan::output();
        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('New permissions created', $output);
    }
}
