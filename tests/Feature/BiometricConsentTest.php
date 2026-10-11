<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricDeviceStatusEnum;
use App\Events\BiometricConsentGiven;
use App\Http\Controllers\BiometricConsentController;
use App\Http\Requests\BiometricConsentStoreRequest;
use App\Jobs\SendBiometricConsentNotificationJob;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\GymMember;
use App\Services\Biometric\BiometricConsentEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BiometricConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_consent_page_returns_200_with_valid_signed_url(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'test@example.com',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'biometric.consent.show',
            now()->addDays(7),
            [
                'gym_member_id' => $member->id,
                'gym_id' => $gym->id,
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertSee('Biometric Data Consent');
    }

    public function test_consent_page_returns_403_without_signed_url(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
        ]);

        $response = $this->get("/consent/biometric?gym_member_id={$member->id}&gym_id={$gym->id}");

        $response->assertForbidden();
    }

    public function test_consent_page_shows_already_consented_when_member_has_consent(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'test@example.com',
        ]);

        // Create existing consent
        BiometricConsent::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'consented_at' => now(),
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'biometric.consent.show',
            now()->addDays(7),
            [
                'gym_member_id' => $member->id,
                'gym_id' => $gym->id,
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertSee('Consent Already Recorded');
    }

    public function test_post_consent_creates_consent_record(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'test@example.com',
        ]);

        // Create form request instance and validate
        $formRequest = new BiometricConsentStoreRequest();
        $formRequest->merge([
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
            'consented' => '1',
        ]);

        // Set server variables for IP address
        $formRequest->server->set('REMOTE_ADDR', '127.0.0.1');
        $formRequest->setMethod('POST');

        // Resolve controller and call store with form request
        $controller = $this->app->make(BiometricConsentController::class);
        $controller->store($formRequest);

        $this->assertDatabaseHas('biometric_consents', [
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
            'version' => '1.0',
        ]);
    }

    public function test_post_consent_idempotent_when_already_consented(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'test@example.com',
        ]);

        // Create existing consent
        BiometricConsent::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'consented_at' => now()->subDay(),
        ]);

        // Create form request instance
        $formRequest = new BiometricConsentStoreRequest();
        $formRequest->merge([
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
            'consented' => '1',
        ]);

        // Resolve controller and call store with form request
        $controller = $this->app->make(BiometricConsentController::class);
        $controller->store($formRequest);

        // Should not create a new consent record
        $this->assertEquals(1, BiometricConsent::query()->where('gym_member_id', $member->id)->count());
    }

    public function test_consent_requires_checkbox(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'test@example.com',
        ]);

        // Test form request validation directly
        $formRequest = new BiometricConsentStoreRequest();
        $formRequest->merge([
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
            'consented' => '0', // Invalid - not '1' or 'on'
        ]);

        // The validation should fail for 'consented' field
        $validator = Validator::make(
            $formRequest->all(),
            $formRequest->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('consented', $validator->errors()->toArray());
    }

    public function test_member_creation_dispatches_consent_email_job(): void
    {
        $gym = Gym::factory()->create();

        BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        Queue::fake();

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'newmember@example.com',
        ]);

        // Verify job was dispatched
        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function ($job) use ($member) {
            return $job->gymMember->id === $member->id && $job->sendEmail === true;
        });
    }

    public function test_member_creation_skips_job_when_gym_has_no_active_biometric_device(): void
    {
        Queue::fake();

        $gym = Gym::factory()->create();

        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'nobiometrics@example.com',
        ]);

        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class);
    }

    public function test_member_creation_skips_job_when_no_email(): void
    {
        $gym = Gym::factory()->create();

        BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        Queue::fake();

        // Create member with empty string email (not null, since DB may require email)
        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => '',
        ]);

        // Verify job was NOT dispatched (empty email should skip)
        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class);
    }

    public function test_consent_given_event_fires(): void
    {
        Event::fake([BiometricConsentGiven::class]);

        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'test@example.com',
        ]);

        // Create form request instance
        $formRequest = new BiometricConsentStoreRequest();
        $formRequest->merge([
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
            'consented' => '1',
        ]);

        // Set server variables for IP address
        $formRequest->server->set('REMOTE_ADDR', '127.0.0.1');
        $formRequest->setMethod('POST');

        // Resolve controller and call store with form request
        $controller = $this->app->make(BiometricConsentController::class);
        $controller->store($formRequest);

        Event::assertDispatched(BiometricConsentGiven::class);
    }

    /**
     * A signed link stays valid for its whole TTL, which can outlive the
     * membership it was minted for. copy_consent_link has no eligibility
     * precondition, so an archived member can be handed one.
     */
    public function test_an_archived_member_cannot_open_or_grant_consent(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'archived@example.com',
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $showUrl = URL::temporarySignedRoute('biometric.consent.show', now()->addDays(7), [
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->get($showUrl)->assertGone();

        $storeUrl = URL::temporarySignedRoute('biometric.consent.store', now()->addDays(7), [
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->post($storeUrl, ['gym_member_id' => $member->id, 'consented' => 1])->assertGone();

        $this->assertDatabaseCount('biometric_consents', 0);
    }

    public function test_a_lapsed_membership_cannot_grant_consent(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'lapsed@example.com',
            'membership_end' => now()->subDay(),
        ]);

        $storeUrl = URL::temporarySignedRoute('biometric.consent.store', now()->addDays(7), [
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->post($storeUrl, ['gym_member_id' => $member->id, 'consented' => 1])->assertGone();

        $this->assertDatabaseCount('biometric_consents', 0);
    }

    /**
     * A member with no membership_end at all is refused by isEligible() — they have
     * no active membership by this application's definition — yet a link already in
     * their inbox is still honoured. That asymmetry is the entire reason the two
     * methods exist: refusing a link over missing data would strand a member
     * mid-flow who never had an end date configured, while still refusing one whose
     * membership genuinely lapsed.
     */
    public function test_a_member_with_no_membership_end_is_honoured_despite_never_being_eligible(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'no_end_date@example.com',
            'membership_end' => null,
            'is_archived' => false,
        ]);

        $this->assertFalse(
            BiometricConsentEligibility::isEligible($member),
            'isEligible() must keep refusing a member with no membership_end.'
        );

        $this->assertTrue(
            BiometricConsentEligibility::honoursExistingRequest($member),
            'An already-minted link must still be honoured for a member with no membership_end.'
        );

        $showUrl = URL::temporarySignedRoute('biometric.consent.show', now()->addDays(7), [
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->get($showUrl)->assertOk();

        $storeUrl = URL::temporarySignedRoute('biometric.consent.store', now()->addDays(7), [
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->post($storeUrl, ['gym_member_id' => $member->id, 'consented' => 1])->assertOk();

        $this->assertDatabaseCount('biometric_consents', 1);
    }

    public function test_honours_existing_request_refuses_an_archived_or_lapsed_member(): void
    {
        $gym = Gym::factory()->create();

        $archived = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $lapsed = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'is_archived' => false,
        ]);

        $current = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->addDay(),
            'is_archived' => false,
        ]);

        $this->assertFalse(BiometricConsentEligibility::honoursExistingRequest($archived));
        $this->assertFalse(BiometricConsentEligibility::honoursExistingRequest($lapsed));
        $this->assertTrue(BiometricConsentEligibility::honoursExistingRequest($current));
    }

    /**
     * The two signed consent routes carry a bearer credential and are reachable
     * without authentication, so they need a rate limit of their own — the bulk
     * action's throttle bounds how fast links are minted, not how fast one can be
     * replayed.
     *
     * The route throttle and RequestBiometricConsentAction::throttle() are
     * different controls on different surfaces, so the presence of one says
     * nothing about the other and each has to be asserted where it is declared.
     */
    public function test_the_signed_consent_routes_are_rate_limited(): void
    {
        $expected = [
            'biometric.consent.show' => 'throttle:60,1',
            'biometric.consent.store' => 'throttle:30,1',
        ];

        foreach ($expected as $routeName => $throttle) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] must exist.");

            $this->assertContains(
                'signed',
                $route->gatherMiddleware(),
                "Route [{$routeName}] is a bearer credential and must stay signature-checked."
            );

            $this->assertContains(
                $throttle,
                $route->gatherMiddleware(),
                "Route [{$routeName}] is replayable for its whole TTL and must stay rate limited."
            );
        }
    }

    /**
     * The regression test for a revocation that could be replayed away.
     *
     * The signed link is a bearer credential that stays valid for its whole TTL,
     * and the only thing standing between a withdrawn consent and an active one
     * was the absence of an active row — the partial unique index only guards
     * `revoked_at IS NULL`. So a member who withdrew could be made to consent
     * again by replaying the very link that had already been used, which defeats
     * the withdrawal entirely.
     */
    public function test_a_revoked_consent_cannot_be_reinstated_by_replaying_the_signed_link(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'withdrawn@example.com',
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);

        BiometricConsent::factory()->create([
            'gym_id' => $gym->id,
            'gym_member_id' => $member->id,
            'revoked_at' => now()->subDay(),
        ]);

        $this->assertTrue(
            $member->hasRevokedBiometricConsent(),
            'Fixture must hold a withdrawn consent for this to mean anything.'
        );
        $this->assertFalse(
            $member->hasActiveBiometricConsent(),
            'A revoked row is not an active one, which is exactly why the replay guard exists.'
        );

        $showUrl = URL::temporarySignedRoute('biometric.consent.show', now()->addDays(7), [
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $storeUrl = URL::temporarySignedRoute('biometric.consent.store', now()->addDays(7), [
            'gym_member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->get($showUrl)->assertGone();
        $this->post($storeUrl, ['gym_member_id' => $member->id, 'consented' => 1])->assertGone();

        $this->assertDatabaseCount('biometric_consents', 1);
        $this->assertDatabaseMissing('biometric_consents', [
            'gym_member_id' => $member->id,
            'revoked_at' => null,
        ]);
    }
}
