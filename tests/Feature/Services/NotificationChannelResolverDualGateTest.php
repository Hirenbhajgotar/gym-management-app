<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\NotificationType;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\Gym;
use App\Models\GymSetting;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Providers\WhatsAppProviderFactory;
use App\Services\Biometric\BiometricConsentTemplateResolver;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsApp\TemplateLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NotificationChannelResolverDualGateTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    public function test_returns_false_when_whatsapp_notifications_setting_disabled_or_missing(): void
    {
        // 1. Setting completely missing
        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        // 2. Setting explicitly false
        $this->saveBooleanSetting('whatsapp_notifications', false);

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    public function test_returns_false_when_the_biometric_consent_notification_setting_is_disabled_or_missing(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->createApprovedGymTemplate();

        // 1. Per-type setting completely missing
        GymSetting::query()
            ->where('gym_id', $this->gym->id)
            ->where('key', NotificationType::BIOMETRIC_CONSENT->value)
            ->delete();

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        // 2. Per-type setting explicitly false
        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, false);

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        // 3. Per-type setting enabled again
        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, true);

        $this->assertTrue(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    public function test_returns_false_when_account_missing_or_inactive(): void
    {
        $this->saveBooleanSetting('whatsapp_notifications', true);
        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, true);
        $this->createApprovedGymTemplate();

        // No WhatsAppAccount exists for gym
        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        // Inactive WhatsAppAccount
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'status' => WhatsAppAccountStatusEnum::SUSPENDED,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'AC' . fake()->unique()->numerify('######################'),
            'twilio_auth_token' => 'token',
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+15551234567',
            'country_code' => 'US',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
        ]);

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    public function test_returns_false_when_account_has_no_active_phone_number(): void
    {
        $this->saveBooleanSetting('whatsapp_notifications', true);
        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, true);
        $this->createApprovedGymTemplate();

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'AC' . fake()->unique()->numerify('######################'),
            'twilio_auth_token' => 'token',
        ]);

        // Case A: No phone number record
        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        // Case B: Inactive phone number
        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+15551234567',
            'country_code' => 'US',
            'status' => WhatsAppPhoneNumberStatusEnum::DEACTIVATED,
        ]);

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    public function test_returns_false_when_no_template_exists(): void
    {
        $this->configureActiveWhatsAppForGym();

        $this->assertFalse(BiometricConsentTemplateResolver::exists($this->gym->id));
        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    public function test_returns_false_when_template_unapproved_or_inactive(): void
    {
        $this->configureActiveWhatsAppForGym();

        // Case A: Template exists but status is pending
        $template = $this->createBiometricTemplate($this->gym->id, ['twilio_template_sid' => 'HXunapproved'], [
            'body' => 'Please provide biometric consent',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);

        $this->assertFalse(BiometricConsentTemplateResolver::exists($this->gym->id));
        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        // Case B: Template approved but is_active is false
        $template->update([
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => false,
        ]);

        $this->assertFalse(BiometricConsentTemplateResolver::exists($this->gym->id));
        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    public function test_returns_true_when_gym_has_active_gym_specific_approved_template(): void
    {
        $this->configureActiveWhatsAppForGym();

        $this->createApprovedGymTemplate();

        $this->assertTrue(BiometricConsentTemplateResolver::exists($this->gym->id));
        $this->assertTrue(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
        $this->assertNull(
            BiometricConsentTemplateResolver::unavailableReason($this->gym->id),
            'A gym with its own dispatchable template has nothing to explain.'
        );
    }

    /**
     * The regression test for a gate that promised a dispatch the provider could
     * not make.
     *
     * The resolver used to opt into the platform default unconditionally, so a
     * system-owned (gym_id IS NULL) row carrying a real Twilio SID made the gate
     * report the channel sendable. The job hands the provider a template *name*
     * and the provider re-resolves it through its own lookup — gym-scoped in
     * production, widened only inside the Twilio sandbox — so the row the gate
     * had just approved is the one row it could never find. Every send then threw
     * assertDelivered() on all three tries and the gym was told nothing.
     *
     * The resolver now derives its scope from the provider's, so the gate and the
     * delivery path are the same question again.
     */
    public function test_a_platform_owned_template_does_not_satisfy_a_production_gate(): void
    {
        $this->configureActiveWhatsAppForGym();

        $this->createBiometricTemplate(null, ['twilio_template_sid' => 'HXsystem_default_sid'], [
            'display_name' => 'Platform Biometric Consent',
        ]);

        $this->assertFalse(
            BiometricConsentTemplateResolver::exists($this->gym->id),
            'TwilioMessagingService::findTemplate() is gym-scoped in production, so it cannot see this row.'
        );
        $this->assertNull(BiometricConsentTemplateResolver::resolve($this->gym->id));
        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    /**
     * Inside the Twilio sandbox the provider does widen, so the resolver has to
     * widen with it — otherwise the sandbox would be the one place the gate and
     * the lookup could still disagree, in the opposite direction.
     */
    public function test_the_platform_default_is_in_scope_inside_the_twilio_sandbox(): void
    {
        Config::set('services.twilio.use_sandbox', true);

        $this->configureActiveWhatsAppForGym();

        $system = $this->createBiometricTemplate(null, ['twilio_template_sid' => 'HXsystem_default_sid'], [
            'display_name' => 'Platform Biometric Consent',
        ]);

        $this->assertSame(
            $system->id,
            BiometricConsentTemplateResolver::resolve($this->gym->id)?->id,
            'The resolver must widen exactly where TwilioMessagingService::findTemplate() widens.'
        );
    }

    /**
     * Meta has no sandbox and never widens, so the flag being true must not be
     * enough to drag a platform row into a Meta gym's scope.
     */
    public function test_a_meta_gym_never_inherits_the_platform_default_even_in_the_twilio_sandbox(): void
    {
        Config::set('services.twilio.use_sandbox', true);

        $gym = Gym::factory()->create();

        $this->configureActiveWhatsAppForGym($gym, WhatsAppProviderTypeEnum::WABA);

        $this->createBiometricTemplate(null, ['meta_template_id' => '998877665544'], [
            'display_name' => 'Platform Biometric Consent',
        ]);

        $this->assertFalse(BiometricConsentTemplateResolver::exists($gym->id));
    }

    /**
     * The round-2 blocker: an approved and active template that carries no vendor
     * identifier at all. Twilio would post contentSid => null after a credit
     * deduction and Meta would post a name it has never seen, so the gate has to
     * refuse the row before either provider spends anything.
     */
    public function test_rejects_an_approved_template_that_carries_no_provider_identifier(): void
    {
        $this->configureActiveWhatsAppForGym();

        $this->createBiometricTemplate($this->gym->id, [], ['display_name' => 'Seeded Biometric Consent']);

        $this->assertFalse(BiometricConsentTemplateResolver::exists($this->gym->id));
        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        $this->assertStringContainsString(
            'Twilio content SID',
            (string) BiometricConsentTemplateResolver::unavailableReason($this->gym->id)
        );
    }

    public function test_a_meta_identifier_does_not_satisfy_a_twilio_gym(): void
    {
        $this->configureActiveWhatsAppForGym();

        $this->createBiometricTemplate($this->gym->id, ['meta_template_id' => '998877665544']);

        $this->assertFalse(BiometricConsentTemplateResolver::exists($this->gym->id));
    }

    /**
     * The other half of the identifier predicate. A Twilio SID on a Meta gym is
     * the same refusal as a Meta id on a Twilio gym, and it has to name the right
     * column: the reason is read by an admin looking at a WhatsApp settings
     * screen, so "Twilio content SID" there would send them to the wrong field.
     */
    public function test_a_twilio_identifier_does_not_satisfy_a_meta_gym_and_says_so(): void
    {
        $gym = Gym::factory()->create();

        $this->configureActiveWhatsAppForGym($gym, WhatsAppProviderTypeEnum::WABA);

        $this->createBiometricTemplate($gym->id, ['twilio_template_sid' => 'HXgym_sid']);

        $this->assertFalse(BiometricConsentTemplateResolver::exists($gym->id));

        $this->assertStringContainsString(
            'Meta template ID',
            (string) BiometricConsentTemplateResolver::unavailableReason($gym->id)
        );
    }

    public function test_a_meta_gym_accepts_a_meta_identifier(): void
    {
        $gym = Gym::factory()->create();

        $this->configureActiveWhatsAppForGym($gym, WhatsAppProviderTypeEnum::WABA);

        $this->createBiometricTemplate($gym->id, ['meta_template_id' => '998877665544']);

        $this->assertTrue(BiometricConsentTemplateResolver::exists($gym->id));
        $this->assertNull(BiometricConsentTemplateResolver::unavailableReason($gym->id));
        $this->assertNull(NotificationChannelResolver::biometricConsentWhatsAppGateFailure($gym));
    }

    /**
     * The gate and its explanation must never disagree, and the explanation has
     * to name the condition that is actually holding the channel closed.
     */
    public function test_gate_failure_explains_each_blocking_condition(): void
    {
        // No settings at all.
        GymSetting::query()->where('gym_id', $this->gym->id)->delete();

        $failure = NotificationChannelResolver::biometricConsentWhatsAppGateFailure($this->gym);
        $this->assertIsString($failure);
        $this->assertStringContainsString('WhatsApp notifications are switched off', $failure);

        $this->configureActiveWhatsAppForGym();
        $this->createApprovedGymTemplate();

        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, false);

        $failure = NotificationChannelResolver::biometricConsentWhatsAppGateFailure($this->gym);
        $this->assertIsString($failure);
        $this->assertStringContainsString('Biometric consent notifications are switched off', $failure);

        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, true);

        $this->assertNull(NotificationChannelResolver::biometricConsentWhatsAppGateFailure($this->gym));
        $this->assertTrue(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    public function test_gate_failure_names_the_missing_template(): void
    {
        $this->configureActiveWhatsAppForGym();

        $failure = NotificationChannelResolver::biometricConsentWhatsAppGateFailure($this->gym);

        $this->assertIsString($failure);
        $this->assertStringContainsString('No approved, active biometric consent WhatsApp template', $failure);
    }

    /**
     * The gate stripped the tenant global scope while the provider lookups kept
     * it, so `orWhereNull('gym_id')` was dead SQL in an HTTP request and live in
     * a worker — the same lookup returning different rows depending on where it
     * ran. Both sides now go through TemplateLookup, so the gym's own row must
     * beat the system default on the resolver's query and on the shared scope.
     */
    public function test_the_gym_row_wins_over_the_system_default_for_both_providers(): void
    {
        $this->configureActiveWhatsAppForGym();

        $this->createBiometricTemplate(null, ['twilio_template_sid' => 'HXsystem_default_sid'], [
            'display_name' => 'System Default',
            'body' => 'System body',
        ]);

        $gymTemplate = $this->createApprovedGymTemplate();

        // The resolver's own query.
        $this->assertSame($gymTemplate->id, BiometricConsentTemplateResolver::resolve($this->gym->id)?->id);

        // The shared scope: matching on the name both rows share is what makes
        // this an ordering test rather than a lookup test.
        $this->assertSame(
            $gymTemplate->id,
            TemplateLookup::applyScope(
                WhatsAppTemplate::query()->where('name', NotificationType::BIOMETRIC_CONSENT->value),
                $this->gym->id,
                allowSystemDefault: true,
            )->first()?->id
        );
    }

    public function test_a_soft_deleted_gym_row_leaves_the_gym_with_no_template(): void
    {
        $this->configureActiveWhatsAppForGym();

        $gymTemplate = $this->createApprovedGymTemplate();
        $gymTemplate->delete();

        $this->assertNull(
            BiometricConsentTemplateResolver::resolve($this->gym->id),
            'Retiring the gym\'s own consent template closes the channel rather than silently inheriting the platform\'s.'
        );
    }

    private function configureActiveWhatsAppForGym(
        ?Gym $gym = null,
        WhatsAppProviderTypeEnum $provider = WhatsAppProviderTypeEnum::TWILIO
    ): void {
        $gym ??= $this->gym;

        $this->saveBooleanSetting('whatsapp_notifications', true, $gym);
        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, true, $gym);

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'provider_type' => $provider,
            'twilio_account_sid' => 'AC' . fake()->unique()->numerify('######################'),
            'twilio_auth_token' => 'token',
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+1555' . fake()->unique()->numerify('#######'),
            'country_code' => 'US',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
        ]);

        WhatsAppProviderFactory::invalidateProviderCache($gym->id);
    }

    private function createApprovedGymTemplate(): WhatsAppTemplate
    {
        return $this->createBiometricTemplate($this->gym->id, ['twilio_template_sid' => 'HX' . fake()->unique()->numerify('######################')]);
    }

    /**
     * The vendor identifier columns are deliberately non-fillable — services set
     * them by direct assignment — so they cannot ride along with create().
     *
     * @param  array<string, mixed>  $identifiers
     */
    private function createBiometricTemplate(?int $gymId, array $identifiers = [], array $overrides = []): WhatsAppTemplate
    {
        $template = WhatsAppTemplate::query()->create(array_merge([
            'gym_id' => $gymId,
            'name' => NotificationType::BIOMETRIC_CONSENT->value,
            'display_name' => 'Biometric Consent',
            'body' => 'Please provide biometric consent at {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => true,
        ], $overrides));

        foreach ($identifiers as $column => $value) {
            $template->{$column} = $value;
        }

        $template->save();

        return $template->refresh();
    }

    private function saveBooleanSetting(string $key, bool $value, ?Gym $gym = null): void
    {
        GymSetting::query()->updateOrCreate(
            ['gym_id' => ($gym ?? $this->gym)->id, 'key' => $key],
            ['value' => $value, 'type' => gettype($value)]
        );
    }
}
