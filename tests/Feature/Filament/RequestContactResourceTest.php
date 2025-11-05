<?php

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Filament\SuperAdmin\Resources\RequestContactResource;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages\CreateRequestContact;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages\EditRequestContact;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages\ListRequestContacts;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages\ViewRequestContact;
use App\Models\RequestContact;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RequestContactResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::firstOrCreate(['name' => DefaultRole::SUPER_ADMIN->value, 'guard_name' => 'web']);

        // Create super admin user
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
        ])->assignRole(DefaultRole::SUPER_ADMIN->value);

        // Set the super-admin panel as current
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));
    }

    public function test_can_list_request_contacts(): void
    {
        $requestContacts = RequestContact::factory()->count(5)->create();

        Livewire::actingAs($this->superAdmin)
            ->test(ListRequestContacts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($requestContacts);
    }

    public function test_can_filter_uncontacted_requests(): void
    {
        $contacted = RequestContact::factory()->create([
            'is_contacted' => true,
            'contacted_at' => now(),
        ]);

        $uncontacted = RequestContact::factory()->count(3)->create([
            'is_contacted' => false,
            'contacted_at' => null,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListRequestContacts::class)
            ->assertSuccessful()
            ->filterTable('is_contacted', false)
            ->assertCanSeeTableRecords($uncontacted)
            ->assertCanNotSeeTableRecords([$contacted]);
    }

    public function test_can_filter_contacted_requests(): void
    {
        $contacted = RequestContact::factory()->count(2)->create([
            'is_contacted' => true,
            'contacted_at' => now(),
        ]);

        $uncontacted = RequestContact::factory()->create([
            'is_contacted' => false,
            'contacted_at' => null,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListRequestContacts::class)
            ->assertSuccessful()
            ->filterTable('is_contacted', true)
            ->assertCanSeeTableRecords($contacted)
            ->assertCanNotSeeTableRecords([$uncontacted]);
    }

    public function test_can_search_request_contacts(): void
    {
        $requestContact = RequestContact::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $other = RequestContact::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListRequestContacts::class)
            ->searchTable('John Doe')
            ->assertCanSeeTableRecords([$requestContact])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_can_create_request_contact(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(CreateRequestContact::class)
            ->fillForm([
                'name' => 'Test Contact',
                'email' => 'test@example.com',
                'phone' => '+1234567890',
                'company_name' => 'Test Company',
                'job_title' => 'Manager',
                'gym_size' => 100,
                'preferred_contact_method' => 'email',
                'best_time_to_contact' => 'morning',
                'message' => 'This is a test message',
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(RequestContact::class, [
            'name' => 'Test Contact',
            'email' => 'test@example.com',
            'company_name' => 'Test Company',
        ]);
    }

    public function test_can_edit_request_contact(): void
    {
        $requestContact = RequestContact::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(EditRequestContact::class, [
                'record' => $requestContact->getRouteKey(),
            ])
            ->fillForm([
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'is_contacted' => true,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(RequestContact::class, [
            'id' => $requestContact->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_contacted' => true,
        ]);
    }

    public function test_can_view_request_contact(): void
    {
        $requestContact = RequestContact::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'message' => 'Test message',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ViewRequestContact::class, [
                'record' => $requestContact->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('John Doe')
            ->assertSee('john@example.com')
            ->assertSee('+1234567890')
            ->assertSee('Test message');
    }

    public function test_can_delete_request_contact(): void
    {
        $requestContact = RequestContact::factory()->create();

        Livewire::actingAs($this->superAdmin)
            ->test(EditRequestContact::class, [
                'record' => $requestContact->getRouteKey(),
            ])
            ->callAction('delete');

        $this->assertModelMissing($requestContact);
    }

    public function test_can_mark_as_contacted_from_table(): void
    {
        $requestContact = RequestContact::factory()->create([
            'is_contacted' => false,
            'contacted_at' => null,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListRequestContacts::class)
            ->callTableAction('markAsContacted', $requestContact);

        $requestContact->refresh();

        $this->assertTrue($requestContact->is_contacted);
        $this->assertNotNull($requestContact->contacted_at);
    }

    public function test_can_mark_as_contacted_with_notes(): void
    {
        $requestContact = RequestContact::factory()->create([
            'is_contacted' => false,
            'contacted_at' => null,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListRequestContacts::class)
            ->callTableAction('markAsContacted', $requestContact, data: [
                'admin_notes' => 'Called and discussed requirements',
            ]);

        $requestContact->refresh();

        $this->assertTrue($requestContact->is_contacted);
        $this->assertNotNull($requestContact->contacted_at);
        $this->assertEquals('Called and discussed requirements', $requestContact->admin_notes);
    }

    public function test_can_mark_as_contacted_from_view_page(): void
    {
        $requestContact = RequestContact::factory()->create([
            'is_contacted' => false,
            'contacted_at' => null,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ViewRequestContact::class, [
                'record' => $requestContact->getRouteKey(),
            ])
            ->callAction('markAsContacted', data: [
                'admin_notes' => 'Contacted via email',
            ]);

        $requestContact->refresh();

        $this->assertTrue($requestContact->is_contacted);
        $this->assertNotNull($requestContact->contacted_at);
        $this->assertEquals('Contacted via email', $requestContact->admin_notes);
    }

    public function test_can_bulk_mark_as_contacted(): void
    {
        $requestContacts = RequestContact::factory()->count(3)->create([
            'is_contacted' => false,
            'contacted_at' => null,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListRequestContacts::class)
            ->callTableBulkAction('markAsContacted', $requestContacts);

        foreach ($requestContacts as $requestContact) {
            $requestContact->refresh();
            $this->assertTrue($requestContact->is_contacted);
            $this->assertNotNull($requestContact->contacted_at);
        }
    }

    public function test_mark_contacted_action_hidden_when_already_contacted(): void
    {
        $requestContact = RequestContact::factory()->create([
            'is_contacted' => true,
            'contacted_at' => now(),
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ViewRequestContact::class, [
                'record' => $requestContact->getRouteKey(),
            ])
            ->assertActionHidden('markAsContacted');
    }

    public function test_required_fields_validation(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(CreateRequestContact::class)
            ->fillForm([
                'name' => '',
                'email' => '',
            ])
            ->call('create')
            ->assertHasErrors(['name', 'email']);
    }

    public function test_email_validation(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(CreateRequestContact::class)
            ->fillForm([
                'name' => 'Test Name',
                'email' => 'invalid-email',
            ])
            ->call('create')
            ->assertHasErrors(['email']);
    }

    public function test_tabs_display_correct_counts(): void
    {
        RequestContact::factory()->count(2)->create([
            'is_contacted' => false,
        ]);

        RequestContact::factory()->count(3)->create([
            'is_contacted' => true,
        ]);

        RequestContact::factory()->count(1)->create([
            'created_at' => now()->subDays(40),
        ]);

        $component = Livewire::actingAs($this->superAdmin)
            ->test(ListRequestContacts::class);

        $tabs = $component->instance()->getTabs();

        $this->assertEquals(2, $tabs['uncontacted']->getBadge());
        $this->assertEquals(3, $tabs['contacted']->getBadge());
        $this->assertEquals(5, $tabs['recent']->getBadge());
    }

    public function test_navigation_badge_shows_uncontacted_count(): void
    {
        RequestContact::factory()->count(5)->create([
            'is_contacted' => false,
        ]);

        RequestContact::factory()->count(3)->create([
            'is_contacted' => true,
        ]);

        $badge = RequestContactResource::getNavigationBadge();

        $this->assertEquals('5', $badge);
    }

    public function test_navigation_badge_color_is_warning_when_uncontacted(): void
    {
        RequestContact::factory()->create([
            'is_contacted' => false,
        ]);

        $color = RequestContactResource::getNavigationBadgeColor();

        $this->assertEquals('warning', $color);
    }

    public function test_navigation_badge_hidden_when_all_contacted(): void
    {
        RequestContact::factory()->count(3)->create([
            'is_contacted' => true,
        ]);

        $badge = RequestContactResource::getNavigationBadge();

        $this->assertNull($badge);
    }
}
