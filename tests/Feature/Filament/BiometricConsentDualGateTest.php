<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Enums\NotificationType;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Actions\RequestBiometricConsentAction;
use App\Filament\Admin\Resources\GymMemberResource\Pages\ListGymMembers;
use App\Jobs\FanOutBiometricConsentRequestsJob;
use App\Jobs\SendBiometricConsentNotificationJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\GymSetting;
use App\Models\Permission;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\NotificationChannelResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricConsentDualGateTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER_ACTION_FORM = 'mountedActionForm';

    private const BULK_ACTION_FORM = 'mountedTableBulkActionForm';

    protected Gym $gym;

    protected User $gymOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');

        $this->gymOwner = User::factory()->gymOwner()->create();
        $this->gymOwner->gyms()->attach($this->gym->id, ['role' => DefaultRole::GYM_OWNER->value]);

        foreach (['view_any_gym_member', 'view_gym_member', 'create_gym_member', 'update_gym_member', 'delete_gym_member', 'delete_any_gym_member'] as $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($permission);
            $this->gymOwner->givePermissionTo($permission);
        }

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    #[Test]
    public function the_dual_gate_passes_only_when_every_half_is_satisfied(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->enableBiometricConsentNotifications();
        $this->createApprovedConsentTemplate();

        $this->assertTrue(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));
    }

    #[Test]
    public function the_whatsapp_toggle_is_visible_on_the_header_action_when_the_dual_gate_passes(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->enableBiometricConsentNotifications();
        $this->createApprovedConsentTemplate();

        $this->assertTrue(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        $this->mountHeaderConsentAction()
            ->assertFormFieldIsVisible('send_whatsapp', self::HEADER_ACTION_FORM)
            ->assertFormFieldIsVisible('send_email', self::HEADER_ACTION_FORM);
    }

    #[Test]
    public function the_whatsapp_toggle_is_visible_on_the_bulk_action_when_the_dual_gate_passes(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->enableBiometricConsentNotifications();
        $this->createApprovedConsentTemplate();

        $this->assertTrue(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        $this->mountBulkConsentAction()
            ->assertFormFieldIsVisible('send_whatsapp', self::BULK_ACTION_FORM)
            ->assertFormFieldIsVisible('send_email', self::BULK_ACTION_FORM);
    }

    #[Test]
    public function the_whatsapp_toggle_is_disabled_on_both_actions_when_the_tenant_whatsapp_setting_is_disabled(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->enableBiometricConsentNotifications();
        $this->createApprovedConsentTemplate();

        $this->saveBooleanSetting('whatsapp_notifications', false);

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        $this->mountHeaderConsentAction()
            ->assertFormFieldIsDisabled('send_whatsapp', self::HEADER_ACTION_FORM);

        $this->mountBulkConsentAction()
            ->assertFormFieldIsDisabled('send_whatsapp', self::BULK_ACTION_FORM);
    }

    #[Test]
    public function the_whatsapp_toggle_is_disabled_on_both_actions_when_the_biometric_consent_notification_setting_is_disabled(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->createApprovedConsentTemplate();

        $this->disableBiometricConsentNotifications();

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        $this->mountHeaderConsentAction()
            ->assertFormFieldIsDisabled('send_whatsapp', self::HEADER_ACTION_FORM);

        $this->mountBulkConsentAction()
            ->assertFormFieldIsDisabled('send_whatsapp', self::BULK_ACTION_FORM);
    }

    #[Test]
    public function the_whatsapp_toggle_is_disabled_on_both_actions_when_the_consent_template_is_missing_or_unapproved(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->enableBiometricConsentNotifications();

        WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => NotificationType::BIOMETRIC_CONSENT->value,
            'display_name' => 'Biometric Consent',
            'body' => 'Please provide biometric consent at {{1}}',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
            'is_active' => true,
        ]);

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        $this->mountHeaderConsentAction()
            ->assertFormFieldIsDisabled('send_whatsapp', self::HEADER_ACTION_FORM);

        $this->mountBulkConsentAction()
            ->assertFormFieldIsDisabled('send_whatsapp', self::BULK_ACTION_FORM);
    }

    #[Test]
    public function the_whatsapp_toggle_is_disabled_on_both_actions_when_the_account_has_no_active_phone_number(): void
    {
        $this->enableBiometricConsentNotifications();

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'AC' . fake()->unique()->numerify('######################'),
            'twilio_auth_token' => 'token',
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+15551234567',
            'country_code' => 'US',
            'status' => WhatsAppPhoneNumberStatusEnum::DEACTIVATED,
        ]);

        $this->createApprovedConsentTemplate();

        $this->assertFalse(NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($this->gym));

        $this->mountHeaderConsentAction()
            ->assertFormFieldIsDisabled('send_whatsapp', self::HEADER_ACTION_FORM);

        $this->mountBulkConsentAction()
            ->assertFormFieldIsDisabled('send_whatsapp', self::BULK_ACTION_FORM);
    }

    #[Test]
    public function the_header_action_dispatches_a_whatsapp_consent_request_when_the_dual_gate_passes(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->enableBiometricConsentNotifications();
        $this->createApprovedConsentTemplate();

        $member = $this->makeEligibleMember();

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callAction(RequestBiometricConsentAction::HEADER_ACTION, [
                'send_email' => false,
                'send_whatsapp' => true,
            ])
            ->assertNotified('Biometric Consent Requests Queued');

        // The header path fans out in the worker, so run it to reach the
        // per-member job the WhatsApp gate actually authorises.
        Queue::assertPushed(FanOutBiometricConsentRequestsJob::class, 1);

        (new FanOutBiometricConsentRequestsJob(
            gymId: $this->gym->id,
            actorId: $this->gymOwner->id,
            sendEmail: false,
            sendWhatsApp: true,
        ))->handle();

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($member): bool {
            return $job->gymMember->id === $member->id && $job->sendEmail === false && $job->sendWhatsApp === true;
        });
    }

    #[Test]
    public function the_bulk_action_dispatches_a_whatsapp_consent_request_when_the_dual_gate_passes(): void
    {
        $this->configureActiveWhatsAppForGym();
        $this->enableBiometricConsentNotifications();
        $this->createApprovedConsentTemplate();

        $member = $this->makeEligibleMember();

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callTableBulkAction(RequestBiometricConsentAction::BULK_ACTION, [$member], [
                'send_email' => false,
                'send_whatsapp' => true,
            ])
            ->assertNotified('Biometric Consent Requests Queued');

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($member): bool {
            return $job->gymMember->id === $member->id && $job->sendEmail === false && $job->sendWhatsApp === true;
        });
    }

    private function makeEligibleMember(): GymMember
    {
        return GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'phone' => '+15551234567',
            'allow_whatsapp_marketing' => true,
        ]);
    }

    private function mountHeaderConsentAction(): Testable
    {
        return Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->mountAction(RequestBiometricConsentAction::HEADER_ACTION);
    }

    private function mountBulkConsentAction(): Testable
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);

        return Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->mountTableBulkAction(RequestBiometricConsentAction::BULK_ACTION, [$member]);
    }

    private function configureActiveWhatsAppForGym(): void
    {
        $this->saveBooleanSetting('whatsapp_notifications', true);

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
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
    }

    private function enableBiometricConsentNotifications(): void
    {
        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, true);
    }

    private function disableBiometricConsentNotifications(): void
    {
        $this->saveBooleanSetting(NotificationType::BIOMETRIC_CONSENT->value, false);
    }

    private function saveBooleanSetting(string $key, bool $value): void
    {
        GymSetting::query()->updateOrCreate(
            ['gym_id' => $this->gym->id, 'key' => $key],
            ['value' => $value, 'type' => gettype($value)]
        );
    }

    private function createApprovedConsentTemplate(): WhatsAppTemplate
    {
        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => NotificationType::BIOMETRIC_CONSENT->value,
            'display_name' => 'Biometric Consent',
            'body' => 'Please provide biometric consent at {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => true,
        ]);

        // Non-fillable: services assign the vendor identifier directly. Without it
        // the gate refuses the template, because Twilio could not dispatch it.
        $template->twilio_template_sid = 'HX' . fake()->unique()->numerify('######################');
        $template->save();

        return $template->refresh();
    }
}
