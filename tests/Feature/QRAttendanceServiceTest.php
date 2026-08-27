<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\GymMember;
use App\Services\QRAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QRAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_member_by_id_with_integer_search_value(): void
    {
        $member = GymMember::factory()->create();
        $service = new QRAttendanceService();

        // Pass member ID as integer
        $found = $service->findMember($member->gym_id, $member->id, 'member_id');

        $this->assertNotNull($found);
        $this->assertEquals($member->id, $found->id);
    }

    public function test_find_member_by_phone_with_string_search_value(): void
    {
        $member = GymMember::factory()->create();
        $service = new QRAttendanceService();

        $found = $service->findMember($member->gym_id, $member->phone, 'phone');

        $this->assertNotNull($found);
        $this->assertEquals($member->id, $found->id);
    }

    public function test_check_in_with_integer_member_id(): void
    {
        $member = GymMember::factory()->create([
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_frozen' => false,
        ]);
        $service = new QRAttendanceService();

        $result = $service->checkIn([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'identifier' => 'member_id',
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_check_out_with_integer_member_id(): void
    {
        $member = GymMember::factory()->create([
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_frozen' => false,
        ]);
        $service = new QRAttendanceService();

        // Check-in first
        $service->checkIn([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'identifier' => 'member_id',
        ]);

        // Then check-out
        $result = $service->checkOut([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'identifier' => 'member_id',
        ]);

        $this->assertTrue($result['success']);
    }
}
