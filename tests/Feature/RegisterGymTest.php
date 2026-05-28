<?php

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Filament\Admin\Pages\Tenancy\RegisterGym;
use App\Models\City;
use App\Models\Country;
use App\Models\Gym;
use App\Models\State;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterGymTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $superAdmin;

    /** @var \App\Models\User */
    protected $gymOwner;

    /** @var \App\Models\Gym */
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the required roles once
        Role::firstOrCreate(['name' => DefaultRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => DefaultRole::GYM_OWNER->value, 'guard_name' => 'web']);

        // Super-admin can do anything (panel access is tested elsewhere)
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole(DefaultRole::SUPER_ADMIN->value);

        // A regular user that already owns a gym
        $this->gymOwner = User::factory()->create();
        $this->tenant = Gym::factory()
            ->hasAttached($this->gymOwner, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();
    }

    /**
     * Helper to invoke protected method using reflection.
     */
    private function invokeProtectedMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function guest_is_redirected_to_login(): void
    {
        $this->get(filament()->getTenantRegistrationUrl())
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function authenticated_user_without_tenant_cannot_see_register_page(): void
    {
        $plainUser = User::factory()->create();

        $this->actingAs($plainUser)
            ->get(filament()->getTenantRegistrationUrl())
            ->assertForbidden();   // 403 – Filament default behaviour
    }

    public function gym_owner_of_existing_tenant_can_visit_register_page(): void
    {
        $this->actingAs($this->gymOwner)
            ->get(filament()->getTenantRegistrationUrl())
            ->assertSuccessful()
            ->assertSee('Register Gym');
    }

    public function gym_owner_can_successfully_create_new_gym(): void
    {
        $this->actingAs($this->gymOwner);

        // Arrange: Create a gym owner with 5 gyms
        Gym::factory()
            ->count(5)
            ->hasAttached($this->gymOwner, ['role' => 'gym_owner'], 'users')
            ->create();

        // Create a city, state, and country for valid address data
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->create(['state_id' => $state->id]);

        // Act: Register a new gym
        $response = Livewire::test(RegisterGym::class)
            ->set('data.name', 'New Fitness Hub')
            ->set('data.slug', 'new-fitness-hub')
            ->set('data.address.line1', '123 Main St')
            ->set('data.address.city', $city->id)
            ->set('data.address.state', $state->id)
            ->set('data.address.country', $country->id)
            ->set('data.address.zip', '62701')
            ->call('register');

        // Assert: Gym created, user attached, role assigned, and notification sent
        $this->assertDatabaseHas('gyms', [
            'name' => 'New Fitness Hub',
            'slug' => 'new-fitness-hub',
            'address->line1' => '123 Main St',
            'address->city' => $city->id,
            'address->state' => $state->id,
            'address->country' => $country->id,
            'address->zip' => '62701',
        ]);
        $this->assertDatabaseCount('gyms', 6);
        $this->assertDatabaseHas('gym_user', [
            'user_id' => $this->gymOwner->id,
            'role' => 'gym_owner',
        ]);
        $this->assertTrue($this->gymOwner->hasRole('gym_owner'));
        $response->assertHasNoErrors();
        // $response->assertDispatched('notify', function ($event, $data) {
        //     return $data['title'] === 'Gym Registered Successfully!' &&
        //         $data['body'] === 'You can register 4 more gym(s).' &&
        //         $data['level'] === 'success';
        // });
    }

    public function has_reached_gym_limit_returns_true_when_limit_reached()
    {
        // Arrange: Create a user and attach 10 gyms
        Gym::factory()
            ->count(10)
            ->hasAttached($this->gymOwner, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        // Act: Instantiate RegisterGym and set authenticated user
        $this->actingAs($this->gymOwner);
        $registerGym = new RegisterGym;

        // Assert: hasReachedGymLimit returns true
        $result = $this->invokeProtectedMethod($registerGym, 'hasReachedGymLimit');
        $this->assertTrue($result);
    }

    public function validation_rules_are_applied(): void
    {
        $this->actingAs($this->gymOwner);

        Livewire::test(RegisterGym::class)
            ->set('data.slug', '')
            ->call('register')
            ->assertHasErrors(['data.slug']);
    }

    public function validation_slug_rules_are_applied(): void
    {
        $this->actingAs($this->gymOwner);

        Livewire::test(RegisterGym::class)
            ->set('data.name', '')
            ->set('data.slug', '')
            ->set('data.address.street', '')
            ->set('data.address.country_id', '')
            ->set('data.address.state_id', '')
            ->set('data.address.city_id', '')
            ->call('register')
            ->assertHasErrors(['data.name'])
            ->assertHasErrors(['data.slug'])
            ->assertHasErrors(['data.address.street'])
            ->assertHasErrors(['data.address.country_id'])
            ->assertHasErrors(['data.address.state_id'])
            ->assertHasErrors(['data.address.city_id']);
    }

    public function form_fields_are_present(): void
    {
        Livewire::test(RegisterGym::class)
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('slug')
            ->assertFormFieldExists('address');
    }

    public function slug_is_generated_from_name_when_name_changes(): void
    {
        Livewire::test(RegisterGym::class)
            ->set('data.name', 'My Awesome Gym')
            ->assertSet('data.slug', 'my-awesome-gym');
    }

    public function slug_is_kept_unique_in_real_time(): void
    {
        Gym::factory()->create(['slug' => 'taken-slug']);

        Livewire::test(RegisterGym::class)
            ->set('data.slug', 'taken-slug')
            ->call('register')
            ->assertHasErrors('data.slug');
    }

    //
    // public function a_gym_can_be_registered_successfully(): void
    // {
    //     $this->partialMock(RolesAndPermissionsService::class, function ($mock) {
    //         $mock->shouldReceive('createDefaultTenantRolesAndAssignPermissions')
    //             ->once()
    //             ->with($this->user, Mockery::type(Gym::class));
    //     });

    //     // Grab the first seeded city (and its state & country) so the values really exist
    //     $city   = \App\Models\City::first();       // requires the table + seeder
    //     $state  = $city->state;                    // belongsTo relationship
    //     $country = $state->country;                // belongsTo relationship

    //     Livewire::test(RegisterGym::class)
    //         ->set('data.name', 'New Gym')
    //         ->set('data.slug', 'new-gym')
    //         ->set('data.address.line1', '123 Main St')
    //         ->set('data.address.city', $city->id)       // send the id (or uuid)
    //         ->set('data.address.state', $state->id)     // id
    //         ->set('data.address.country', $country->id) // id
    //         ->set('data.address.zip', '62701')
    //         ->call('register')
    //         ->assertHasNoFormErrors()
    //         ->assertNotified(
    //             fn(Notification $n) => $n->title === 'Gym Registered Successfully!'
    //         );

    //     $this->assertDatabaseHas('gyms', [
    //         'name' => 'New Gym',
    //         'slug' => 'new-gym',
    //     ]);

    //     $this->assertDatabaseHas('gym_user', [
    //         'gym_id'  => Gym::first()->id,
    //         'user_id' => $this->user->id,
    //         'role'    => DefaultRole::GYM_OWNER->value,
    //     ]);

    //     $this->assertTrue($this->user->fresh()->hasRole(DefaultRole::GYM_OWNER->value));
    // }

    //
    // public function user_cannot_exceed_maximum_gyms_allowed(): void
    // {
    //     Gym::factory()->count(10)->create();

    //     Livewire::test(RegisterGym::class)
    //         ->assertSet('hasReachedGymLimit', true)
    //         ->assertDontSee('Register')
    //         ->call('register')
    //         ->assertDispatched(Notification::class, function ($eventName, $params) {
    //             return ($params['message'] ?? '') === 'Registration Failed';
    //         });

    //     $this->assertCount(10, Gym::all());
    // }

    //
    // public function remaining_count_shown_on_success_notification(): void
    // {
    //     Gym::factory()->count(8)->create(['owner_id' => $this->user->id]);

    //     Livewire::test(RegisterGym::class)
    //         ->set('data.name', 'Almost Full')
    //         ->set('data.slug', 'almost-full')
    //         ->set('data.address.line1', '1 Test Lane')
    //         ->call('register')
    //         ->assertNotified(
    //             fn(Notification $n) => str_contains($n->body, '2 more gym(s)')
    //         );
    // }

    //
    // public function user_without_gyms_sees_full_allowance(): void
    // {
    //     Livewire::test(RegisterGym::class)
    //         ->assertSee('You can register 10 more gym(s). Current: 0/10');
    // }
}
