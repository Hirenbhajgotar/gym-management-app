<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Livewire\Frontend\HomeController;
use App\Models\Plan;
use App\Models\RequestContact;
use App\Observers\PlanObserver;
use App\Services\ErrorReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Request::setTrustedProxies(
            ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
        );
        RateLimiter::clear('request-callback:127.0.0.1');
        RateLimiter::clear('request-callback:203.0.113.195');
        RateLimiter::clear('request-callback:198.51.100.42');
        Cache::forget(Plan::ALL_PLANS_FOR_HOME_CACHE_KEY);
        Cache::forget(Plan::ALL_ENTERPRISE_PLANS_FOR_HOME_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('request-callback:127.0.0.1');
        RateLimiter::clear('request-callback:203.0.113.195');
        RateLimiter::clear('request-callback:198.51.100.42');
        Cache::forget(Plan::ALL_PLANS_FOR_HOME_CACHE_KEY);
        Cache::forget(Plan::ALL_ENTERPRISE_PLANS_FOR_HOME_CACHE_KEY);
        parent::tearDown();
    }

    #[Test]
    public function home_page_renders_successfully(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('FluxFitness');
        $response->assertSeeHtml('id="features"');
        $response->assertSeeHtml('id="solutions"');
        $response->assertSeeHtml('id="pricing"');
        $response->assertSeeHtml('id="faq"');
        $response->assertSeeHtml('id="contact"');
    }

    #[Test]
    public function user_can_submit_callback_request_successfully(): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        $component
            ->set('callbackName', 'Rahul Sharma')
            ->set('callbackPhone', '+919820012345')
            ->set('callbackGym', 'Powerhouse Gym')
            ->call('requestCallback');

        $component->assertHasNoErrors();
        $component->assertSet('callbackSuccess', true);
        $component->assertSet('callbackName', '');
        $component->assertSet('callbackPhone', '');
        $component->assertSet('callbackGym', '');

        $this->assertDatabaseHas('request_contacts', [
            'name' => 'Rahul Sharma',
            'phone' => '+919820012345',
            'company_name' => 'Powerhouse Gym',
        ]);
    }

    #[Test]
    public function user_can_submit_callback_request_with_national_phone_number_and_read_back_without_error(): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        $component
            ->set('callbackName', 'Kunal Verma')
            ->set('callbackPhone', '9820012345')
            ->set('callbackGym', 'Gold Gym')
            ->call('requestCallback');

        $component->assertHasNoErrors();
        $component->assertSet('callbackSuccess', true);

        $contact = RequestContact::query()->where('name', 'Kunal Verma')->firstOrFail();
        $this->assertSame('+919820012345', (string) $contact->phone);
    }

    #[Test]
    #[DataProvider('invalidPhoneDataProvider')]
    public function callback_request_rejects_invalid_phone_numbers(string $invalidPhone): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        $component
            ->set('callbackPhone', $invalidPhone)
            ->call('requestCallback');

        $component->assertHasErrors(['callbackPhone']);
        $component->assertSet('callbackSuccess', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPhoneDataProvider(): array
    {
        return [
            'short number' => ['123'],
            'empty string' => [''],
            'invalid non-region UK number' => ['+442071838750'],
            'alphanumeric string' => ['not-a-phone'],
        ];
    }

    #[Test]
    public function callback_request_throttles_after_three_attempts(): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        // 3 allowed attempts
        for ($i = 1; $i <= 3; $i++) {
            $component
                ->set('callbackPhone', "+91982001000{$i}")
                ->call('requestCallback');

            $component->assertHasNoErrors();
            $component->assertSet('callbackSuccess', true);

            $component->call('resetCallback');
        }

        // 4th attempt blocked by rate limiter
        $component
            ->set('callbackPhone', '+919820010004')
            ->call('requestCallback');

        $component->assertHasErrors(['callbackPhone']);
        $component->assertSet('callbackSuccess', false);
    }

    #[Test]
    public function user_can_reset_callback_state(): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        $component
            ->set('callbackName', 'Test Name')
            ->set('callbackPhone', '+919820012345')
            ->set('callbackGym', 'Test Gym')
            ->set('callbackSuccess', true)
            ->call('resetCallback');

        $component->assertSet('callbackSuccess', false);
        $component->assertSet('callbackName', '');
        $component->assertSet('callbackPhone', '');
        $component->assertSet('callbackGym', '');
    }

    #[Test]
    public function start_free_trial_redirects_to_registration(): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        $component
            ->call('startFreeTrial')
            ->assertRedirect(route('gym.register'));
    }

    #[Test]
    public function selected_plan_is_null_and_locked_against_client_updates(): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);
        $component->assertSet('selectedPlan', null);

        // selectedPlan is never set by legitimate client code (the view uses
        // wire:click="selectPlan($planId)"), so a client-supplied update must
        // be rejected. This mirrors the #[Locked] hardening on the Toast component.
        try {
            /** @var Testable $malicious */
            $malicious = Livewire::test(HomeController::class);
            $malicious->set('selectedPlan', 5);
            $this->fail('Expected CannotUpdateLockedPropertyException was not thrown.');
        } catch (CannotUpdateLockedPropertyException $e) {
            $this->assertStringContainsString('selectedPlan', $e->getMessage());
        }
    }

    #[Test]
    public function it_skips_auto_reporting_for_corrupt_livewire_payloads(): void
    {
        // Tampered/corrupted Livewire payloads are attacker probes, not app bugs,
        // so they must not create error reports (or page on-call).
        /** @var ErrorReportService $service */
        $service = resolve(ErrorReportService::class);

        $this->assertTrue($service->shouldSkipAutoReporting(new CorruptComponentPayloadException()));
    }

    #[Test]
    public function it_skips_auto_reporting_for_unknown_livewire_method_calls(): void
    {
        // Calls to non-existent Livewire component methods (e.g. the legacy
        // Livewire v2 "emit" method) are attacker probes, not app bugs, so
        // they must not create error reports (or page on-call).
        /** @var ErrorReportService $service */
        $service = resolve(ErrorReportService::class);

        $this->assertTrue($service->shouldSkipAutoReporting(new MethodNotFoundException('emit')));
    }

    #[Test]
    public function it_rejects_an_unknown_livewire_method_call_with_a_400_instead_of_a_500(): void
    {
        // Mirrors the production probe: a hand-crafted /livewire/update POST that
        // calls the legacy Livewire v2 "emit" method, which does not exist in v3.
        // Livewire throws MethodNotFoundException; we must answer 400 (no 500,
        // no stack trace, no error report).
        $snapshot = [
            'data' => ['selectedPlan' => null],
            'memo' => [
                'id' => 'test-component-id',
                'name' => 'frontend.home-controller',
                'path' => '/',
                'method' => 'GET',
                'release' => ReleaseToken::generate(HomeController::class),
                'children' => [],
                'scripts' => [],
                'assets' => [],
                'errors' => [],
                'locale' => 'en',
            ],
        ];
        $snapshot['checksum'] = Checksum::generate($snapshot);

        $token = 'test-csrf-token';

        $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->withHeader('X-Livewire', 'true')
            ->postJson('/livewire/update', [
                'components' => [
                    [
                        'snapshot' => json_encode($snapshot),
                        'updates' => [],
                        'calls' => [
                            [
                                'path' => null,
                                'method' => 'emit',
                                'params' => ['system', 'id'],
                            ],
                        ],
                    ],
                ],
                '_token' => $token,
            ])
            ->assertStatus(400);
    }

    #[Test]
    public function it_rejects_a_tampered_livewire_payload_with_a_400_instead_of_a_500(): void
    {
        // Mirrors the production attack: a hand-crafted /livewire/update POST whose
        // snapshot checksum cannot be recomputed without the app key. Livewire's
        // checksum verification must reject it, and our handler must respond 400
        // (not a 500 with a stack trace / error report).
        $snapshot = [
            'data' => ['selectedPlan' => ['malicious' => 'object']],
            'memo' => ['name' => 'frontend.home-controller', 'id' => 'abc123'],
            'checksum' => 'tampered-checksum-value',
        ];

        // The production attack supplied a valid CSRF token, so CSRF passed and
        // the request reached Livewire. Mirror that here with a valid token so we
        // exercise Livewire's checksum verification + our 400 handler. The
        // `release` value must match the current deployment or Livewire short-
        // circuits with its own 419 ("release token mismatch") before reaching
        // the checksum check.
        $token = 'test-csrf-token';
        $snapshot['memo']['release'] = ReleaseToken::generate(HomeController::class);

        $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->withHeader('X-Livewire', 'true')
            ->postJson('/livewire/update', [
                'components' => [
                    [
                        'snapshot' => json_encode($snapshot),
                        'updates' => [],
                        'calls' => [],
                    ],
                ],
                '_token' => $token,
            ])
            ->assertStatus(400);
    }

    #[Test]
    public function rate_limiter_ordering_and_isolation(): void
    {
        // 1. Rate Limiter Ordering: Validation errors must NOT consume attempts from the rate limiter budget
        RateLimiter::clear('request-callback:127.0.0.1');

        /** @var Testable $invalidAttemptComponent */
        $invalidAttemptComponent = Livewire::test(HomeController::class);

        // Submit 5 invalid requests (bad phone number)
        for ($i = 0; $i < 5; $i++) {
            $invalidAttemptComponent
                ->set('callbackPhone', 'not-a-valid-phone')
                ->call('requestCallback');

            $invalidAttemptComponent->assertHasErrors(['callbackPhone']);
            $invalidAttemptComponent->assertSet('callbackSuccess', false);
        }

        // The budget should still be untouched (0 attempts consumed) because validation failed before hit()
        $this->assertSame(0, RateLimiter::attempts('request-callback:127.0.0.1'));

        // 2. Perform 3 valid requests
        for ($i = 1; $i <= 3; $i++) {
            $invalidAttemptComponent
                ->set('callbackPhone', "+91982001000{$i}")
                ->call('requestCallback');

            $invalidAttemptComponent->assertHasNoErrors();
            $invalidAttemptComponent->assertSet('callbackSuccess', true);

            $invalidAttemptComponent->call('resetCallback');
        }

        // 4th attempt is blocked
        $invalidAttemptComponent
            ->set('callbackPhone', '+919820010004')
            ->call('requestCallback');

        $invalidAttemptComponent->assertHasErrors(['callbackPhone']);
        $invalidAttemptComponent->assertSet('callbackSuccess', false);
        $this->assertSame(3, RateLimiter::attempts('request-callback:127.0.0.1'));

        // 3. Rate Limiter Isolation: When rate limiter budget is cleared, requests succeed independently
        RateLimiter::clear('request-callback:127.0.0.1');

        /** @var Testable $isolatedClientComponent */
        $isolatedClientComponent = Livewire::test(HomeController::class);

        $isolatedClientComponent
            ->set('callbackPhone', '+919820010005')
            ->call('requestCallback');

        $isolatedClientComponent->assertHasNoErrors();
        $isolatedClientComponent->assertSet('callbackSuccess', true);

        $this->assertSame(1, RateLimiter::attempts('request-callback:127.0.0.1'));

        $this->assertDatabaseHas('request_contacts', [
            'phone' => '+919820010005',
        ]);
    }

    #[Test]
    public function select_plan_stores_selected_plan_in_session_and_redirects(): void
    {
        $plan = Plan::factory()->create([
            'name' => 'Pro Yearly',
            'is_active' => true,
        ]);

        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        $component
            ->call('selectPlan', $plan->id)
            ->assertSessionHas('selected_plan_id', $plan->id)
            ->assertRedirect(route('gym.register'));
    }

    #[Test]
    public function select_plan_fails_for_inactive_plan(): void
    {
        $inactivePlan = Plan::factory()->create([
            'name' => 'Deprecated Plan',
            'is_active' => false,
        ]);

        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);
        $component->call('selectPlan', $inactivePlan->id)
            ->assertHasErrors(['selectedPlan']);
    }

    #[Test]
    public function invalid_submissions_do_not_persist_records(): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        $component
            ->set('callbackName', 'Bad Request')
            ->set('callbackPhone', 'invalid-phone')
            ->call('requestCallback');

        $component->assertHasErrors(['callbackPhone']);
        $this->assertDatabaseCount('request_contacts', 0);
    }

    #[Test]
    public function start_free_trial_redirects_with_free_plan_in_session(): void
    {
        $freePlan = Plan::factory()->create([
            'name' => 'Starter Free',
            'price_monthly' => 0,
            'is_active' => true,
        ]);

        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        $component
            ->call('startFreeTrial')
            ->assertSessionHas('selected_plan_id', $freePlan->id)
            ->assertRedirect(route('gym.register'));
    }

    #[Test]
    public function plans_and_enterprise_plans_use_deterministic_computed_cache_keys(): void
    {
        $standardPlan = Plan::factory()->create([
            'name' => 'Standard Plan',
            'is_active' => true,
            'is_enterprise' => false,
        ]);

        $enterprisePlan = Plan::factory()->create([
            'name' => 'Enterprise Plan',
            'is_active' => true,
            'is_enterprise' => true,
        ]);

        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);

        /** @var Collection<int, Plan> $plans */
        $plans = $component->get('plans');
        /** @var Collection<int, Plan> $enterprisePlans */
        $enterprisePlans = $component->get('enterprisePlans');

        $this->assertTrue($plans->contains('id', $standardPlan->id));
        $this->assertTrue($enterprisePlans->contains('id', $enterprisePlan->id));

        $this->assertTrue(Cache::has(Plan::ALL_PLANS_FOR_HOME_CACHE_KEY));
        $this->assertTrue(Cache::has(Plan::ALL_ENTERPRISE_PLANS_FOR_HOME_CACHE_KEY));
    }

    #[Test]
    public function plan_feature_updates_touch_parent_plan_and_bust_cache(): void
    {
        $plan = Plan::factory()->create([
            'name' => 'Cache Bust Test Plan',
            'is_active' => true,
        ]);

        $feature = $plan->features()->create([
            'feature_name' => 'Original Feature',
        ]);

        $originalUpdatedAt = $plan->refresh()->updated_at;
        $this->assertNotNull($originalUpdatedAt);

        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);
        $component->get('plans');
        $this->assertTrue(Cache::has(Plan::ALL_PLANS_FOR_HOME_CACHE_KEY));

        $this->travel(5)->seconds();

        // Update feature touches parent Plan (PlanFeature::$touches = ['plan'])
        $feature->update(['feature_name' => 'Updated Feature']);

        $plan->refresh();
        $this->assertNotNull($plan->updated_at);
        $this->assertTrue($plan->updated_at->gt($originalUpdatedAt));

        // PlanObserver clears both home plan cache keys on update
        resolve(PlanObserver::class)->updated($plan);

        $this->assertFalse(Cache::has(Plan::ALL_PLANS_FOR_HOME_CACHE_KEY));
        $this->assertFalse(Cache::has(Plan::ALL_ENTERPRISE_PLANS_FOR_HOME_CACHE_KEY));
    }
}
