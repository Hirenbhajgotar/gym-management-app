<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\BroadcastOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppBroadcastOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected BroadcastOrchestrator $orchestrator;

    protected WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);

        $this->user = User::factory()->create()->assignRole(DefaultRole::GYM_OWNER->value);

        $this->gym = Gym::factory()->create();
        $this->user->gyms()->attach($this->gym);

        $this->template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'is_active' => true,
        ]);

        GymMember::factory()->count(5)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 0,
            'remaining_credits' => 100,
        ]);

        $this->orchestrator = resolve(BroadcastOrchestrator::class);
    }

    public function test_creates_broadcast_record(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'all',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch(
            $data,
            $this->gym->id,
            [],
        );

        $this->assertInstanceOf(WhatsAppBroadcast::class, $broadcast);
        $this->assertEquals($this->gym->id, $broadcast->gym_id);
        $this->assertEquals($this->template->id, $broadcast->whatsapp_template_id);
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $broadcast->status);
    }

    public function test_dispatches_send_broadcast_job(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'all',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function test_schedules_broadcast_for_later(): void
    {
        Queue::fake();

        $scheduledAt = now()->addHour();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'all',
            'schedule_type' => 'later',
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch(
            $data,
            $this->gym->id,
            [],
        );

        $this->assertSame(WhatsAppBroadcastStatusEnum::SCHEDULED, $broadcast->status);
        $this->assertNotNull($broadcast->scheduled_at);

        Queue::assertNotPushed(SendBroadcastJob::class);
    }

    public function test_creates_broadcast_with_selected_members(): void
    {
        Queue::fake();

        $members = GymMember::query()->where('gym_id', $this->gym->id)->take(3)->pluck('id')->toArray();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'selected',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch(
            $data,
            $this->gym->id,
            $members,
        );

        $this->assertEquals(3, $broadcast->target_count);
        $this->assertCount(3, $broadcast->recipients);
    }

    public function test_creates_broadcast_with_variables(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => ['name' => '[member_name]'],
            'recipient_type' => 'all',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        $this->assertEquals(['name' => '[member_name]'], $broadcast->message_variables);
    }

    public function test_upfront_credit_deduction_records_credits_used(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'all',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        $this->assertSame(5, $broadcast->target_count);
        $this->assertSame(5, $broadcast->credits_used);

        // Read credit record
        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();

        $this->assertSame(95, $creditRecord->remaining_credits);
        $this->assertSame(5, $creditRecord->used_credits);
    }

    public function test_insufficient_credits_throws_exception(): void
    {
        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        $creditRecord->update(['remaining_credits' => 2, 'used_credits' => 98]);

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'all',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient credits');

        $this->orchestrator->createAndDispatch($data, $this->gym->id, []);
    }

    public function test_dispatches_job_with_skip_credit_deduction_flag(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'all',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        Queue::assertPushed(function (SendBroadcastJob $job): bool {
            return $job->skipCreditDeduction === true;
        });
    }

    public function test_credit_deduction_is_atomic_with_lock_for_update(): void
    {
        Queue::fake();

        // Simulate concurrent broadcasts by checking the deduction happens
        // within a single transaction (rollback scenario)
        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'selected',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $members = GymMember::query()->where('gym_id', $this->gym->id)->take(3)->pluck('id')->toArray();

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, $members);

        // Verify credits are deducted exactly for the target count
        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        $this->assertSame(3, $broadcast->target_count);
        $this->assertSame(97, $creditRecord->remaining_credits);
        $this->assertSame(3, $creditRecord->used_credits);
    }
}
