<?php

namespace Tests\Feature;

use App\Livewire\Frontend\RequestDemo;
use App\Models\RequestContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequestDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear rate limiter before each test
        RateLimiter::clear('request-demo:127.0.0.1');
    }

    #[Test]
    public function it_can_render_request_demo_page(): void
    {
        $response = $this->get(route('request-demo'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(RequestDemo::class);
    }

    #[Test]
    public function it_can_submit_demo_request_with_required_fields_only(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234567890')
            ->set('preferred_contact_method', 'email')
            ->call('submitRequest')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('request_contacts', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'preferred_contact_method' => 'email',
        ]);
    }

    #[Test]
    public function it_can_submit_demo_request_with_all_fields(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'Jane Smith')
            ->set('email', 'jane@fitzone.com')
            ->set('phone', '+1987654321')
            ->set('company_name', 'FitZone Gym')
            ->set('gym_size', 500)
            ->set(
                'message',
                'Looking for a comprehensive gym management solution.',
            )
            ->call('submitRequest')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('request_contacts', [
            'name' => 'Jane Smith',
            'email' => 'jane@fitzone.com',
            'phone' => '+1987654321',
            'company_name' => 'FitZone Gym',
            'gym_size' => 500,
            'message' => 'Looking for a comprehensive gym management solution.',
        ]);
    }

    #[Test]
    public function it_requires_name_field(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('email', 'test@example.com')
            ->set('phone', '+1234567890')
            ->call('submitRequest')
            ->assertHasErrors(['name' => 'required']);
    }

    #[Test]
    public function it_requires_email_field(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('phone', '+1234567890')
            ->call('submitRequest')
            ->assertHasErrors(['email' => 'required']);
    }

    #[Test]
    public function it_requires_phone_field(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->call('submitRequest')
            ->assertHasErrors(['phone' => 'required']);
    }

    #[Test]
    public function it_validates_email_format(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'invalid-email')
            ->set('phone', '+1234567890')
            ->call('submitRequest')
            ->assertHasErrors(['email' => 'email']);
    }

    #[Test]
    public function it_validates_name_minimum_length(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'J')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234567890')
            ->call('submitRequest')
            ->assertHasErrors(['name' => 'min']);
    }

    #[Test]
    public function it_validates_phone_minimum_length(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '123')
            ->call('submitRequest')
            ->assertHasErrors(['phone' => 'min']);
    }

    #[Test]
    public function it_validates_gym_size_is_integer(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234567890')
            ->set('gym_size', 'not-a-number')
            ->call('submitRequest')
            ->assertHasErrors(['gym_size' => 'integer']);
    }

    #[Test]
    public function it_validates_message_maximum_length(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234567890')
            ->set('message', str_repeat('a', 1001))
            ->call('submitRequest')
            ->assertHasErrors(['message' => 'max']);
    }

    #[Test]
    public function it_validates_preferred_contact_method_values(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234567890')
            ->set('preferred_contact_method', 'invalid-method')
            ->call('submitRequest')
            ->assertHasErrors(['preferred_contact_method' => 'in']);
    }

    #[Test]
    public function it_stores_ip_address_and_user_agent(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234567890')
            ->call('submitRequest');

        $requestContact = RequestContact::first();

        $this->assertNotNull($requestContact->ip_address);
        $this->assertNotNull($requestContact->user_agent);
    }

    #[Test]
    public function it_resets_form_after_successful_submission(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234567890')
            ->set('message', 'Test message')
            ->call('submitRequest')
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('phone', '')
            ->assertSet('message', '')
            ->assertSet('preferred_contact_method', 'email')
            ->assertDispatched('show-success');
    }

    #[Test]
    public function it_enforces_rate_limiting(): void
    {
        $component = Livewire::test(RequestDemo::class);

        // Submit 3 requests successfully
        for ($i = 0; $i < 3; $i++) {
            $component
                ->set('name', "John Doe {$i}")
                ->set('email', "john{$i}@example.com")
                ->set('phone', "+123456789{$i}")
                ->call('submitRequest');
        }

        // 4th request should be rate limited
        $component
            ->set('name', 'John Doe 4')
            ->set('email', 'john4@example.com')
            ->set('phone', '+1234567894')
            ->call('submitRequest')
            ->assertDispatched('show-error');

        // Verify only 3 requests were stored
        $this->assertCount(3, RequestContact::all());
    }

    #[Test]
    public function it_dispatches_success_event_after_submission(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234567890')
            ->call('submitRequest')
            ->assertDispatched('show-success');
    }

    #[Test]
    public function it_dispatches_error_event_on_rate_limit(): void
    {
        $component = Livewire::test(RequestDemo::class);

        // Submit 3 requests
        for ($i = 0; $i < 3; $i++) {
            $component
                ->set('name', "John Doe {$i}")
                ->set('email', "john{$i}@example.com")
                ->set('phone', "+123456789{$i}")
                ->call('submitRequest');
        }

        // 4th request should dispatch error event
        $component
            ->set('name', 'John Doe 4')
            ->set('email', 'john4@example.com')
            ->set('phone', '+1234567894')
            ->call('submitRequest')
            ->assertDispatched('show-error');
    }

    #[Test]
    public function request_contact_can_be_marked_as_contacted(): void
    {
        $requestContact = RequestContact::factory()->uncontacted()->create();

        $this->assertFalse($requestContact->is_contacted);
        $this->assertNull($requestContact->contacted_at);

        $requestContact->markAsContacted('Called and scheduled demo');

        $this->assertTrue($requestContact->fresh()->is_contacted);
        $this->assertNotNull($requestContact->fresh()->contacted_at);
        $this->assertEquals(
            'Called and scheduled demo',
            $requestContact->fresh()->admin_notes,
        );
    }

    #[Test]
    public function it_displays_contact_information_on_page(): void
    {
        $response = $this->get(route('request-demo'));

        $response->assertSee('Request Your Demo');
        $response->assertSee('Full Name');
        $response->assertSee('Email Address');
        $response->assertSee('Phone Number');
        $response->assertSee('Preferred Contact Method');
    }

    #[Test]
    public function home_controller_can_redirect_to_request_demo(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(
            \App\Livewire\Frontend\HomeController::class,
        );
    }
}
