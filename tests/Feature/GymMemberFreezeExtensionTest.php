<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\GymMemberResource\Pages\ListGymMembers;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\GymSetting;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\MemberFrozenNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GymMemberFreezeExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        GymSetting::query()->create([
            'gym_id' => $this->gym->id,
            'key' => 'min_freeze_days',
            'value' => '7',
            'type' => 'integer',
        ]);

        GymSetting::query()->create([
            'gym_id' => $this->gym->id,
            'key' => 'max_freeze_days',
            'value' => '60',
            'type' => 'integer',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_freeze_with_extend_membership_true_and_valid_days_extends_plan_and_payment_end_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'duration' => 1,
        ]);

        $initialEnd = Carbon::parse('2026-10-01');

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $plan->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'is_frozen' => false,
        ]);

        /** @var MemberPayment $payment */
        $payment = MemberPayment::factory()->create([
            'gym_id' => $this->gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'status' => \App\Enums\PaymentStatus::COMPLETED,
        ]);

        // Freeze for 15 days with extend_membership = true
        $member->freeze(reason: 'Vacation', days: 15, extendMembership: true);

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertTrue($freshMember->is_frozen);
        $this->assertTrue($freshMember->extend_membership);
        $this->assertEquals('2026-10-16', $freshMember->membership_end?->toDateString());

        $freshPayment = $payment->fresh();
        $this->assertNotNull($freshPayment);
        $this->assertEquals('2026-10-16', $freshPayment->membership_end?->toDateString());
    }

    public function test_freeze_with_extend_membership_false_does_not_extend_plan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $initialEnd = Carbon::parse('2026-10-01');

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'is_frozen' => false,
        ]);

        // Freeze for 15 days with extend_membership = false
        $member->freeze(reason: 'Vacation', days: 15, extendMembership: false);

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertTrue($freshMember->is_frozen);
        $this->assertFalse($freshMember->extend_membership);
        $this->assertEquals('2026-10-01', $freshMember->membership_end?->toDateString());
    }

    public function test_freeze_below_min_freeze_days_does_not_extend_plan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $initialEnd = Carbon::parse('2026-10-01');

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'is_frozen' => false,
        ]);

        // Freeze for 3 days (below min_freeze_days threshold of 7) with extend_membership = true
        $member->freeze(reason: 'Short trip', days: 3, extendMembership: true);

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertTrue($freshMember->is_frozen);
        $this->assertEquals('2026-10-01', $freshMember->membership_end?->toDateString());
    }

    public function test_freeze_exceeding_max_freeze_days_is_capped_at_max_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'duration' => 6, // 6 months plan allows max_freeze_days setting limit of 60
        ]);

        $initialEnd = Carbon::parse('2026-10-01');

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $plan->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'is_frozen' => false,
        ]);

        // Freeze for 90 days (above max_freeze_days threshold of 60) with extend_membership = true
        $member->freeze(reason: 'Long travel', days: 90, extendMembership: true);

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertTrue($freshMember->is_frozen);
        // Extension should be capped at max_freeze_days = 60
        $this->assertEquals('2026-11-30', $freshMember->membership_end?->toDateString());
    }

    /**
     * Test Case 1: Checkbox checked but days not entered (manual / indefinite freeze)
     */
    public function test_checkbox_checked_with_no_days_entered_extends_plan_on_unfreeze(): void
    {
        $initialEnd = Carbon::parse('2026-10-01');
        $freezeDate = Carbon::parse('2026-09-10');

        Carbon::setTestNow($freezeDate);

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'is_frozen' => false,
        ]);

        // Checkbox checked, but days left empty (null)
        $member->freeze(reason: 'Manual Freeze', days: null, extendMembership: true);

        // Initially end date is untouched
        $this->assertEquals('2026-10-01', $member->fresh()?->membership_end?->toDateString());

        // Fast-forward 10 days and unfreeze
        Carbon::setTestNow($freezeDate->copy()->addDays(10));
        $member->unfreeze();

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertFalse($freshMember->is_frozen);
        // 10 days frozen >= min 7 days -> extended by +10 days -> Oct 11
        $this->assertEquals('2026-10-11', $freshMember->membership_end?->toDateString());
    }

    /**
     * Test Case 2: 15 days freeze created, but member returns early after 10 days
     */
    public function test_member_frozen_for_15_days_returns_early_after_10_days(): void
    {
        $initialEnd = Carbon::parse('2026-10-01');
        $freezeDate = Carbon::parse('2026-09-10');

        Carbon::setTestNow($freezeDate);

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'is_frozen' => false,
        ]);

        // Freeze for 15 days upfront with checkbox checked -> pre-extends to 2026-10-16
        $member->freeze(reason: 'Vacation', days: 15, extendMembership: true);
        $this->assertEquals('2026-10-16', $member->fresh()?->membership_end?->toDateString());

        // Member returns early on day 10 (Sep 20)
        Carbon::setTestNow($freezeDate->copy()->addDays(10));
        $member->unfreeze();

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertFalse($freshMember->is_frozen);
        // Frozen for 10 days (>= min 7 days) -> net extension is +10 days -> 2026-10-11
        $this->assertEquals('2026-10-11', $freshMember->membership_end?->toDateString());
    }

    /**
     * Test Case 3: Max days setting is 60, but member's plan duration is only 1 month (30 days)
     */
    public function test_freeze_extension_is_capped_by_one_month_plan_duration_even_if_max_freeze_setting_is_60(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        // 1-month plan (duration = 1 -> 30 days)
        $oneMonthPlan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'duration' => 1,
        ]);

        $initialEnd = Carbon::parse('2026-10-01');

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $oneMonthPlan->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'is_frozen' => false,
        ]);

        // Max freeze setting is 60, but member requests 45 days freeze on a 1-month plan
        $member->freeze(reason: 'Long Vacation', days: 45, extendMembership: true);

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertTrue($freshMember->is_frozen);
        // Plan extension should be capped at min(maxSetting:60, planDays:30) = 30 days -> 2026-10-31
        $this->assertEquals('2026-10-31', $freshMember->membership_end?->toDateString());
    }

    public function test_late_unfreeze_after_frozen_until_passed_adjusts_membership_end(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // Freeze for 15 days -> pre-extends end to Oct 16 (frozen_until = Sep 25)
        $member->freeze(reason: 'Vacation', days: 15, extendMembership: true);
        $this->assertEquals('2026-10-16', $member->fresh()?->membership_end?->toDateString());

        // Fast-forward to Sep 30 (day 20, 5 days after frozen_until has passed)
        Carbon::setTestNow($freezeDate->copy()->addDays(20));
        $member->unfreeze();

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertFalse($freshMember->is_frozen);
        // Frozen for 20 actual days -> net extension is +20 days -> 2026-10-21
        $this->assertEquals('2026-10-21', $freshMember->membership_end?->toDateString());
    }

    public function test_refreeze_cycle_after_unfreeze(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // First freeze: 10 days
        $member->freeze(reason: 'Trip 1', days: 10, extendMembership: true);
        $this->assertEquals('2026-10-11', $member->fresh()?->membership_end?->toDateString());

        // Unfreeze on day 10
        Carbon::setTestNow($freezeDate->copy()->addDays(10));
        $member->unfreeze();
        $this->assertFalse($member->fresh()?->is_frozen);

        // Second freeze: 10 days on Sep 25
        Carbon::setTestNow($freezeDate->copy()->addDays(15));
        $member->freeze(reason: 'Trip 2', days: 10, extendMembership: true);
        $this->assertEquals('2026-10-21', $member->fresh()?->membership_end?->toDateString());
    }

    public function test_freeze_boundary_equality_min_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // Boundary test: days == min_freeze_days (7)
        $member->freeze(reason: 'Min Boundary', days: 7, extendMembership: true);
        $this->assertEquals('2026-10-08', $member->fresh()?->membership_end?->toDateString());
    }

    public function test_freeze_boundary_equality_max_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'duration' => 6,
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $plan->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // Boundary test: days == max_freeze_days (60)
        $member->freeze(reason: 'Max Boundary', days: 60, extendMembership: true);
        $this->assertEquals('2026-11-30', $member->fresh()?->membership_end?->toDateString());
    }

    public function test_double_unfreeze_is_idempotent(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // Unfreezing an already unfrozen member returns false and changes nothing
        $result = $member->unfreeze();
        $this->assertFalse($result);
        $this->assertEquals('2026-10-01', $member->fresh()?->membership_end?->toDateString());
    }

    public function test_freeze_with_null_membership_end_does_not_fail(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => null,
            'is_frozen' => false,
        ]);

        $result = $member->freeze(reason: 'No end date', days: 10, extendMembership: true);
        $this->assertTrue($result);
        $this->assertTrue($member->fresh()?->is_frozen);
        $this->assertNull($member->fresh()?->membership_end);

        $unfreezeResult = $member->unfreeze();
        $this->assertTrue($unfreezeResult);
        $this->assertFalse($member->fresh()?->is_frozen);
        $this->assertNull($member->fresh()?->membership_end);
    }

    public function test_manual_freeze_with_extend_membership_false_does_not_extend_on_unfreeze(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        $member->freeze(reason: 'Manual without extend', days: null, extendMembership: false);

        Carbon::setTestNow($freezeDate->copy()->addDays(15));
        $member->unfreeze();

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertFalse($freshMember->is_frozen);
        $this->assertEquals('2026-10-01', $freshMember->membership_end?->toDateString());
    }

    public function test_auto_unfreeze_if_expired_unfreezes_past_frozen_until(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        $member->freeze(reason: 'Trip', days: 10, extendMembership: true);
        $this->assertEquals('2026-10-11', $member->fresh()?->membership_end?->toDateString());

        // Fast forward past frozen_until (Sep 20) to Sep 25 (15 days actual)
        Carbon::setTestNow($freezeDate->copy()->addDays(15));

        $this->assertFalse($member->fresh()?->isFrozen());
        $this->assertTrue($member->fresh()?->autoUnfreezeIfExpired());

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertFalse($freshMember->is_frozen);
        $this->assertEquals('2026-10-16', $freshMember->membership_end?->toDateString());
    }

    public function test_check_membership_expiry_command_auto_unfreezes_expired_freezes(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        $member->freeze(reason: 'Trip', days: 10, extendMembership: true);

        // Fast forward to Sep 25
        Carbon::setTestNow($freezeDate->copy()->addDays(15));

        $this->artisan('gym:check-membership-expiry')
            ->assertSuccessful();

        $this->assertFalse($member->fresh()?->is_frozen);
    }

    public function test_zombie_refreeze_after_freeze_expires_reconciles_before_new_freeze(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // Initial freeze for 10 days -> pre-extends to Oct 11 (frozen_until = Sep 20)
        $member->freeze(reason: 'Trip 1', days: 10, extendMembership: true);
        $this->assertEquals('2026-10-11', $member->fresh()?->membership_end?->toDateString());

        // Fast-forward to Sep 25 (15 days actual, frozen_until has passed -> zombie state before cron)
        Carbon::setTestNow($freezeDate->copy()->addDays(15));
        $this->assertFalse($member->fresh()?->isFrozen());
        $this->assertTrue($member->fresh()?->is_frozen);

        // Re-freeze without manual unfreeze: should auto-reconcile trip 1 (15 actual days -> Oct 16) and apply trip 2 (10 days -> Oct 26)
        $result = $member->freeze(reason: 'Trip 2', days: 10, extendMembership: true);
        $this->assertTrue($result);

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertTrue($freshMember->is_frozen);
        $this->assertEquals('2026-10-26', $freshMember->membership_end?->toDateString());
    }

    public function test_freeze_when_already_actively_frozen_returns_false(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        $member->freeze(reason: 'Trip 1', days: 10, extendMembership: true);
        $this->assertTrue($member->fresh()?->isFrozen());

        // Second freeze while still actively frozen
        $result = $member->freeze(reason: 'Double click freeze', days: 10, extendMembership: true);
        $this->assertFalse($result);
        $this->assertEquals('2026-10-11', $member->fresh()?->membership_end?->toDateString());
    }

    public function test_manual_freeze_is_not_auto_unfrozen(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // Manual freeze (days: null)
        $member->freeze(reason: 'Indefinite', days: null, extendMembership: true);
        $this->assertTrue($member->fresh()?->isFrozen());
        $this->assertNull($member->fresh()?->frozen_until);

        // Fast-forward 30 days
        Carbon::setTestNow($freezeDate->copy()->addDays(30));

        $this->assertTrue($member->fresh()?->isFrozen());
        $this->assertFalse($member->fresh()?->autoUnfreezeIfExpired());
        $this->assertTrue($member->fresh()?->is_frozen);
    }

    public function test_freeze_below_min_freeze_days_with_manual_unfreeze_does_not_extend(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // Manual freeze (days: null) with extend_membership: true
        $member->freeze(reason: 'Quick Trip', days: null, extendMembership: true);

        // Member returns after only 4 days (below min 7 days)
        Carbon::setTestNow($freezeDate->copy()->addDays(4));
        $member->unfreeze();

        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertFalse($freshMember->is_frozen);
        // Did not meet minimum 7 days -> membership_end remains unchanged
        $this->assertEquals('2026-10-01', $freshMember->membership_end?->toDateString());
    }

    public function test_payment_status_expired_is_skipped_during_membership_end_sync(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $initialEnd = Carbon::parse('2026-10-01');

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'is_frozen' => false,
        ]);

        $payment = MemberPayment::factory()->create([
            'gym_id' => $this->gym->id,
            'member_id' => $member->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => $initialEnd->copy(),
            'status' => PaymentStatus::EXPIRED,
        ]);

        $member->freeze(reason: 'Trip', days: 10, extendMembership: true);

        $freshPayment = $payment->fresh();
        $this->assertNotNull($freshPayment);
        // Expired payment should not be modified
        $this->assertEquals('2026-10-01', $freshPayment->membership_end?->toDateString());
    }

    public function test_check_membership_expiry_dry_run_does_not_modify_records(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        $member->freeze(reason: 'Trip', days: 10, extendMembership: true);

        // Fast forward to Sep 25 (past frozen_until of Sep 20)
        Carbon::setTestNow($freezeDate->copy()->addDays(15));

        $initialMemberEnd = $member->fresh()?->membership_end?->toDateTimeString();
        $initialFrozenUntil = $member->fresh()?->frozen_until?->toDateTimeString();

        $this->artisan('gym:check-membership-expiry', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN]')
            ->assertSuccessful();

        // In dry-run mode, member should remain frozen and DB values must be unchanged
        $freshMember = $member->fresh();
        $this->assertNotNull($freshMember);
        $this->assertTrue($freshMember->is_frozen);
        $this->assertEquals($initialMemberEnd, $freshMember->membership_end?->toDateTimeString());
        $this->assertEquals($initialFrozenUntil, $freshMember->frozen_until?->toDateTimeString());
        $this->assertDatabaseHas('gym_members', [
            'id' => $member->id,
            'is_frozen' => true,
        ]);
    }

    public function test_check_membership_expiry_multi_tenant_isolation(): void
    {
        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        $gym2 = Gym::factory()->create();

        // Member of gym 1 (frozen for 10 days, expired on Sep 20)
        $member1 = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);
        $member1->freeze(reason: 'Gym 1 Trip', days: 10, extendMembership: true);

        // Member of gym 2 (active freeze, expires on Sep 30)
        $member2 = GymMember::factory()->create([
            'gym_id' => $gym2->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);
        $member2->freeze(reason: 'Gym 2 Trip', days: 20, extendMembership: true);

        // Fast-forward to Sep 25
        Carbon::setTestNow($freezeDate->copy()->addDays(15));

        $this->artisan('gym:check-membership-expiry')
            ->assertSuccessful();

        // Gym 1 member is expired and auto-unfrozen
        $this->assertFalse($member1->fresh()?->is_frozen);

        // Gym 2 member is still within their 20-day freeze and remains frozen
        $this->assertTrue($member2->fresh()?->is_frozen);
    }

    public function test_notification_is_sent_with_correct_payload_on_freeze_and_unfreeze(): void
    {
        Notification::fake();

        $freezeDate = Carbon::parse('2026-09-10');
        Carbon::setTestNow($freezeDate);

        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        // Freeze
        $member->freeze(reason: 'Medical Leave', days: 14, extendMembership: true);

        Notification::assertSentTo(
            $member,
            MemberFrozenNotification::class,
            function (MemberFrozenNotification $notification) {
                return $notification->isFrozen === true
                    && $notification->reason === 'Medical Leave'
                    && $notification->frozenUntil?->toDateString() === '2026-09-24';
            }
        );

        // Unfreeze
        $member->unfreeze();

        Notification::assertSentTo(
            $member,
            MemberFrozenNotification::class,
            function (MemberFrozenNotification $notification) {
                return $notification->isFrozen === false;
            }
        );
    }

    public function test_activity_log_is_recorded_on_freeze_and_unfreeze(): void
    {
        $user = User::factory()->create();
        $user->gyms()->attach($this->gym->id);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => Carbon::parse('2026-10-01'),
            'is_frozen' => false,
        ]);

        $member->freeze(reason: 'Travel', days: 10, frozenById: $user->id, extendMembership: true);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'gym_member',
            'description' => 'Member frozen',
            'subject_id' => $member->id,
            'causer_id' => $user->id,
        ]);

        $member->unfreeze(unfrozenById: $user->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'gym_member',
            'description' => 'Member unfrozen',
            'subject_id' => $member->id,
            'causer_id' => $user->id,
        ]);
    }

    private function grantGymMemberPermissions(User $user, Role $role): void
    {
        foreach (['view_any_gym_member', 'view_gym_member', 'update_gym_member'] as $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($permission);
            $user->givePermissionTo($permission);
        }
    }

    public function test_filament_toggle_freeze_action_authorization_per_role(): void
    {
        setPermissionsTeamId($this->gym->id);

        $rolesWithAccess = [
            DefaultRole::SUPER_ADMIN,
            DefaultRole::ADMIN,
            DefaultRole::GYM_OWNER,
            DefaultRole::MANAGER,
            DefaultRole::RECEPTIONIST,
        ];

        foreach ($rolesWithAccess as $defaultRole) {
            $role = Role::findOrCreate($defaultRole->value, 'web');
            $user = User::factory()->create();
            $user->gyms()->attach($this->gym->id, ['role' => $defaultRole->value]);
            $user->assignRole($role);
            $this->grantGymMemberPermissions($user, $role);

            $member = GymMember::factory()->create([
                'gym_id' => $this->gym->id,
                'membership_start' => Carbon::parse('2026-09-01'),
                'membership_end' => now()->addDays(30),
                'is_frozen' => false,
            ]);

            $this->actingAs($user);
            Filament::setTenant($this->gym);
            Filament::setCurrentPanel(Filament::getPanel('admin'));

            Livewire::actingAs($user)
                ->test(ListGymMembers::class)
                ->callTableAction('toggle_freeze', $member, [
                    'reason' => 'Role test',
                    'days' => 10,
                    'extend_membership' => true,
                ])
                ->assertHasNoTableActionErrors();

            $this->assertTrue($member->fresh()?->is_frozen);
        }
    }

    public function test_filament_toggle_freeze_action_hidden_or_unauthorized_for_trainer_role(): void
    {
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate(DefaultRole::TRAINER->value, 'web');
        $user = User::factory()->create();
        $user->gyms()->attach($this->gym->id, ['role' => DefaultRole::TRAINER->value]);
        $user->assignRole($role);
        $this->grantGymMemberPermissions($user, $role);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => Carbon::parse('2026-09-01'),
            'membership_end' => now()->addDays(30),
            'is_frozen' => false,
        ]);

        $this->actingAs($user);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test(ListGymMembers::class)
            ->assertTableActionHidden('toggle_freeze', $member);
    }
}
