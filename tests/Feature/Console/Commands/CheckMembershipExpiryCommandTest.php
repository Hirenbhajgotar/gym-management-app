<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckMembershipExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_marks_expired_members_as_expired(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        $this->artisan('gym:check-membership-expiry')
            ->assertExitCode(0);

        $this->assertDatabaseHas('gym_members', [
            'id' => $member->id,
            'payment_status' => PaymentStatus::EXPIRED->value,
        ]);
    }

    #[Test]
    public function it_does_not_mark_active_members_as_expired(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        $this->artisan('gym:check-membership-expiry')
            ->assertExitCode(0);

        $this->assertDatabaseHas('gym_members', [
            'id' => $member->id,
            'payment_status' => PaymentStatus::COMPLETED->value,
        ]);
    }

    #[Test]
    public function it_marks_members_with_null_payment_status_as_expired(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'payment_status' => null,
        ]);

        $this->artisan('gym:check-membership-expiry')
            ->assertExitCode(0);

        $this->assertDatabaseHas('gym_members', [
            'id' => $member->id,
            'payment_status' => PaymentStatus::EXPIRED->value,
        ]);
    }

    #[Test]
    public function it_marks_expired_member_payments(): void
    {
        $gym = Gym::factory()->create();
        $payment = MemberPayment::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'status' => PaymentStatus::COMPLETED,
        ]);

        $this->artisan('gym:check-membership-expiry')
            ->assertExitCode(0);

        $this->assertDatabaseHas('member_payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::EXPIRED->value,
        ]);
    }

    #[Test]
    public function it_does_not_mark_active_payments(): void
    {
        $gym = Gym::factory()->create();
        $payment = MemberPayment::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->addMonth(),
            'status' => PaymentStatus::COMPLETED,
        ]);

        $this->artisan('gym:check-membership-expiry')
            ->assertExitCode(0);

        $this->assertDatabaseHas('member_payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::COMPLETED->value,
        ]);
    }

    #[Test]
    public function it_skips_already_expired_records(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'payment_status' => PaymentStatus::EXPIRED,
        ]);
        $payment = MemberPayment::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'status' => PaymentStatus::EXPIRED,
        ]);

        $this->artisan('gym:check-membership-expiry')
            ->assertExitCode(0);

        $this->assertDatabaseHas('gym_members', [
            'id' => $member->id,
            'payment_status' => PaymentStatus::EXPIRED->value,
        ]);
        $this->assertDatabaseHas('member_payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::EXPIRED->value,
        ]);
    }

    #[Test]
    public function it_reports_when_no_expired_records_found(): void
    {
        $this->artisan('gym:check-membership-expiry')
            ->expectsOutputToContain('No gym members with expired memberships found.')
            ->expectsOutputToContain('No member payments with expired memberships found.')
            ->assertExitCode(0);
    }

    #[Test]
    public function dry_run_does_not_make_changes(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        $this->artisan('gym:check-membership-expiry --dry-run')
            ->expectsOutputToContain('[DRY-RUN]')
            ->assertExitCode(0);

        $this->assertDatabaseHas('gym_members', [
            'id' => $member->id,
            'payment_status' => PaymentStatus::COMPLETED->value,
        ]);
    }

    #[Test]
    public function dry_run_shows_correct_count(): void
    {
        $gym = Gym::factory()->create();
        GymMember::factory()->count(3)->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        $this->artisan('gym:check-membership-expiry --dry-run')
            ->expectsOutputToContain('[DRY-RUN] 3 gym member(s) would be marked as expired.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_expired_payments_with_null_status(): void
    {
        $gym = Gym::factory()->create();
        $payment = MemberPayment::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'status' => null,
        ]);

        $this->artisan('gym:check-membership-expiry')
            ->assertExitCode(0);

        $this->assertDatabaseHas('member_payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::EXPIRED->value,
        ]);
    }

    #[Test]
    public function it_handles_multiple_expired_members(): void
    {
        $gym = Gym::factory()->create();
        $members = GymMember::factory()->count(5)->create([
            'gym_id' => $gym->id,
            'membership_end' => now()->subDay(),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        $this->artisan('gym:check-membership-expiry')
            ->assertExitCode(0);

        foreach ($members as $member) {
            $this->assertDatabaseHas('gym_members', [
                'id' => $member->id,
                'payment_status' => PaymentStatus::EXPIRED->value,
            ]);
        }
    }
}
