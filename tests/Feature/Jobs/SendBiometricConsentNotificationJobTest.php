<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\NotificationType;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Jobs\SendBiometricConsentNotificationJob;
use App\Mail\BiometricConsentMail;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\GymSetting;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Providers\MetaWhatsAppProvider;
use App\Providers\TwilioWhatsAppProvider;
use App\Providers\WhatsAppProviderFactory;
use App\Services\Biometric\BiometricConsentTemplateResolver;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsAppVariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SendBiometricConsentNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Twilio resolves a content template on its name alone, so the job must hand
     * the provider the template name and never the Twilio SID.
     */
    private const TEMPLATE_NAME = 'biometric_consent_notifications';

    private const TEMPLATE_SID = 'HXtest_consent_sid';

    private const META_TEMPLATE_ID = '998877665544';

    private const META_PHONE_NUMBER_ID = '1163624323508414';

    /**
     * A number libphonenumber reports as valid. The suite used to dial fictional
     * +1555… fixtures, which are "possible" but not valid — exactly the gap
     * PhoneNumber::toDialableE164() now closes.
     */
    private const DIALABLE_NUMBER = '+919261212121';

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        Config::set('services.twilio.use_sandbox', false);
        Config::set('services.twilio.sandbox_number', '+15557626186');

        // Creating a member for a gym that runs biometrics makes
        // GymMemberObserver queue a consent job, which writes to the same
        // per-channel delivery ledger the job under test asserts on. Faking the
        // queue keeps this class in control of when a request is actually sent.
        Queue::fake();
    }

    protected function tearDown(): void
    {
        WhatsAppProviderFactory::clearTestProviders();

        // parent::tearDown() runs first so a failed Mockery expectation can never
        // skip RefreshDatabase's rollback.
        parent::tearDown();

        Mockery::close();
    }

    #[Test]
    public function it_sends_the_template_by_name_and_carries_the_signed_consent_url(): void
    {
        $member = $this->makeDeliverableMember();

        $provider = $this->registerProvider();

        /** @var Expectation $expectation */
        $expectation = $provider->shouldReceive('sendTemplateMessage');
        $expectation->once()->andReturnUsing(function (string $to, string $templateName, array $variables) use ($member): array {
            $this->assertSame(self::DIALABLE_NUMBER, $to);
            $this->assertSame(self::TEMPLATE_NAME, $templateName);
            $this->assertNotSame(self::TEMPLATE_SID, $templateName);
            $this->assertArrayHasKey('2', $variables);
            $this->assertStringContainsString('consent/biometric', (string) $variables['2']);
            $this->assertStringContainsString('gym_member_id=' . $member->id, (string) $variables['2']);

            return ['success' => true, 'message_id' => 'SM123'];
        });

        $this->runJob($member);
    }

    #[Test]
    public function it_throws_when_the_provider_reports_a_failure_result(): void
    {
        $member = $this->makeDeliverableMember();

        $provider = $this->registerProvider();

        /** @var Expectation $expectation */
        $expectation = $provider->shouldReceive('sendTemplateMessage');
        $expectation->once()->andReturn([
            'success' => false,
            'error' => 'Template biometric_consent_notifications not found',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WhatsApp consent request was not delivered: Template biometric_consent_notifications not found');

        $this->runJob($member);
    }

    #[Test]
    public function it_skips_an_archived_member_without_touching_the_provider(): void
    {
        $member = $this->makeDeliverableMember([
            'is_archived' => true,
            'archived_at' => now(),
            'allow_whatsapp_marketing' => false,
        ]);

        $this->registerProviderThatMustNotBeCalled();

        $this->runJob($member);
    }

    #[Test]
    public function it_skips_a_member_whose_membership_has_expired(): void
    {
        $member = $this->makeDeliverableMember(['membership_end' => now()->subDay()]);

        $this->registerProviderThatMustNotBeCalled();

        $this->runJob($member);
    }

    #[Test]
    public function it_skips_a_member_who_already_has_an_active_consent(): void
    {
        $member = $this->makeDeliverableMember();

        BiometricConsent::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'revoked_at' => null,
        ]);

        $this->registerProviderThatMustNotBeCalled();

        $this->runJob($member);
    }

    #[Test]
    public function it_skips_a_member_whose_stored_phone_is_not_dialable(): void
    {
        $member = $this->makeDeliverableMember(['phone' => 'abc']);

        $this->registerProviderThatMustNotBeCalled();

        $this->runJob($member);
    }

    #[Test]
    public function sandbox_mode_targets_the_sandbox_number_and_never_the_member_phone(): void
    {
        Config::set('services.twilio.use_sandbox', true);
        Config::set('services.twilio.sandbox_number', '+15550001111');

        $member = $this->makeDeliverableMember(['phone' => self::DIALABLE_NUMBER]);

        $provider = $this->registerProvider();

        /** @var Expectation $templateExpectation */
        $templateExpectation = $provider->shouldReceive('sendTemplateMessage');
        $templateExpectation->never();

        /** @var Expectation $messageExpectation */
        $messageExpectation = $provider->shouldReceive('sendMessage');
        $messageExpectation->once()->withArgs(function (string $to, string $body) use ($member): bool {
            $this->assertSame('+15550001111', $to);
            $this->assertNotSame((string) $member->phone, $to);
            $this->assertStringContainsString('consent/biometric', $body);

            return true;
        })->andReturn(['success' => true]);

        $this->runJob($member);
    }

    #[Test]
    public function a_second_run_for_the_same_member_on_the_same_day_delivers_only_once(): void
    {
        $member = $this->makeDeliverableMember();

        $provider = $this->registerProvider();

        /** @var Expectation $expectation */
        $expectation = $provider->shouldReceive('sendTemplateMessage');
        $expectation->once()->andReturn(['success' => true]);

        $this->runJob($member);
        $this->runJob($member);
    }

    /**
     * The regression test for the round-2 blocker: the previous design marked a
     * single per-day key as the first statement of handle(), so the very first
     * throw consumed it and every retry returned at the top of the method
     * logging a routine skip. The job reported success, $tries/$backoff and
     * failed() were unreachable, and the member was never asked.
     */
    #[Test]
    public function it_retries_the_channel_that_failed_without_resending_the_channel_that_succeeded(): void
    {
        $member = $this->makeDeliverableMember();

        Mail::fake();

        $provider = $this->registerProvider();

        /** @var Expectation $expectation */
        $expectation = $provider->shouldReceive('sendTemplateMessage');
        $expectation->twice()->andReturn(
            ['success' => false, 'error' => 'Twilio 503 Service Unavailable'],
            ['success' => true, 'message_id' => 'SM-retry'],
        );

        // First attempt: the email goes out, the WhatsApp send fails.
        try {
            $this->runJob($member, sendEmail: true, sendWhatsApp: true);
            $this->fail('Expected the failed WhatsApp delivery to throw.');
        } catch (RuntimeException) {
            // Expected.
        }

        Mail::assertSent(BiometricConsentMail::class, 1);

        // Second attempt: the retry must re-attempt only the failed channel.
        $this->runJob($member, sendEmail: true, sendWhatsApp: true);

        Mail::assertSent(BiometricConsentMail::class, 1);
    }

    /**
     * A member the job never reached must keep a clean ledger, so that un-archiveing
     * them later still allows a delivery. The old per-day key was consumed before any
     * eligibility check and burned the member's one request for the day.
     */
    #[Test]
    public function it_leaves_the_delivery_ledger_unmarked_for_a_member_it_never_reached(): void
    {
        $member = $this->makeDeliverableMember([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $provider = $this->registerProvider();

        /** @var Expectation $expectation */
        $expectation = $provider->shouldReceive('sendTemplateMessage');
        $expectation->once()->andReturn(['success' => true]);

        // Two skipped runs. Under the old behaviour the first of these burned
        // the member's ledger even though nothing was delivered.
        $this->runJob($member);
        $this->runJob($member);

        $this->assertFalse(Cache::has($this->ledgerKey('whatsapp', $member)));

        $member->forceFill(['is_archived' => false, 'archived_at' => null])->save();

        $this->runJob($member);

        $this->assertTrue(Cache::has($this->ledgerKey('whatsapp', $member)));
    }

    #[Test]
    public function it_records_a_delivery_ledger_entry_for_each_delivered_channel(): void
    {
        $member = $this->makeDeliverableMember();

        Mail::fake();

        $provider = $this->registerProvider();

        /** @var Expectation $expectation */
        $expectation = $provider->shouldReceive('sendTemplateMessage');
        $expectation->once()->andReturn(['success' => true]);

        $this->runJob($member, sendEmail: true, sendWhatsApp: true);

        $this->assertTrue(Cache::has($this->ledgerKey('email', $member)));
        $this->assertTrue(Cache::has($this->ledgerKey('whatsapp', $member)));
    }

    #[Test]
    public function it_does_not_record_a_delivery_ledger_entry_for_a_channel_that_failed(): void
    {
        $member = $this->makeDeliverableMember();

        Mail::fake();

        $provider = $this->registerProvider();

        /** @var Expectation $expectation */
        $expectation = $provider->shouldReceive('sendTemplateMessage');
        $expectation->once()->andReturn(['success' => false, 'error' => 'Twilio 503']);

        try {
            $this->runJob($member, sendEmail: true, sendWhatsApp: true);
            $this->fail('Expected the failed WhatsApp delivery to throw.');
        } catch (RuntimeException) {
            // Expected: assertDelivered() turns the provider's non-throwing
            // failure array into a throw so the retry budget applies.
        }

        $this->assertTrue(Cache::has($this->ledgerKey('email', $member)));
        $this->assertFalse(Cache::has($this->ledgerKey('whatsapp', $member)));
    }

    /**
     * Calls failed() directly rather than exhausting the retry budget: what is
     * under test is the hook itself — that it logs identifiers only and scrubs
     * the bearer consent URL and the member's address out of the exception text
     * before it reaches storage.
     */
    #[Test]
    public function it_logs_only_identifiers_through_the_failed_hook(): void
    {
        $member = $this->makeDeliverableMember();

        Log::spy();

        (new SendBiometricConsentNotificationJob(
            gymMember: $member,
            sendEmail: true,
            sendWhatsApp: true,
        ))->failed(new RuntimeException(
            'Delivery failed for consent_target@example.com at '
            . 'http://localhost/consent/biometric?gym_member_id=1&signature=abc123&expires=1'
        ));

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Biometric consent notification failed after retries'
                && ($context['member_id'] ?? null) === $member->id
                && ($context['gym_id'] ?? null) === $this->gym->id
                && ! str_contains((string) $context['error'], 'consent_target@example.com')
                && ! str_contains((string) $context['error'], 'signature=abc123')
        );
    }

    #[Test]
    public function it_sends_the_consent_email_when_send_email_is_true(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'email' => 'member_test@example.com',
            'membership_end' => now()->addMonth(),
        ]);

        Mail::fake();

        $this->runJob($member, sendEmail: true, sendWhatsApp: false);

        Mail::assertSent(BiometricConsentMail::class, function (BiometricConsentMail $mail) use ($member): bool {
            return $mail->gymMember->id === $member->id
                && str_contains($mail->consentUrl, 'consent/biometric');
        });
    }

    #[Test]
    public function it_sends_nothing_when_send_email_is_false(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'email' => 'has_email@example.com',
            'membership_end' => now()->addMonth(),
        ]);

        Mail::fake();

        $this->runJob($member, sendEmail: false, sendWhatsApp: false);

        Mail::assertNothingSent();
    }

    #[Test]
    public function it_sends_no_email_when_the_member_has_no_email_address(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'email' => '',
            'membership_end' => now()->addMonth(),
        ]);

        Mail::fake();

        $this->runJob($member, sendEmail: true, sendWhatsApp: false);

        Mail::assertNothingSent();
    }

    /**
     * Meta is the configured default provider, but every other test in this class
     * registers a Twilio mock, so nothing here ever reaches MetaMessagingService.
     * The provider-side template id is the payload Meta actually dispatches by —
     * posting the internal enum value in `name` produces a template Meta has
     * never seen — so this drives the real provider chain and fakes only the
     * HTTP boundary.
     *
     * The body parameters are asserted, not just the template name. Meta
     * substitutes them positionally against the template's {{1}}, {{2}}, so a
     * payload whose values arrive in the wrong order still posts cleanly and
     * still bills the gym: the member's name lands in the consent-URL slot, the
     * URL lands in the name slot, and nothing errors. Nothing else in the suite
     * pins that ordering.
     */
    #[Test]
    public function it_addresses_meta_by_the_provider_side_template_id_on_a_waba_gym(): void
    {
        $member = $this->makeMetaDeliverableMember();

        Http::fake([
            '*' => Http::response([
                'messages' => [['id' => 'wamid.HBgLOTE5ODc2NTQzMjEw']],
            ]),
        ]);

        $this->runJob($member);

        Http::assertSent(function ($request) use ($member): bool {
            $payload = $request->data();

            $this->assertSame(self::META_TEMPLATE_ID, $payload['template']['name'] ?? null);
            $this->assertNotSame(self::TEMPLATE_NAME, $payload['template']['name'] ?? null);
            $this->assertSame(self::DIALABLE_NUMBER, $payload['to'] ?? null);

            $parameters = $payload['template']['components'][0]['parameters'] ?? [];
            $texts = array_column($parameters, 'text');

            $this->assertCount(2, $texts, 'Every placeholder has to reach Meta.');
            $this->assertSame($member->name, $texts[0] ?? null, '{{1}} is the member name.');
            $this->assertStringContainsString('consent/biometric', (string) ($texts[1] ?? ''), '{{2}} is the signed consent URL.');
            $this->assertStringContainsString('gym_member_id=' . $member->id, (string) ($texts[1] ?? ''));

            return true;
        });
    }

    /**
     * The free-text branch is a safety net, not a fallback, and the only thing
     * that keeps it that way is the gate and the resolver reading the same rows
     * the provider does.
     *
     * The previous version of this test asserted the gate and the resolver
     * against each other — both of which run the same query — so it reduced to
     * "exists() implies first() is non-null" and never ran the job. This one
     * runs the job and watches which provider method is reached: a gym that
     * passes the gate must get the template path, never sendMessage().
     */
    #[Test]
    public function a_gym_that_passes_the_delivery_time_gate_never_reaches_the_free_text_branch(): void
    {
        $member = $this->makeMetaDeliverableMember();

        $gym = Gym::query()->findOrFail($this->gym->id);

        $this->assertTrue(
            NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym),
            'Fixture must pass the gate for this invariant to mean anything.'
        );

        $provider = Mockery::mock(MetaWhatsAppProvider::class);
        WhatsAppProviderFactory::setTestProvider($this->gym->id, $provider);

        /** @var Expectation $templateExpectation */
        $templateExpectation = $provider->shouldReceive('sendTemplateMessage');
        $templateExpectation->once()->andReturn(['success' => true, 'message_id' => 'wamid.NOPE']);

        /** @var Expectation $messageExpectation */
        $messageExpectation = $provider->shouldReceive('sendMessage');
        $messageExpectation->never();

        $this->runJob($member);
    }

    /**
     * A WABA gym whose own approved template carries only a Twilio SID resolves
     * nothing for Meta, so the gate closes the channel. The delivery ledger must
     * stay unmarked: burning it would make the member un-retryable for the day,
     * which is the failure mode the per-channel ledger exists to prevent.
     */
    #[Test]
    public function it_leaves_the_delivery_ledger_unmarked_when_a_waba_gym_has_no_dispatchable_template(): void
    {
        $member = $this->makeMetaDeliverableMember(withMetaTemplateId: false);

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.NOPE']]])]);

        $gym = Gym::query()->findOrFail($this->gym->id);

        $this->assertFalse(
            NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym),
            'A Meta gym with no meta_template_id must fail the gate.'
        );

        Log::spy();

        $this->runJob($member);

        Http::assertNothingSent();
        $this->assertFalse(
            Cache::has($this->ledgerKey('whatsapp', $member)),
            'A refused gate must not consume the member\'s WhatsApp delivery ledger.'
        );
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * The regression test for a gate that promised a dispatch the provider could
     * not make.
     *
     * The resolver used to opt into the platform default unconditionally, so a
     * system-owned (gym_id IS NULL) row carrying a real Meta id made exists()
     * report the channel sendable. The job then handed Meta the template *name*
     * and Meta re-resolved it through its own lookup, which is gym-scoped by
     * design — so it found nothing, returned success => false, and the send threw
     * assertDelivered() on all three tries before reaching failed() with nothing
     * delivered. Every member of the gym got a silent no-op.
     *
     * The resolver's scope now follows the providers', so the gate closes and
     * the member is never asked through a channel that cannot deliver.
     */
    #[Test]
    public function a_platform_owned_template_alone_never_reaches_the_provider(): void
    {
        $member = $this->makeMetaDeliverableMember();

        // Hand the gym's dispatchable template back to the platform: same name,
        // same provider-side id, but no gym owns it, so no provider will find it.
        $template = WhatsAppTemplate::query()
            ->where('gym_id', $this->gym->id)
            ->where('name', self::TEMPLATE_NAME)
            ->firstOrFail();
        $template->gym_id = null;
        $template->save();

        $gym = Gym::query()->findOrFail($this->gym->id);

        $this->assertFalse(
            NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym),
            'A platform-owned row is invisible to the provider, so the gate must close.'
        );
        $this->assertNull(
            BiometricConsentTemplateResolver::resolve($gym->id),
            'The resolver must not hand the job a row the provider will refuse.'
        );

        Http::fake();

        Log::spy();

        $this->runJob($member);

        Http::assertNothingSent();
        $this->assertFalse(
            Cache::has($this->ledgerKey('whatsapp', $member)),
            'A closed gate must not consume the member\'s WhatsApp delivery ledger.'
        );
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * The gate is re-checked at delivery time, not only when the modal renders:
     * a gym can switch WhatsApp off between the dispatch and the worker running.
     */
    #[Test]
    public function it_does_not_send_whatsapp_when_the_gym_fails_the_gate_after_dispatch(): void
    {
        $member = $this->makeDeliverableMember();

        $this->registerProviderThatMustNotBeCalled();

        GymSetting::query()
            ->where('gym_id', $this->gym->id)
            ->where('key', 'whatsapp_notifications')
            ->update(['value' => 'false']);

        $this->runJob($member);

        $this->assertFalse(
            Cache::has($this->ledgerKey('whatsapp', $member)),
            'A gate closed after dispatch must not consume the delivery ledger.'
        );
    }

    /**
     * A gym on Meta rather than Twilio. Separate from
     * configureGymForWhatsAppConsent() because MetaContext and
     * WhatsAppProviderFactory each resolve a single account per gym, so the two
     * providers cannot share one gym.
     */
    private function makeMetaDeliverableMember(bool $withMetaTemplateId = true): GymMember
    {
        $this->configureGymForMetaConsent($withMetaTemplateId);

        return GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Meta Consent Target',
            'email' => 'meta_consent_target@example.com',
            'phone' => self::DIALABLE_NUMBER,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);
    }

    private function configureGymForMetaConsent(bool $withMetaTemplateId = true): WhatsAppTemplate
    {
        BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => self::META_PHONE_NUMBER_ID,
            'phone_number' => self::DIALABLE_NUMBER,
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'remaining_credits' => 100,
            'used_credits' => 0,
        ]);

        // The provider a gym resolves to is cached, and TestCase leaves redis
        // alone between tests, so a stale Twilio entry would make the resolver
        // demand the wrong identifier column.
        WhatsAppProviderFactory::invalidateProviderCache($this->gym->id);

        $this->enableWhatsAppConsentGates();

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => self::TEMPLATE_NAME,
            'display_name' => 'Biometric Consent',
            'body' => 'Hello {{1}}, please consent at {{2}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => true,
            'variable_count' => 2,
            'variable_placeholder' => [
                '1' => [
                    'placeholder' => 'Member Name',
                    'name' => 'member_name',
                    'example' => 'John',
                    'type' => 'text',
                    'source' => 'member.name',
                ],
                '2' => [
                    'placeholder' => 'Consent Link',
                    'name' => 'consent_link',
                    'example' => 'https://example.com',
                    'type' => 'url',
                    'source' => 'biometric.consent_url',
                ],
            ],
        ]);

        $template->twilio_template_sid = $withMetaTemplateId ? null : self::TEMPLATE_SID;
        $template->meta_template_id = $withMetaTemplateId ? self::META_TEMPLATE_ID : null;
        $template->save();

        return $template->refresh();
    }

    private function enableWhatsAppConsentGates(): void
    {
        foreach (['whatsapp_notifications', NotificationType::BIOMETRIC_CONSENT->value] as $key) {
            GymSetting::query()->updateOrCreate(
                ['gym_id' => $this->gym->id, 'key' => $key],
                ['value' => true, 'type' => gettype(true)]
            );
        }
    }

    /**
     * A gym that passes the complete WhatsApp consent gate: an active biometric
     * device, an active account with an active number, both notification
     * toggles on, and an approved active template that maps a variable to
     * biometric.consent_url.
     */
    private function configureGymForWhatsAppConsent(): WhatsAppTemplate
    {
        BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'AC' . fake()->unique()->numerify('######################'),
            'twilio_auth_token' => 'test_token',
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+1555' . fake()->unique()->numerify('#######'),
            'country_code' => 'US',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
        ]);

        // The provider a gym resolves to is cached in redis, and TestCase leaves
        // redis alone between tests, so a stale entry from an earlier test would
        // make the gate demand the other provider's identifier.
        WhatsAppProviderFactory::invalidateProviderCache($this->gym->id);

        GymSetting::query()->updateOrCreate(
            ['gym_id' => $this->gym->id, 'key' => 'whatsapp_notifications'],
            ['value' => true, 'type' => gettype(true)]
        );

        GymSetting::query()->updateOrCreate(
            ['gym_id' => $this->gym->id, 'key' => NotificationType::BIOMETRIC_CONSENT->value],
            ['value' => true, 'type' => gettype(true)]
        );

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => self::TEMPLATE_NAME,
            'display_name' => 'Biometric Consent',
            'body' => 'Hello {{1}}, please consent at {{2}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => true,
            'variable_count' => 2,
            'variable_placeholder' => [
                '1' => [
                    'placeholder' => 'Member Name',
                    'name' => 'member_name',
                    'example' => 'John',
                    'type' => 'text',
                    'source' => 'member.name',
                ],
                '2' => [
                    'placeholder' => 'Consent Link',
                    'name' => 'consent_link',
                    'example' => 'https://example.com',
                    'type' => 'url',
                    'source' => 'biometric.consent_url',
                ],
            ],
        ]);

        // The vendor identifier columns are deliberately non-fillable, so the
        // services that carry them set them by direct assignment. The gate
        // refuses a template the provider cannot dispatch.
        $template->twilio_template_sid = self::TEMPLATE_SID;
        $template->meta_template_id = self::META_TEMPLATE_ID;
        $template->save();

        return $template->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDeliverableMember(array $overrides = []): GymMember
    {
        $this->configureGymForWhatsAppConsent();

        return GymMember::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
            'name' => 'Consent Target',
            'email' => 'consent_target@example.com',
            'phone' => self::DIALABLE_NUMBER,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ], $overrides));
    }

    private function runJob(GymMember $member, bool $sendEmail = false, bool $sendWhatsApp = true): void
    {
        (new SendBiometricConsentNotificationJob(
            gymMember: $member,
            sendEmail: $sendEmail,
            sendWhatsApp: $sendWhatsApp,
        ))->handle(new WhatsAppVariableResolver());
    }

    /**
     * Mirrors SendBiometricConsentNotificationJob::deliveryLedgerKey() so the
     * tests assert against the real key format rather than a guess at it.
     */
    private function ledgerKey(string $channel, GymMember $member): string
    {
        return "biometric_consent_delivered_{$channel}_{$member->id}_" . today()->toDateString();
    }

    /**
     * @return TwilioWhatsAppProvider&Mockery\MockInterface
     */
    private function registerProvider(): object
    {
        $provider = Mockery::mock(TwilioWhatsAppProvider::class);
        WhatsAppProviderFactory::setTestProvider($this->gym->id, $provider);

        return $provider;
    }

    /**
     * Registers a provider that fails the test if the job reaches it at all.
     *
     * @return TwilioWhatsAppProvider&Mockery\MockInterface
     */
    private function registerProviderThatMustNotBeCalled(): object
    {
        $provider = $this->registerProvider();

        /** @var Expectation $templateExpectation */
        $templateExpectation = $provider->shouldReceive('sendTemplateMessage');
        $templateExpectation->never();

        /** @var Expectation $messageExpectation */
        $messageExpectation = $provider->shouldReceive('sendMessage');
        $messageExpectation->never();

        return $provider;
    }
}
