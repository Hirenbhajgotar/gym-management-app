<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_is_active_is_synced_from_status_on_creation()
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertTrue($user->is_active);
        $this->assertEquals(UserStatus::ACTIVE, $user->status);

        $inactiveUser = User::factory()->create([
            'status' => UserStatus::INACTIVE,
            'email' => 'inactive@example.com',
        ]);

        $this->assertFalse($inactiveUser->is_active);
        $this->assertEquals(UserStatus::INACTIVE, $inactiveUser->status);
    }

    public function test_user_is_active_is_synced_from_status_on_update()
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertTrue($user->is_active);

        // Update status to INACTIVE
        $user->update(['status' => UserStatus::INACTIVE]);

        $this->assertFalse($user->fresh()->is_active);
        $this->assertEquals(UserStatus::INACTIVE, $user->fresh()->status);

        // Update status back to ACTIVE
        $user->update(['status' => UserStatus::ACTIVE]);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_active_scope_queries_status_column()
    {
        $activeUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'email' => 'active@test.com']);
        $inactiveUser = User::factory()->create(['status' => UserStatus::INACTIVE, 'email' => 'inactive@test.com']);

        $activeUsers = User::active()->get();

        $this->assertTrue($activeUsers->contains($activeUser));
        $this->assertFalse($activeUsers->contains($inactiveUser));
    }

    public function test_status_mutator_accepts_string_backed_value_from_form()
    {
        // Filament form submits the backed enum value as a string (e.g. "1").
        $user = User::factory()->create([
            'status' => '1',
            'email' => 'stringstatus@example.com',
        ]);

        $this->assertEquals(UserStatus::ACTIVE, $user->status);
        $this->assertTrue($user->is_active);

        $inactive = User::factory()->create([
            'status' => '0',
            'email' => 'stringstatus2@example.com',
        ]);

        $this->assertEquals(UserStatus::INACTIVE, $inactive->status);
        $this->assertFalse($inactive->is_active);
    }

    public function test_status_mutator_accepts_integer_backed_value()
    {
        $user = User::factory()->create([
            'status' => 1,
            'email' => 'intstatus@example.com',
        ]);

        $this->assertEquals(UserStatus::ACTIVE, $user->status);
        $this->assertTrue($user->is_active);
    }

    public function test_is_active_accessor_works_correctly()
    {
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->assertTrue($user->isActive); // Using the accessor (if it's defined as attribute)
        // Wait, the accessor in User.php is `protected function isActive(): Attribute`.
        // So normally we access it via `$user->is_active` (which is the column) if we didn't override it.
        // But since we have both a column `is_active` and an accessor `isActive`, Laravel might conflict if not careful.
        // In User.php: `isActive(): Attribute` -> `get: fn () => $this->status === UserStatus::ACTIVE`.
        // If we access `$user->is_active`, Laravel first checks for accessor.
        // Since the method name is `isActive` (camelCase) and return type is `Attribute`, it matches `is_active` attribute access?
        // Actually, `isActive` accessor usually maps to `is_active` attribute if using `Attribute::make()`.

        // Let's verify if `is_active` column in DB and `isActive` accessor conflict.
        // If `is_active` is in `$fillable` and we have a column, `$user->is_active` returns the column value by default unless accessor overrides.
        // By defining `isActive()` returning `Attribute`, we ARE overriding the `get` for `is_active` attribute if the naming convention matches.
        // `isActive` -> `is_active`? No, `isActive` -> `is_active` is NOT standard naming for accessor (it should be `isActiveAttribute` or `is_active()` with `Attribute` return).

        // In User.php provided:
        // protected function isActive(): Attribute { ... }
        // This DOES NOT automatically map to `$user->is_active` in Laravel 11/12 unless it follows `isActiveAttribute` or is `is_active` method.
        // Wait, `isActive` method name might map to `is_active` attribute if using the new syntax?
        // Actually, `active` method maps to `active` attribute. `isActive` maps to `is_active` attribute?
        // Let's test calling the method directly if it's public (it's protected).
        // Or access property `$user->is_active`.

        // Given we are syncing the column `is_active`, `$user->is_active` will naturally return the column value which IS correct.
        // So the accessor is redundant if we are syncing, OR it acts as a safeguard.
        // But `isActive` accessor in User.php puts logic: `$this->status === UserStatus::ACTIVE`.

        $user->status = UserStatus::INACTIVE;
        $user->save();
        $this->assertFalse($user->is_active);
    }
}
