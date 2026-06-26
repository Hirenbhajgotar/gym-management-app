<?php

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Filament\Admin\Pages\Tenancy\EditGymProfile;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Gym;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditGymProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $gymOwner;

    protected User $otherUser;

    protected Gym $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(['name' => DefaultRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => DefaultRole::GYM_OWNER->value, 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create()->assignRole(DefaultRole::SUPER_ADMIN->value);

        $this->gymOwner = User::factory()->create()->assignRole(DefaultRole::GYM_OWNER->value);
        $this->tenant = Gym::factory()
            ->hasAttached($this->gymOwner, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->has(Address::factory()->for(Gym::factory()), 'address') // existing address
            ->create();

        $this->otherUser = User::factory()->create();
    }

    /* ---------------------------------------------------------
     |  Access control
     * ------------------------------------------------------- */

    public function guest_is_redirected_to_login(): void
    {
        $this->get(EditGymProfile::getUrl(['tenant' => $this->tenant]))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function user_without_tenant_access_cannot_view_edit_page(): void
    {
        $this->actingAs($this->otherUser)
            ->get(EditGymProfile::getUrl(['tenant' => $this->tenant]))
            ->assertForbidden();
    }

    public function gym_owner_of_tenant_can_view_edit_page(): void
    {
        $this->actingAs($this->gymOwner)
            ->get(EditGymProfile::getUrl(['tenant' => $this->tenant]))
            ->assertSuccessful()
            ->assertSee('Gym profile');
    }

    /* ---------------------------------------------------------
     |  Submission / update flow
     * ------------------------------------------------------- */

    public function test_gym_owner_can_successfully_edit_gym_profile()
    {
        $this->actingAs($this->gymOwner);

        // Arrange: Create city, state, and country for valid address data
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->create(['state_id' => $state->id]);

        // Act: Update the gym profile
        $response = Livewire::test(EditGymProfile::class, ['tenant' => $this->tenant])
            ->set('data.name', 'New Fitness Hub')
            ->set('data.slug', 'new-fitness-hub')
            ->set('data.address.line1', '123 Main St')
            ->set('data.address.city', $city->id)
            ->set('data.address.state', $state->id)
            ->set('data.address.country', $country->id)
            ->set('data.address.zip', '62701')
            ->call('save');

        // Assert: Gym updated, notification sent, and redirect triggered
        $this->assertDatabaseHas('gyms', [
            'id' => $this->tenant->id,
            'name' => 'New Fitness Hub',
            'slug' => 'new-fitness-hub',
            'address->line1' => '123 Main St',
            'address->city' => $city->id,
            'address->state' => $state->id,
            'address->country' => $country->id,
            'address->zip' => '62701',
        ]);
        $response->assertHasNoErrors();
        $response->assertDispatched('notify', function ($event, $data) {
            return $data['title'] === 'Gym Profile Updated';
        });
        $response->assertRedirect(route('filament.admin.tenant.edit', ['tenant' => 'new-fitness-hub']));
    }

    public function slug_must_be_unique_except_for_current_tenant(): void
    {
        Gym::factory()->create(['slug' => 'existing-slug']);

        $response = $this->actingAs($this->gymOwner)
            ->patch(EditGymProfile::getUrl(['tenant' => $this->tenant]), [
                'name' => 'Test',
                'slug' => 'existing-slug',
            ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function name_is_required(): void
    {
        $response = $this->actingAs($this->gymOwner)
            ->patch(EditGymProfile::getUrl(['tenant' => $this->tenant]), [
                'name' => '',
                'slug' => 'valid-slug',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function user_cannot_edit_tenant_they_do_not_own(): void
    {
        $otherGym = Gym::factory()->create(); // no pivot for $this->gymOwner

        $response = $this->actingAs($this->gymOwner)
            ->patch(EditGymProfile::getUrl(['tenant' => $otherGym]), [
                'name' => 'Evil Edit',
                'slug' => 'evil-edit',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('gyms', ['name' => 'Evil Edit']);
    }
}
