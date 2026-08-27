<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BroadcastOrchestrator::class)]
final class WhatsAppBroadcastOrchestratorTest extends TestCase
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
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
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

    #[Test]
    public function it_throws_exception_when_twilio_sandbox_mode_is_enabled(): void
    {
        config(['services.twilio.use_sandbox' => true]);

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Broadcasts are disabled in sandbox mode. Please disable sandbox mode to send broadcasts.');

        $this->orchestrator->createAndDispatch($data, $this->gym->id, []);
    }

    #[Test]
    public function it_creates_broadcast_record(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
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

    #[Test]
    public function it_dispatches_send_broadcast_job(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        Queue::assertPushed(SendBroadcastJob::class);
    }

    #[Test]
    public function it_schedules_broadcast_for_later(): void
    {
        Queue::fake();

        $scheduledAt = now()->addHour();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
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

    #[Test]
    public function it_creates_broadcast_with_selected_members(): void
    {
        Queue::fake();

        /** @var list<int> $members */
        $members = GymMember::query()->where('gym_id', $this->gym->id)->take(3)->pluck('id')->toArray();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
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

    #[Test]
    public function it_creates_broadcast_with_variables(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => ['name' => '[member_name]'],
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        $this->assertEquals(['name' => '[member_name]'], $broadcast->message_variables);
    }

    #[Test]
    public function it_records_credits_used_on_upfront_credit_deduction(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        $this->assertSame(5, $broadcast->target_count);
        $this->assertSame(5, $broadcast->credits_used);

        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);

        $this->assertSame(95, $creditRecord->remaining_credits);
        $this->assertSame(5, $creditRecord->used_credits);
    }

    #[Test]
    public function it_uses_welcome_credits_before_paid_credits_on_upfront_deduction(): void
    {
        Queue::fake();

        $welcomeCredits = config()->integer('services.whatsapp.welcome_credits', 50);

        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);
        $creditRecord->update([
            'welcome_credits_claimed' => true,
            'welcome_credits_used' => 0,
        ]);

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
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        $this->assertSame(100, $broadcast->target_count);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);

        $this->assertSame($welcomeCredits, $credit->welcome_credits_used);
        $this->assertSame(100 - $welcomeCredits, $credit->used_credits);
        $this->assertSame(0, $credit->remaining_credits);
    }

    #[Test]
    public function it_throws_exception_when_credits_are_insufficient(): void
    {
        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);
        $creditRecord->update(['remaining_credits' => 2, 'used_credits' => 98]);

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient credits');

        $this->orchestrator->createAndDispatch($data, $this->gym->id, []);
    }

    #[Test]
    public function it_dispatches_job_with_skip_credit_deduction_flag(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        Queue::assertPushed(function (SendBroadcastJob $job): bool {
            return $job->skipCreditDeduction === true;
        });
    }

    #[Test]
    public function it_deducts_credits_atomically(): void
    {
        Queue::fake();

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        /** @var list<int> $members */
        $members = GymMember::query()->where('gym_id', $this->gym->id)->take(3)->pluck('id')->toArray();

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, $members);

        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($creditRecord instanceof WhatsAppMessageCredit);
        $this->assertSame(3, $broadcast->target_count);
        $this->assertSame(97, $creditRecord->remaining_credits);
        $this->assertSame(3, $creditRecord->used_credits);
    }

    #[Test]
    public function it_skips_upfront_credit_deduction_for_self_managed_account(): void
    {
        Queue::fake();

        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED,
        ]);

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
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

    #[Test]
    public function it_does_not_throw_for_self_managed_account_without_credit_record(): void
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
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        $this->assertSame(5, $broadcast->target_count);
        $this->assertDatabaseCount('whatsapp_message_credits', 0);
    }

    #[Test]
    public function it_does_not_throw_for_self_managed_account_with_insufficient_credits(): void
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
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [],
        ];

        $broadcast = $this->orchestrator->createAndDispatch($data, $this->gym->id, []);

        $this->assertSame(5, $broadcast->target_count);
        $this->assertSame(2, $creditRecord->refresh()->remaining_credits);
    }

    #[Test]
    public function it_dispatches_job_without_skip_credit_deduction_flag_for_self_managed_account(): void
    {
        Queue::fake();

        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED,
        ]);

        $data = [
            'template_id' => $this->template->id,
            'variables' => [],
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
