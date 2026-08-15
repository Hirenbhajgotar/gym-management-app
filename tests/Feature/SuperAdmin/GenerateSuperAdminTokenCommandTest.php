<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\DefaultRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GenerateSuperAdminTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate([
            'name' => DefaultRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
        ]);

        Role::query()->firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);
    }

    #[Test]
    public function it_generates_token_for_super_admin_user_via_email_argument(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'email' => 'superadmin@example.com',
        ]);
        $superAdmin->assignRole(DefaultRole::SUPER_ADMIN->value);

        /** @var PendingCommand $command */
        $command = $this->artisan('superadmin:generate-token', [
            'email' => 'superadmin@example.com',
            '--token-name' => 'test-log-viewer',
        ]);
        $command->expectsOutputToContain('Personal access token generated successfully!')
            ->expectsOutputToContain('LOG_VIEWER_PRODUCTION_TOKEN=')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(1, $superAdmin->tokens);

        /** @var PersonalAccessToken $token */
        $token = $superAdmin->tokens->first();
        $this->assertEquals('test-log-viewer', $token->name);
    }

    #[Test]
    public function it_fails_if_user_email_does_not_exist(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('superadmin:generate-token', [
            'email' => 'nonexistent@example.com',
        ]);
        $command->expectsOutputToContain('User with email [nonexistent@example.com] not found.')
            ->assertExitCode(1)
            ->run();
    }

    #[Test]
    public function it_fails_if_user_is_not_super_admin(): void
    {
        /** @var User $regularUser */
        $regularUser = User::factory()->create([
            'email' => 'regular@example.com',
        ]);
        $regularUser->assignRole(DefaultRole::GYM_OWNER->value);

        /** @var PendingCommand $command */
        $command = $this->artisan('superadmin:generate-token', [
            'email' => 'regular@example.com',
        ]);
        $command->expectsOutputToContain('does not have the Super Admin role.')
            ->assertExitCode(1)
            ->run();
    }

    #[Test]
    public function it_automatically_picks_single_super_admin_if_no_email_provided(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $superAdmin->assignRole(DefaultRole::SUPER_ADMIN->value);

        /** @var PendingCommand $command */
        $command = $this->artisan('superadmin:generate-token');
        $command->expectsOutputToContain('Personal access token generated successfully!')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(1, $superAdmin->tokens);
    }
}
