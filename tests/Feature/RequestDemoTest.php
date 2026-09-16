<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Livewire\Frontend\RequestDemo;
use App\Models\Gym;
use App\Models\MemberPayment;
use App\Models\RequestContact;
use App\Notifications\RequestDemoNotification;
use App\Rules\ValidEmailDomain;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

#[CoversClass(RequestDemo::class)]
#[UsesClass(RequestContact::class)]
#[UsesClass(ValidEmailDomain::class)]
#[UsesClass(RequestDemoNotification::class)]
#[UsesClass(Gym::class)]
#[UsesClass(MemberPayment::class)]
final class RequestDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ValidEmailDomain::$resolver = fn (string $domain): bool => true;
        $this->clearRateLimiterKeys();
    }

    protected function tearDown(): void
    {
        ValidEmailDomain::$resolver = null;
        $this->clearRateLimiterKeys();

        parent::tearDown();
    }

    protected function clearRateLimiterKeys(): void
    {
        RateLimiter::clear(RequestDemo::rateLimitKeyFor('127.0.0.1'));
        RateLimiter::clear('request-demo:127.0.0.1');
    }

    #[Test]
    public function it_can_render_request_demo_page(): void
    {
        $response = $this->get(route('request-demo'));

        $response->assertOk();

        Livewire::test(RequestDemo::class)
            ->assertOk();
    }

    #[Test]
    public function it_can_submit_demo_request_with_required_fields_only(): void
    {
        $test = Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest');

        $test->assertHasNoErrors();
        $test->assertSet('isSubmitted', true);

        $this->assertDatabaseHas('request_contacts', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+14155552671',
        ]);
    }

    #[Test]
    public function it_can_submit_demo_request_with_all_fields(): void
    {
        $test = Livewire::test(RequestDemo::class)
            ->set('name', 'Jane Smith')
            ->set('email', 'jane@fitzone.com')
            ->set('phone', '+919876543210')
            ->set('company_name', 'FitZone Gym')
            ->set('gym_size', '500')
            ->set(
                'message',
                'Looking for a comprehensive gym management solution.',
            )
            ->call('submitRequest');

        $test->assertHasNoErrors();
        $test->assertSet('isSubmitted', true);

        $this->assertDatabaseHas('request_contacts', [
            'name' => 'Jane Smith',
            'email' => 'jane@fitzone.com',
            'phone' => '+919876543210',
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
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertHasErrors(['name' => 'required']);
    }

    #[Test]
    public function it_requires_email_field(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('phone', '+14155552671')
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
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertHasErrors(['email' => 'email']);
    }

    #[Test]
    public function it_validates_email_domain_dns_rejection_path(): void
    {
        ValidEmailDomain::$resolver = fn (string $domain): bool => false;

        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@nonexistent-domain-12345.xyz')
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertHasErrors(['email']);
    }

    #[Test]
    public function it_allows_email_when_dns_check_resolves_valid(): void
    {
        ValidEmailDomain::$resolver = fn (string $domain): bool => true;

        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@validcompany.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertHasNoErrors();
    }

    #[Test]
    public function it_skips_dns_validation_when_disabled_in_config(): void
    {
        Config::set('request-demo.dns_check_enabled', false);
        ValidEmailDomain::$resolver = fn (string $domain): bool => false;

        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@unresolvable-domain.test')
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertHasNoErrors();
    }

    #[Test]
    public function it_validates_name_minimum_length(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'J')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertHasErrors(['name' => 'min']);
    }

    #[Test]
    public function it_validates_phone_minimum_length(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+1234')
            ->call('submitRequest')
            ->assertHasErrors(['phone' => 'min']);
    }

    #[Test]
    #[TestWith(['+447911123456', true])] // UK number is rejected because default allowlist is US, IN, PK
    #[TestWith(['+923001234567', false])] // PK number is accepted
    #[TestWith(['+14155552671', false])] // US number is accepted
    #[TestWith(['+919876543210', false])] // IN number is accepted
    public function it_validates_phone_country_code(string $phone, bool $expectError): void
    {
        $test = Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', $phone)
            ->call('submitRequest');

        if ($expectError) {
            $test->assertHasErrors(['phone']);
        } else {
            $test->assertHasNoErrors();
        }
    }

    #[Test]
    public function it_validates_gym_size_is_integer(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->set('gym_size', 'not-a-number')
            ->call('submitRequest')
            ->assertHasErrors(['gym_size' => 'integer']);
    }

    #[Test]
    #[TestWith(['0', 'min'])]
    #[TestWith(['100001', 'max'])]
    public function it_rejects_gym_size_outside_boundary_limits(string $gymSize, string $expectedRule): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->set('gym_size', $gymSize)
            ->call('submitRequest')
            ->assertHasErrors(['gym_size' => $expectedRule]);
    }

    #[Test]
    #[TestWith(['1'])]
    #[TestWith(['100000'])]
    public function it_accepts_gym_size_at_boundary_limits(string $gymSize): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->set('gym_size', $gymSize)
            ->call('submitRequest')
            ->assertHasNoErrors();
    }

    #[Test]
    public function it_validates_string_length_boundaries(): void
    {
        // 255 chars is accepted
        Livewire::test(RequestDemo::class)
            ->set('name', str_repeat('a', 255))
            ->set('email', str_repeat('a', 243) . '@example.com')
            ->set('phone', '+14155552671')
            ->set('company_name', str_repeat('b', 255))
            ->call('submitRequest')
            ->assertHasNoErrors();

        // 256 chars is rejected
        Livewire::test(RequestDemo::class)
            ->set('name', str_repeat('a', 256))
            ->set('company_name', str_repeat('b', 256))
            ->call('submitRequest')
            ->assertHasErrors(['name' => 'max', 'company_name' => 'max']);
    }

    #[Test]
    public function it_validates_message_maximum_length(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->set('message', str_repeat('a', 1001))
            ->call('submitRequest')
            ->assertHasErrors(['message' => 'max']);
    }

    #[Test]
    public function it_stores_ip_address_and_truncates_user_agent(): void
    {
        Livewire::test(RequestDemo::class)
            ->set('name', 'Agent Tester')
            ->set('email', 'agent@example.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertHasNoErrors();

        $requestContact = RequestContact::query()->where('email', 'agent@example.com')->first();

        $this->assertNotNull($requestContact);
        $this->assertNotNull($requestContact->ip_address);
        $this->assertNotNull($requestContact->user_agent);
        $this->assertNotEmpty($requestContact->user_agent);
        $this->assertLessThanOrEqual(255, strlen($requestContact->user_agent));
    }

    #[Test]
    public function it_resets_form_after_successful_submission(): void
    {
        $test = Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->set('message', 'Test message')
            ->call('submitRequest');

        $test->assertSet('name', '');
        $test->assertSet('email', '');
        $test->assertSet('phone', '');
        $test->assertSet('message', '');
        $test->assertSet('isSubmitted', true);
        $test->assertDispatched('show-success');
        $test->assertSee('Demo Request Received!');
        $test->assertSee('Submit Another Request');
    }

    #[Test]
    public function it_can_reset_form_to_submit_another_request(): void
    {
        $test = Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest');

        $test->assertSet('isSubmitted', true);
        $test->call('resetForm');
        $test->assertSet('isSubmitted', false);
        $test->assertSet('name', '');
        $test->assertHasNoErrors();
    }

    #[Test]
    public function it_enforces_rate_limiting_and_cannot_be_bypassed_by_rotating_user_agent(): void
    {
        // Submit 3 requests with different User-Agent headers from same IP
        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['HTTP_USER_AGENT' => "RotatedUA-{$i}"]);

            Livewire::test(RequestDemo::class)
                ->set('name', "John Doe {$i}")
                ->set('email', "john{$i}@example.com")
                ->set('phone', "+1415555267{$i}")
                ->call('submitRequest')
                ->assertHasNoErrors();
        }

        // 4th request with yet another User-Agent should STILL be blocked
        $this->withServerVariables(['HTTP_USER_AGENT' => 'BrandNewUserAgentString']);

        $blocked = Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe 4')
            ->set('email', 'john4@example.com')
            ->set('phone', '+14155552674')
            ->call('submitRequest');

        $blocked->assertHasErrors(['rate_limit']);
        /** @var \Illuminate\Support\MessageBag $errors */
        $errors = $blocked->errors();
        $this->assertStringContainsString('Too many requests. Please try again in', $errors->first('rate_limit'));

        // Verify only 3 requests were stored
        $this->assertCount(3, RequestContact::all());
    }

    #[Test]
    public function it_deduplicates_rapid_identical_submissions_via_idempotency_guard(): void
    {
        $test1 = Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest');

        $test1->assertHasNoErrors();
        $test1->assertSet('isSubmitted', true);

        // Immediate duplicate submission with same email/phone
        $test2 = Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest');

        $test2->assertHasNoErrors();
        $test2->assertSet('isSubmitted', true);

        // Only 1 record created in database
        $this->assertCount(1, RequestContact::query()->where('email', 'john@example.com')->get());
    }

    #[Test]
    public function it_normalizes_phone_numbers_to_e164_and_renders_queued_notification(): void
    {
        $test = Livewire::test(RequestDemo::class)
            ->set('name', 'Hiren Patel')
            ->set('email', 'hiren@example.com')
            ->set('phone', '+919876543210')
            ->call('submitRequest');

        $test->assertHasNoErrors();

        $contact = RequestContact::query()->where('email', 'hiren@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('+919876543210', (string) $contact->phone);

        // Render mail directly to assert no cast or formatting crash
        $notification = new RequestDemoNotification($contact);
        $mail = $notification->toMail(new AnonymousNotifiable());

        $this->assertSame('New Demo Request - Hiren Patel', $mail->subject);
        $this->assertContains('• **Phone:** +919876543210', $mail->introLines);
    }

    #[Test]
    public function it_sanitizes_crlf_in_name_for_notification_subject(): void
    {
        $contact = RequestContact::factory()->create([
            'name' => "John\r\nDoe Injection",
            'email' => 'injection@example.com',
            'phone' => '+14155552671',
        ]);

        $notification = new RequestDemoNotification($contact);
        $mail = $notification->toMail(new AnonymousNotifiable());

        $this->assertSame('New Demo Request - JohnDoe Injection', $mail->subject);
    }

    #[Test]
    public function it_prioritizes_force_contact_email_over_default_contact_email(): void
    {
        Notification::fake();
        Config::set('app.contact_email', 'default-admin@fluxfitness.com');
        Config::set('request-demo.force_contact_email', 'override-admin@fluxfitness.com');

        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertHasNoErrors();

        Notification::assertSentOnDemand(
            RequestDemoNotification::class,
            fn (RequestDemoNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'override-admin@fluxfitness.com'
        );
    }

    #[Test]
    public function it_renders_gracefully_with_empty_or_missing_config(): void
    {
        Config::set('request-demo.testimonials', []);
        Config::set('request-demo.faqs', []);
        Config::set('app.contact_phone');
        Config::set('app.contact_whatsapp');

        $component = Livewire::test(RequestDemo::class);
        $component->assertOk();
        $this->assertSame('#', $component->get('whatsAppUrl'));

        $response = $this->get(route('request-demo'));
        $response->assertOk();
        $response->assertSee('No FAQs available at the moment');
    }

    #[Test]
    public function it_prunes_old_records_using_scope_and_prunable(): void
    {
        $recent = RequestContact::factory()->create(['created_at' => now()->subDays(10)]);
        $old = RequestContact::factory()->create(['created_at' => now()->subDays(200)]);

        $older = RequestContact::query()->olderThanDays(180)->get();

        $this->assertFalse($older->contains('id', $recent->id));
        $this->assertTrue($older->contains('id', $old->id));

        $prunable = (new RequestContact())->prunable()->get();
        $this->assertTrue($prunable->contains('id', $old->id));
        $this->assertFalse($prunable->contains('id', $recent->id));
    }

    #[Test]
    public function it_handles_generic_exception_and_dispatches_error(): void
    {
        RequestContact::creating(function (): void {
            throw new Exception('Simulated database failure');
        });

        Livewire::test(RequestDemo::class)
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+14155552671')
            ->call('submitRequest')
            ->assertDispatched('show-error', message: 'Something went wrong. Please try again later.');
    }

    #[Test]
    public function request_contact_can_be_marked_as_contacted(): void
    {
        $requestContact = RequestContact::factory()->uncontacted()->create();

        $this->assertFalse($requestContact->is_contacted);
        $this->assertNull($requestContact->contacted_at);

        $requestContact->markAsContacted('Called and scheduled demo');

        $fresh = $requestContact->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->is_contacted);
        $this->assertNotNull($fresh->contacted_at);
        $this->assertSame(
            'Called and scheduled demo',
            $fresh->admin_notes,
        );
    }

    #[Test]
    public function it_generates_whatsapp_url(): void
    {
        Config::set('app.contact_whatsapp', '919265098227');
        Config::set('request-demo.whatsapp_message', 'Hello Support');

        $component = Livewire::test(RequestDemo::class);

        $this->assertSame(
            'https://wa.me/919265098227?text=Hello%20Support',
            $component->get('whatsAppUrl'),
        );
    }

    #[Test]
    public function it_prunes_old_records_using_model_prune_artisan_command(): void
    {
        $recent = RequestContact::factory()->create(['created_at' => now()->subDays(10)]);
        $old = RequestContact::factory()->create(['created_at' => now()->subDays(200)]);

        // $this->artisan('model:prune', ['--model' => [RequestContact::class]])
        //     ->assertSuccessful();
        $this->runCommand('model:prune', ['--model' => [RequestContact::class]])->assertSuccessful();

        $this->assertDatabaseHas('request_contacts', ['id' => $recent->id]);
        $this->assertDatabaseMissing('request_contacts', ['id' => $old->id]);
    }

    #[Test]
    public function it_returns_default_metrics_when_gyms_count_is_below_or_equal_to_threshold(): void
    {
        Cache::flush();
        Config::set('request-demo.metrics.min_tenants_threshold', 47);
        Config::set('request-demo.metrics.active_gyms_count', '47+');
        Config::set('request-demo.metrics.monthly_dues_processed', '₹42.3 Lakh+');

        $component = Livewire::test(RequestDemo::class);

        $this->assertSame('47+', $component->get('activeGymsCount'));
        $this->assertSame('₹42.3 Lakh+', $component->get('monthlyDuesProcessed'));
    }

    #[Test]
    public function it_returns_dynamic_queried_metrics_when_gyms_count_exceeds_threshold(): void
    {
        Cache::flush();
        Config::set('request-demo.metrics.min_tenants_threshold', 2);
        Config::set('request-demo.metrics.active_gyms_count', '47+');
        Config::set('request-demo.metrics.monthly_dues_processed', '₹42.3 Lakh+');

        // Create 3 gyms (exceeds threshold 2)
        $gyms = Gym::factory()->count(3)->create();
        $firstGym = $gyms->first();
        $this->assertInstanceOf(Gym::class, $firstGym);

        MemberPayment::factory()->create([
            'gym_id' => $firstGym->id,
            'amount' => 500000, // 5 Lakh
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
        ]);

        $component = Livewire::test(RequestDemo::class);

        $this->assertSame('3+', $component->get('activeGymsCount'));
        $this->assertSame('₹5 Lakh+', $component->get('monthlyDuesProcessed'));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function runCommand(string $command, array $args = []): PendingCommand
    {
        $result = $this->artisan($command, $args);
        assert($result instanceof PendingCommand);

        return $result;
    }
}
