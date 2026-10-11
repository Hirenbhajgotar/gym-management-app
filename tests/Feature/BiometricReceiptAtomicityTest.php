<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\AdmsEventService;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricReceiptStatusEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Events\MemberCheckedIn;
use App\Models\Attendances;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricEventReceipt;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class BiometricReceiptAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        config(['biometrics.adms.require_comm_key' => false]);

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->device = BiometricDevice::factory()
            ->admsEnabled('TEST-SN-ATOMIC')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_frozen' => false,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);
    }

    public function test_attlog_event_creates_receipt_and_attendance_atomically(): void
    {
        Event::fake([MemberCheckedIn::class]);

        $timestamp = now()->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$timestamp}\t0\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-ATOMIC&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertOk();
        $this->assertEquals('OK', $response->getContent());

        $this->assertDatabaseHas('attendances', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'check_in_result' => 'granted',
        ]);

        $this->assertDatabaseHas('biometric_event_receipts', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => BiometricReceiptStatusEnum::PROCESSED->value,
        ]);
    }

    public function test_attlog_duplicate_event_does_not_create_duplicate_attendance(): void
    {
        Event::fake([MemberCheckedIn::class]);

        $timestamp = now()->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$timestamp}\t0\t15\t0";

        // First push
        $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-ATOMIC&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        )->assertOk();

        $this->assertEquals(1, Attendances::query()->where('device_id', $this->device->id)->count());
        $this->assertEquals(1, BiometricEventReceipt::query()->where('device_id', $this->device->id)->count());

        // Replay same event (retry by terminal)
        $replayResponse = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-ATOMIC&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $replayResponse->assertOk();
        $this->assertEquals('OK', $replayResponse->getContent());

        // Still exactly 1 attendance and 1 receipt
        $this->assertEquals(1, Attendances::query()->where('device_id', $this->device->id)->count());
        $this->assertEquals(1, BiometricEventReceipt::query()->where('device_id', $this->device->id)->count());
    }

    public function test_system_error_during_attlog_returns_err_retry_and_rolls_back(): void
    {
        $mockService = Mockery::mock(AdmsEventService::class);
        $mockService->shouldReceive('processEvent')
            ->andThrow(new Exception('Simulated database deadlock / network failure'));
        $this->app->instance(AdmsEventService::class, $mockService);

        $timestamp = now()->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$timestamp}\t0\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-ATOMIC&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertInternalServerError();
        $this->assertEquals('ERR: RETRY', $response->getContent());

        // Transaction rollback must ensure no attendance and no processed receipt
        $this->assertEquals(0, Attendances::query()->where('device_id', $this->device->id)->count());
        $this->assertEquals(0, BiometricEventReceipt::query()->where('device_id', $this->device->id)->count());
    }

    public function test_unsupported_table_returns_400(): void
    {
        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-ATOMIC&table=UNKNOWN_TABLE',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            'some-payload'
        );

        $response->assertStatus(400);
        $this->assertStringContainsString('ERR: Unsupported table', (string) $response->getContent());
    }

    public function test_attlog_batch_exceeding_line_limit_returns_413(): void
    {
        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');
        $lines = array_fill(0, 501, "{$this->member->id}\t{$timestamp}\t0\t15\t0");
        $body = implode("\n", $lines);

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-ATOMIC&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertStatus(413);
        $this->assertStringContainsString('ERR: Batch exceeds line limit', (string) $response->getContent());
    }

    public function test_timestamp_outside_clock_skew_window_is_rejected(): void
    {
        // Timestamp 2 hours in the future
        $futureTime = now()->addHours(2)->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$futureTime}\t0\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-ATOMIC&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertOk();
        // Since timestamp was invalid/outside skew, it's counted as a malformed line and not recorded
        $this->assertEquals(0, Attendances::query()->where('device_id', $this->device->id)->count());
    }
}
