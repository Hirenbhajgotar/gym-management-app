<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Pages\WhatsApp\Broadcast;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppBroadcastValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);

        $this->user = User::factory()
            ->create([
                'name' => 'Test Owner',
                'email' => 'owner@example.com',
            ])
            ->assignRole(DefaultRole::GYM_OWNER->value);

        $this->gym = Gym::factory()->create();
        $this->user->gyms()->attach($this->gym);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'balance' => 100,
            'total_purchased' => 100,
            'total_used' => 0,
        ]);
    }

    public function test_template_id_is_required(): void
    {
        Livewire::test(Broadcast::class)
            ->fillForm([
                'template_id' => null,
                'recipient_type' => 'all',
            ])
            ->call('sendBroadcast')
            ->assertHasFormErrors(['template_id']);
    }

    public function test_scheduled_at_is_required_when_schedule_type_is_later(): void
    {
        Livewire::test(Broadcast::class)
            ->fillForm([
                'template_id' => null,
                'recipient_type' => 'all',
                'schedule_type' => 'later',
                'scheduled_at' => null,
            ])
            ->call('sendBroadcast')
            ->assertHasFormErrors(['scheduled_at']);
    }

    public function test_broadcast_fails_when_no_members_selected_in_filtered_mode(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => true,
        ]);

        Livewire::test(Broadcast::class)
            ->fillForm([
                'template_id' => $template->id,
                'recipient_type' => 'filtered',
                'schedule_type' => 'now',
            ])
            ->set('selectedMemberIds', [])
            ->set('targetCount', 0)
            ->call('sendBroadcast')
            ->assertNotified('Broadcast Failed');
    }

    public function test_broadcast_sends_successfully_with_valid_data(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => true,
        ]);

        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        Livewire::test(Broadcast::class)
            ->fillForm([
                'template_id' => $template->id,
                'recipient_type' => 'all',
                'schedule_type' => 'now',
            ])
            ->call('sendBroadcast')
            ->assertNotified('Broadcast Started!');
    }
}
