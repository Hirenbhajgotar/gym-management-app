<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Admin\Resources\GymMemberResource\Pages\CreateGymMember;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymMemberPhoneEmailValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_optional_on_create(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);
        Filament::setTenant($gym);
        $this->actingAs($user);

        Livewire::test(CreateGymMember::class)
            ->fillForm([
                'name' => 'No Email Member',
                'phone' => '+15559990001',
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gym_members', [
            'phone' => '+15559990001',
            'email' => null,
        ]);
    }

    public function test_phone_is_required_on_create(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);
        Filament::setTenant($gym);
        $this->actingAs($user);

        Livewire::test(CreateGymMember::class)
            ->fillForm([
                'name' => 'No Phone Member',
                'email' => 'nophone@example.com',
            ])
            ->call('create')
            ->assertHasErrors(['data.phone']);
    }

    public function test_phone_must_be_unique_per_gym(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);
        Filament::setTenant($gym);
        $this->actingAs($user);

        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'phone' => '+15551234567',
        ]);

        Livewire::test(CreateGymMember::class)
            ->fillForm([
                'name' => 'Duplicate Phone Member',
                'email' => 'dup@example.com',
                'phone' => '+15551234567',
            ])
            ->call('create')
            ->assertHasErrors(['data.phone']);
    }

    public function test_same_phone_allowed_in_different_gyms(): void
    {
        $gymA = Gym::factory()->create();
        $gymB = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach([$gymA->id, $gymB->id]);

        GymMember::factory()->create([
            'gym_id' => $gymA->id,
            'phone' => '+15551234567',
        ]);

        Filament::setTenant($gymB);
        $this->actingAs($user);

        Livewire::test(CreateGymMember::class)
            ->fillForm([
                'name' => 'Same Phone Different Gym',
                'email' => 'different@example.com',
                'phone' => '+15551234567',
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gym_members', [
            'gym_id' => $gymB->id,
            'phone' => '+15551234567',
        ]);
    }

    public function test_factory_generates_unique_phones(): void
    {
        $gym = Gym::factory()->create();
        $members = GymMember::factory()->count(10)->create(['gym_id' => $gym->id]);

        $phones = $members->pluck('phone');
        $this->assertCount(10, $phones->unique());
    }
}
