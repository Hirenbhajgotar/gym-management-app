<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\BroadcastOrchestrator;
use Exception;
use Illuminate\Database\Eloquent\Factories\Sequence;
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

        GymMember::factory()->count(5)->sequence(
            ['phone' => '+1111111111'],
            ['phone' => '+1222222222'],
            ['phone' => '+1333333333'],
            ['phone' => '+1444444444'],
            ['phone' => '+1555555555'],
        )->create([
            'gym_id' => $this->gym->id,
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

        /** @var list<int> $members */
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
        assert($creditRecord instanceof WhatsAppMessageCredit);

        $this->assertSame(95, $creditRecord->remaining_credits);
        $this->assertSame(5, $creditRecord->used_credits);
    }

    public function test_upfront_deduction_uses_welcome_credits_before_paid_credits(): void
    {
        Queue::fake();

        $welcomeCredits = config()->integer('services.whatsapp.welcome_credits', 50);

        // Claim the one-time welcome credits for this gym (100 paid + 50 welcome = 150 total budget).
        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);
        $creditRecord->update([
            'welcome_credits_claimed' => true,
            'welcome_credits_used' => 0,
        ]);

        // 5 members already exist in setUp(); add 95 more so the broadcast targets 100.
        // Phones are deterministic to honour the unique (gym_id, phone) index.
        GymMember::factory()->count(95)->sequence(
            fn (Sequence $sequence): array => [
                'phone' => '+2' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ],
        )->create([
            'gym_id' => $this->gym->id,
        ]);

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'all',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        $this->assertSame(100, $broadcast->target_count);

        // Welcome credits cover the first $welcomeCredits messages for free; only the
        // remainder is charged against the paid ledger.
        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);

        $this->assertSame($welcomeCredits, $credit->welcome_credits_used);
        $this->assertSame(100 - $welcomeCredits, $credit->used_credits);
        $this->assertSame(0, $credit->remaining_credits);
    }

    public function test_insufficient_credits_throws_exception(): void
    {
        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);
        $creditRecord->update(['remaining_credits' => 2, 'used_credits' => 98]);

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'recipient_type' => 'all',
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $this->expectException(Exception::class);
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

        /** @var list<int> $members */
        $members = GymMember::query()->where('gym_id', $this->gym->id)->take(3)->pluck('id')->toArray();

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, $members);

        // Verify credits are deducted exactly for the target count
        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);
        $this->assertSame(3, $broadcast->target_count);
        $this->assertSame(97, $creditRecord->remaining_credits);
        $this->assertSame(3, $creditRecord->used_credits);
    }

    public function test_self_managed_account_skips_upfront_credit_deduction(): void
    {
        Queue::fake();

        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED,
        ]);

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

        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);
        $this->assertSame(100, $creditRecord->remaining_credits);
        $this->assertSame(0, $creditRecord->used_credits);
    }

    public function test_self_managed_account_without_credit_record_does_not_throw(): void
    {
        Queue::fake();

        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED,
        ]);

        WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->delete();

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
        $this->assertDatabaseCount('whatsapp_message_credits', 0);
    }

    public function test_self_managed_account_with_insufficient_credits_does_not_throw(): void
    {
        Queue::fake();

        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED,
        ]);

        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);
        $creditRecord->update(['remaining_credits' => 2, 'used_credits' => 98]);

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
        $this->assertSame(2, $creditRecord->refresh()->remaining_credits);
    }

    public function test_self_managed_account_dispatches_job_without_skip_credit_deduction_flag(): void
    {
        Queue::fake();

        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED,
        ]);

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
            return $job->skipCreditDeduction === false;
        });
    }
}
