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

class WhatsAppSandboxTestMessageTest extends TestCase
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

    public function test_send_test_message_fails_when_sandbox_is_not_enabled(): void
    {
        config(['services.twilio.use_sandbox' => false]);

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
                'recipient_type' => 'all',
                'variables' => ['John'],
            ])
            ->set('targetCount', 1)
            ->call('sendTestMessage')
            ->assertNotified('Sandbox mode is not enabled');
    }

    public function test_send_test_message_fails_when_no_template_selected(): void
    {
        config(['services.twilio.use_sandbox' => true]);

        Livewire::test(Broadcast::class)
            ->fillForm([
                'template_id' => null,
                'recipient_type' => 'all',
            ])
            ->set('targetCount', 1)
            ->call('sendTestMessage')
            ->assertNotified('Please select a template');
    }

    public function test_send_test_message_fails_when_no_recipients(): void
    {
        config(['services.twilio.use_sandbox' => true]);

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
                'recipient_type' => 'all',
            ])
            ->set('targetCount', 0)
            ->call('sendTestMessage')
            ->assertNotified('No recipients found');
    }

    public function test_send_test_message_succeeds_with_sandbox_enabled(): void
    {
        config(['services.twilio.use_sandbox' => true]);
        config(['services.twilio.sandbox_number' => '+15557626186']);

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
            'name' => 'John Doe',
        ]);

        Livewire::test(Broadcast::class)
            ->fillForm([
                'template_id' => $template->id,
                'recipient_type' => 'all',
                'variables' => ['John'],
            ])
            ->set('targetCount', 1)
            ->set('selectedMemberIds', [])
            ->call('sendTestMessage')
            ->assertNotified('Test Message Sent!');
    }

    public function test_send_test_message_uses_selected_member_phone_when_available(): void
    {
        config(['services.twilio.use_sandbox' => true]);
        config(['services.twilio.sandbox_number' => '+15557626186']);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_active' => true,
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1987654321',
            'name' => 'Jane Doe',
        ]);

        Livewire::test(Broadcast::class)
            ->fillForm([
                'template_id' => $template->id,
                'recipient_type' => 'filtered',
                'variables' => ['Jane'],
            ])
            ->set('targetCount', 1)
            ->set('selectedMemberIds', [$member->id])
            ->call('sendTestMessage')
            ->assertNotified('Test Message Sent!');
    }
}
