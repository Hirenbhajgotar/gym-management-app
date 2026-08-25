<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Pages\WhatsApp\Broadcast;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupTestCase;

#[CoversClass(Broadcast::class)]
final class WhatsAppBroadcastValidationTest extends WhatsAppSetupTestCase
{
    protected User $user;

    protected Gym $gym;

    protected WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsGymOwner();

        $gym = Filament::getTenant();
        assert($gym instanceof Gym);
        $this->gym = $gym;

        Role::query()->firstOrCreate([
            'name' => DefaultRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
            'gym_id' => $this->gym->id,
        ]);
        $this->user->assignRole(DefaultRole::SUPER_ADMIN->value);

        config(['services.twilio.use_sandbox' => false]);

        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);

        WhatsAppMessageCredit::query()->firstOrCreate(
            ['gym_id' => $this->gym->id],
            [
                'total_credits' => 100,
                'used_credits' => 0,
                'remaining_credits' => 100,
            ]
        );

        $this->template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => true,
        ]);
    }

    private function createTestPage(): Testable
    {
        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);

        return Livewire::test(Broadcast::class);
    }

    #[Test]
    public function it_requires_template_id(): void
    {
        $this->createTestPage()
            ->fillForm([
                'template_id' => null,
            ])
            ->set('targetCount', 1)
            ->set('selectedMemberIds', [1])
            ->call('sendBroadcast')
            ->assertHasFormErrors(['template_id']);
    }

    #[Test]
    public function it_requires_scheduled_at_when_schedule_type_is_later(): void
    {
        $this->createTestPage()
            ->set('data.schedule_type', 'later')
            ->fillForm([
                'template_id' => $this->template->id,
                'schedule_type' => 'later',
                'scheduled_at' => null,
            ])
            ->set('targetCount', 1)
            ->set('selectedMemberIds', [1])
            ->call('sendBroadcast')
            ->assertHasFormErrors(['scheduled_at']);
    }

    #[Test]
    public function it_fails_broadcast_when_no_members_are_selected_in_filtered_mode(): void
    {
        $this->createTestPage()
            ->fillForm([
                'template_id' => $this->template->id,
                'schedule_type' => 'now',
            ])
            ->set('selectedMemberIds', [])
            ->set('targetCount', 0)
            ->call('sendBroadcast')
            ->assertHasFormErrors(['filters.plans']);
    }

    #[Test]
    public function it_sends_broadcast_successfully_with_valid_data(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now(),
            'membership_end' => now()->addMonth(),
            'allow_whatsapp_marketing' => true,
            'is_archived' => false,
            'phone' => '+1234567890',
        ]);

        $this->createTestPage()
            ->fillForm([
                'template_id' => $this->template->id,
                'schedule_type' => 'now',
            ])
            ->set('selectedMemberIds', [$member->id])
            ->set('targetCount', 1)
            ->call('sendBroadcast')
            ->assertNotified('Broadcast Started!');
    }

    #[Test]
    public function it_fails_updated_filters_when_no_filters_are_selected(): void
    {
        $this->createTestPage()
            ->fillForm([
                'filters' => [
                    'plans' => null,
                    'trainers' => null,
                    'membership_type' => null,
                    'payment_status' => null,
                ],
            ])
            ->call('applyFilters')
            ->assertHasFormErrors(['filters.plans']);
    }

    #[Test]
    public function it_fails_updated_filters_when_zero_members_match(): void
    {
        $this->createTestPage()
            ->fillForm([
                'filters' => [
                    'membership_type' => 'active',
                ],
            ])
            ->call('applyFilters')
            ->assertHasFormErrors(['filters.plans']);
    }

    #[Test]
    public function it_succeeds_updated_filters_when_matching_members_are_found(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now(),
            'membership_end' => now()->addMonth(),
            'allow_whatsapp_marketing' => true,
            'is_archived' => false,
            'phone' => '+1234567890',
        ]);

        $page = $this->createTestPage();
        $page->fillForm([
            'filters' => [
                'membership_type' => 'active',
            ],
        ]);
        $page->call('applyFilters');
        $page->assertHasNoFormErrors();
        $page->assertSet('filtersApplied', true);
        $page->assertSet('targetCount', 1);
    }

    #[Test]
    public function it_shows_credits_in_summary_stat_card_when_billing_mode_is_not_self_managed(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP,
        ]);

        $page = $this->createTestPage();
        $page->assertSee('Required Credits');
    }

    #[Test]
    public function it_shows_expected_cost_in_summary_stat_card_when_billing_mode_is_self_managed(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED,
        ]);

        $page = $this->createTestPage();
        $page->assertSee('Expected Cost');
    }
}
