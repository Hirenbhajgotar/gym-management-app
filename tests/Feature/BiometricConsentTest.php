<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\BiometricConsentGiven;
use App\Http\Controllers\BiometricConsentController;
use App\Http\Requests\BiometricConsentStoreRequest;
use App\Jobs\SendBiometricConsentEmail;
use App\Models\BiometricConsent;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake();

        $gym = Gym::factory()->create();

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => 'newmember@example.com',
        ]);

        // Verify job was dispatched
        Queue::assertPushed(SendBiometricConsentEmail::class, function ($job) use ($member) {
            return $job->gymMember->id === $member->id;
        });
    }

    public function test_member_creation_skips_job_when_no_email(): void
    {
        Queue::fake();

        $gym = Gym::factory()->create();

        // Create member with empty string email (not null, since DB may require email)
        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'email' => '',
        ]);

        // Verify job was NOT dispatched (empty email should skip)
        Queue::assertNotPushed(SendBiometricConsentEmail::class);
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
}
