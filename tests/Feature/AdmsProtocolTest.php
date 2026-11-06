<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricTimestampParser;
use App\Biometrics\Services\RedisBiometricCacheService;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Events\AdmsAccessDenied;
use App\Events\MemberCheckedIn;
use App\Events\MemberCheckedOut;
use App\Models\AdmsDeviceCommand;
use App\Models\Attendances;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricEventReceipt;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\GymSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdmsProtocolTest extends TestCase
{
    use RefreshDatabase;

    /** Secret every webhook-capable device in this suite is provisioned with. */
    private const WEBHOOK_SECRET = 'test-webhook-secret';

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
            ->admsEnabled('TEST-SN-001')
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

    public function test_adms_cdata_processes_attlog_check_in(): void
    {
        Event::fake([MemberCheckedIn::class]);

        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$timestamp}\t0\t15\t0";

        // Send raw body
        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
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
            'provider_type' => 'adms',
        ]);

        Event::assertDispatched(MemberCheckedIn::class);
    }

    public function test_adms_cdata_auto_detects_checkout(): void
    {
        Event::fake([MemberCheckedIn::class, MemberCheckedOut::class]);
        Cache::flush();

        $checkInTime = now()->subHours(4)->format('Y-m-d H:i:s');
        $checkOutTime = now()->subHours(1)->format('Y-m-d H:i:s');

        // First event: check-in
        $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "{$this->member->id}\t{$checkInTime}\t0\t15\t0"
        );

        // Second event: same member, status=0 but should auto-detect as checkout
        $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "{$this->member->id}\t{$checkOutTime}\t0\t15\t0"
        );

        Event::assertDispatched(MemberCheckedOut::class);
    }

    public function test_adms_cdata_denies_expired_member(): void
    {
        Event::fake([AdmsAccessDenied::class]);

        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(2),
            'membership_end' => now()->subDays(5),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_frozen' => false,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $expiredMember->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "{$expiredMember->id}\t{$timestamp}\t0\t15\t0"
        );

        $response->assertOk();
        $this->assertEquals('OK', $response->getContent());

        Event::assertDispatched(AdmsAccessDenied::class);
    }

    public function test_adms_cdata_unknown_device_serial_returns_error(): void
    {
        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=UNKNOWN-SERIAL&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "42\t{$timestamp}\t0\t15\t0"
        );

        $response->assertUnauthorized();
        $this->assertStringContainsString('ERR', (string) $response->getContent());
    }

    public function test_adms_cdata_anti_passback_prevents_duplicate(): void
    {
        Event::fake([MemberCheckedIn::class]);

        // Pre-acquire the anti-passback lock using the cache service
        resolve(RedisBiometricCacheService::class)
            ->acquireAntiPassbackLock($this->gym->id, $this->member->id, 120);

        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "{$this->member->id}\t{$timestamp}\t0\t15\t0"
        );

        $response->assertOk();

        // No attendance created due to anti-passback
        Event::assertNotDispatched(MemberCheckedIn::class);
    }

    public function test_adms_cdata_handles_essl_device(): void
    {
        Event::fake([MemberCheckedIn::class]);

        // Create a device with eSSL-style serial
        $esslDevice = BiometricDevice::factory()
            ->admsEnabled('ESSL-XFACE-500')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=ESSL-XFACE-500&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "{$this->member->id}\t{$timestamp}\t0\t1\t0"
        );

        $response->assertOk();

        $this->assertDatabaseHas('attendances', [
            'device_id' => $esslDevice->id,
            'provider_type' => 'adms',
        ]);

        Event::assertDispatched(MemberCheckedIn::class);
    }

    public function test_adms_getrequest_returns_pending_command(): void
    {
        $command = AdmsDeviceCommand::factory()->userDelete((string) $this->member->id)->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);

        $response = $this->get('/api/iclock/getrequest?SN=TEST-SN-001');

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringEndsWith("\r\n", $content);
        $this->assertFalse(str_ends_with($content, "\r\n\r\n"));
        $this->assertStringContainsString('DATA DELETE USERINFO', $content);
        $this->assertStringContainsString("PIN={$this->member->id}", $content);

        // Command should be marked as sent
        $this->assertDatabaseHas('adms_device_commands', [
            'id' => $command->id,
            'status' => 'sent',
        ]);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $command->fresh()?->status);
    }

    public function test_adms_getrequest_returns_ok_when_no_commands(): void
    {
        $response = $this->get('/api/iclock/getrequest?SN=TEST-SN-001');

        $response->assertOk();
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_adms_getrequest_quarantines_an_unaddressable_pin_without_consuming_an_attempt(): void
    {
        $command = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => "12\t3"],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $response = $this->get('/api/iclock/getrequest?SN=TEST-SN-001');

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $fresh = $command->fresh();
        $this->assertNotNull($fresh);
        // The defect is a property of the payload, so the row is terminalised once instead
        // of occupying the head of the lease window on every poll.
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $fresh->status);
        $this->assertSame(AdmsDeviceCommand::UNADDRESSABLE_PIN_CODE, $fresh->device_return_code);
        $this->assertSame(0, $fresh->attempt_count);

        // The next poll skips it and still answers OK.
        $this->assertSame('OK', $this->get('/api/iclock/getrequest?SN=TEST-SN-001')->getContent());
    }

    public function test_adms_devicecmd_acknowledges_command(): void
    {
        $command = AdmsDeviceCommand::factory()->sent()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);

        $response = $this->call(
            'POST',
            '/api/iclock/devicecmd?SN=TEST-SN-001',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            "ID={$command->id}&Return=0"
        );

        $response->assertOk();

        $this->assertDatabaseHas('adms_device_commands', [
            'id' => $command->id,
            'status' => 'acknowledged',
            'device_return_code' => '0',
        ]);
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->fresh()?->status);
    }

    public function test_adms_cdata_get_handshake_updates_heartbeat(): void
    {
        $response = $this->get('/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG');

        $response->assertOk();
        $this->assertEquals('OK', $response->getContent());

        // Heartbeat should be updated
        $this->device->refresh();
        $this->assertNotNull($this->device->last_heartbeat_at);
    }

    public function test_adms_coexists_with_webhook(): void
    {
        Event::fake([MemberCheckedIn::class]);

        // Create a device with BOTH webhook token AND ADMS enabled
        $token = 'dual-mode-token-123';
        BiometricDevice::factory()
            ->deviceNative()
            ->withToken($token)
            ->admsEnabled('DUAL-SN-001')
            ->withWebhookSecret(self::WEBHOOK_SECRET)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::DoorAccess,
            ]);

        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');

        // ADMS attendance push works
        $admsResponse = $this->call(
            'POST',
            '/api/iclock/cdata?SN=DUAL-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "{$this->member->id}\t{$timestamp}\t0\t15\t0"
        );
        $admsResponse->assertOk();

        // ADMS command polling works
        $commandResponse = $this->get('/api/iclock/getrequest?SN=DUAL-SN-001');
        $commandResponse->assertOk();

        // Webhook door control also works (separate channel with second member)
        $secondMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_frozen' => false,
        ]);
        BiometricConsent::query()->create([
            'gym_member_id' => $secondMember->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        $webhookResponse = $this->postSignedWebhook("/api/v1/biometric/webhook/{$token}", [
            'member_id' => $secondMember->id,
            'SN' => 'DUAL-SN-001',
        ]);
        $webhookResponse->assertOk();
    }

    public function test_adms_cdata_ignores_break_status_without_creating_open_attendance(): void
    {
        // Status 2 = break-out
        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$timestamp}\t2\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertOk();
        $this->assertEquals('OK', $response->getContent());

        // Should NOT create any attendance record
        $this->assertDatabaseMissing('attendances', [
            'gym_id' => $this->gym->id,
            'attendanceable_id' => $this->member->id,
        ]);
    }

    public function test_hikvision_event_push_authenticates_and_processes_attendance(): void
    {
        Event::fake([MemberCheckedIn::class]);

        $token = 'hikvision-secret-token-789';
        $hikDevice = BiometricDevice::factory()
            ->deviceNative()
            ->withToken($token)
            ->admsEnabled('HIK-SN-001')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $hikTime = now()->subHours(2)->setTimezone('+08:00')->toIso8601String();
        $xmlPayload = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<EventNotificationAlert version="2.0" xmlns="http://www.hikvision.com/ver20/XMLSchema">
    <eventType>AccessControllerEvent</eventType>
    <dateTime>{$hikTime}</dateTime>
    <AccessControllerEvent>
        <employeeNoString>{$this->member->id}</employeeNoString>
        <currentVerifyMode>face</currentVerifyMode>
    </AccessControllerEvent>
</EventNotificationAlert>
XML;

        $response = $this->call(
            'POST',
            '/api/v1/biometric/hikvision/event',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => "Bearer {$token}",
                'CONTENT_TYPE' => 'application/xml',
            ],
            $xmlPayload
        );

        $response->assertOk();
        $response->assertJson([
            'status' => 'granted',
            'action' => 'check_in',
        ]);

        $this->assertDatabaseHas('attendances', [
            'gym_id' => $this->gym->id,
            'device_id' => $hikDevice->id,
            'attendanceable_id' => $this->member->id,
            'check_in_result' => 'granted',
            'biometric_type' => 'face',
        ]);

        Event::assertDispatched(MemberCheckedIn::class);
    }

    public function test_adms_cdata_resolves_member_via_encrypted_biometric_template(): void
    {
        Event::fake([MemberCheckedIn::class]);

        // Create an enrolled template with an external enrollment ID differing from gym_members.id
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => 'HARDWARE-PIN-777',
            'is_active' => true,
            'enrolled_at' => now(),
        ]);

        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');
        $body = "HARDWARE-PIN-777\t{$timestamp}\t0\t1\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
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
            'attendanceable_id' => $this->member->id,
            'check_in_result' => 'granted',
            'biometric_type' => 'fingerprint',
        ]);

        Event::assertDispatched(MemberCheckedIn::class);
    }

    public function test_adms_cdata_normalizes_device_local_timezone(): void
    {
        Event::fake([MemberCheckedIn::class]);

        GymSetting::query()->create([
            'gym_id' => $this->gym->id,
            'key' => 'timezone',
            'value' => json_encode('Asia/Kolkata'),
        ]);

        $localTime = now()->subHours(2)->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$localTime}\t0\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertOk();

        $attendance = Attendances::query()
            ->where('gym_id', $this->gym->id)
            ->where('device_id', $this->device->id)
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->check_in_time);
        $configTz = config('app.timezone', 'UTC');
        $tz = is_string($configTz) ? $configTz : 'UTC';
        $expectedUtc = Date::parse($localTime, 'Asia/Kolkata')->setTimezone($tz);
        $this->assertEquals($expectedUtc->toDateTimeString(), $attendance->check_in_time->toDateTimeString());
    }

    public function test_biometric_models_are_prunable(): void
    {
        // Old receipt > 60 days
        $oldReceipt = BiometricEventReceipt::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'event_fingerprint' => 'test-fingerprint-old',
            'status' => 'processed',
        ]);
        $oldReceipt->created_at = now()->subDays(65);
        $oldReceipt->saveQuietly();

        // Recent receipt
        $recentReceipt = BiometricEventReceipt::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'event_fingerprint' => 'test-fingerprint-recent',
            'status' => 'processed',
        ]);
        $recentReceipt->created_at = now()->subDays(10);
        $recentReceipt->saveQuietly();

        // Old acknowledged command > 90 days
        $oldCommand = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '1'],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
        ]);
        $oldCommand->acknowledged_at = now()->subDays(95);
        $oldCommand->updated_at = now()->subDays(95);
        $oldCommand->saveQuietly();

        // Pending command (should not prune)
        $pendingCommand = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '2'],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);
        $pendingCommand->created_at = now()->subDays(100);
        $pendingCommand->saveQuietly();

        $prunableReceipts = (new BiometricEventReceipt())->prunable()->pluck('id');
        $this->assertTrue($prunableReceipts->contains($oldReceipt->id));
        $this->assertFalse($prunableReceipts->contains($recentReceipt->id));

        $prunableCommands = (new AdmsDeviceCommand())->prunable()->pluck('id');
        $this->assertTrue($prunableCommands->contains($oldCommand->id));
        $this->assertFalse($prunableCommands->contains($pendingCommand->id));
    }

    public function test_timestamp_within_30_day_window_accepted(): void
    {
        $validTimestamp = now()->subDays(29)->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$validTimestamp}\t0\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertOk();
        $this->assertDatabaseHas('attendances', [
            'device_id' => $this->device->id,
            'attendanceable_id' => $this->member->id,
        ]);
    }

    public function test_timestamp_beyond_30_days_rejected(): void
    {
        $staleTimestamp = now()->subDays(31)->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$staleTimestamp}\t0\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertOk();
        $this->assertDatabaseMissing('attendances', [
            'device_id' => $this->device->id,
            'attendanceable_id' => $this->member->id,
        ]);
    }

    public function test_future_timestamp_within_10_minutes_accepted(): void
    {
        $futureValid = now()->addMinutes(5)->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$futureValid}\t0\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertOk();
        $this->assertDatabaseHas('attendances', [
            'device_id' => $this->device->id,
            'attendanceable_id' => $this->member->id,
        ]);
    }

    public function test_future_timestamp_beyond_10_minutes_rejected(): void
    {
        $futureInvalid = now()->addMinutes(15)->format('Y-m-d H:i:s');
        $body = "{$this->member->id}\t{$futureInvalid}\t0\t15\t0";

        $response = $this->call(
            'POST',
            '/api/iclock/cdata?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body
        );

        $response->assertOk();
        $this->assertDatabaseMissing('attendances', [
            'device_id' => $this->device->id,
            'attendanceable_id' => $this->member->id,
        ]);
    }

    public function test_biometric_timestamp_parser_skew_boundaries(): void
    {
        // Boundary within 30 days
        $pastTimestamp = now()->subDays(29)->startOfSecond();
        $validPast = BiometricTimestampParser::parse(
            $pastTimestamp->format('Y-m-d H:i:s'),
            $this->device
        );
        $this->assertSame($pastTimestamp->toDateTimeString(), $validPast->toDateTimeString());

        // Boundary within 10 minutes future
        $futureTimestamp = now()->addMinutes(9)->startOfSecond();
        $validFuture = BiometricTimestampParser::parse(
            $futureTimestamp->format('Y-m-d H:i:s'),
            $this->device
        );
        $this->assertSame($futureTimestamp->toDateTimeString(), $validFuture->toDateTimeString());

        // Beyond 30 days throws
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timestamp outside acceptable skew window');
        BiometricTimestampParser::parse(
            now()->subDays(31)->format('Y-m-d H:i:s'),
            $this->device
        );
    }

    /**
     * Every CData URI: canonical and `.aspx`, on both route surfaces.
     *
     * @return array<string, array{0: string}>
     */
    public static function cdataUriProvider(): array
    {
        return self::admsUriProvider(['cdata', 'cdata.aspx']);
    }

    /**
     * Every getrequest URI: canonical and `.aspx`, on both route surfaces.
     *
     * @return array<string, array{0: string}>
     */
    public static function getRequestUriProvider(): array
    {
        return self::admsUriProvider(['getrequest', 'getrequest.aspx']);
    }

    /**
     * Every devicecmd URI: canonical and `.aspx`, on both route surfaces.
     *
     * @return array<string, array{0: string}>
     */
    public static function deviceCmdUriProvider(): array
    {
        return self::admsUriProvider(['devicecmd', 'devicecmd.aspx']);
    }

    /**
     * Only the `.aspx` CData aliases added by this change.
     *
     * @return array<string, array{0: string}>
     */
    public static function aspxCdataUriProvider(): array
    {
        return self::admsUriProvider(['cdata.aspx']);
    }

    /**
     * @param  list<string>  $endpoints
     * @return array<string, array{0: string}>
     */
    private static function admsUriProvider(array $endpoints): array
    {
        $uris = [];

        foreach (['api-prefixed' => '/api/iclock/', 'native' => '/iclock/'] as $surface => $prefix) {
            foreach ($endpoints as $endpoint) {
                $uris["{$surface} {$endpoint}"] = [$prefix . $endpoint];
            }
        }

        return $uris;
    }

    #[DataProvider('cdataUriProvider')]
    public function test_adms_handshake_is_served_on_every_cdata_surface_and_alias(string $uri): void
    {
        $response = $this->get($uri . '?SN=TEST-SN-001');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('charset=utf-8', (string) $response->headers->get('Content-Type'));
        $this->assertSame('OK', $response->getContent());

        $this->device->refresh();
        $this->assertNotNull($this->device->last_heartbeat_at);
    }

    public function test_adms_handshake_returns_configuration_options_when_options_queried(): void
    {
        $response = $this->get('/api/iclock/cdata?SN=TEST-SN-001&options=all&pushver=2.4.1');

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringStartsWith('GET OPTION FROM: TEST-SN-001', $content);
        $this->assertStringContainsString('TransFlag=', $content);
        $this->assertStringContainsString('EnrollUser', $content);
        $this->assertStringContainsString('ChgUser', $content);
        $this->assertStringContainsString('PushProtVer=2.4.1', $content);
    }

    public function test_adms_cdata_accepts_auxiliary_device_tables_without_rejection(): void
    {
        foreach (['USERINFO', 'BIODATA', 'FINGERTMP', 'OPTIONS'] as $table) {
            $response = $this->post(
                "/api/iclock/cdata?SN=TEST-SN-001&table={$table}",
                ['dummy' => 'payload'],
                ['Content-Type' => 'text/plain']
            );

            $response->assertOk();
            $this->assertSame('OK', $response->getContent());
        }
    }

    #[DataProvider('getRequestUriProvider')]
    public function test_adms_getrequest_returns_the_pending_command_on_every_surface_and_alias(string $uri): void
    {
        $command = AdmsDeviceCommand::factory()->userDelete((string) $this->member->id)->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);

        $response = $this->get($uri . '?SN=TEST-SN-001');

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringStartsWith('C:' . $command->id . ':', $content);
        $this->assertStringContainsString('DATA DELETE USERINFO', $content);
        $this->assertStringContainsString("PIN={$this->member->id}", $content);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $command->fresh()?->status);
    }

    #[DataProvider('deviceCmdUriProvider')]
    public function test_adms_devicecmd_acknowledges_on_every_surface_and_alias(string $uri): void
    {
        $command = AdmsDeviceCommand::factory()->sent()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);

        $response = $this->call(
            'POST',
            $uri . '?SN=TEST-SN-001',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            "ID={$command->id}&Return=0"
        );

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertDatabaseHas('adms_device_commands', [
            'id' => $command->id,
            'status' => 'acknowledged',
            'device_return_code' => '0',
        ]);
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->fresh()?->status);
    }

    #[DataProvider('aspxCdataUriProvider')]
    public function test_adms_aspx_cdata_accepts_attlog_attendance_push(string $uri): void
    {
        Event::fake([MemberCheckedIn::class]);

        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');

        $response = $this->call(
            'POST',
            $uri . '?SN=TEST-SN-001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "{$this->member->id}\t{$timestamp}\t0\t15\t0"
        );

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertDatabaseHas('attendances', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'attendanceable_id' => $this->member->id,
            'provider_type' => 'adms',
        ]);
        Event::assertDispatched(MemberCheckedIn::class);
    }

    #[DataProvider('aspxCdataUriProvider')]
    public function test_adms_aspx_cdata_rejects_unsupported_tables(string $uri): void
    {
        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');

        $response = $this->call(
            'POST',
            $uri . '?SN=TEST-SN-001&table=UNKNOWN_TABLE',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "{$this->member->id}\t{$timestamp}\t0\t15\t0"
        );

        $response->assertStatus(400);
        $this->assertSame('ERR: Unsupported table', $response->getContent());
        $this->assertDatabaseMissing('attendances', [
            'device_id' => $this->device->id,
            'attendanceable_id' => $this->member->id,
        ]);
    }

    #[DataProvider('aspxCdataUriProvider')]
    public function test_adms_cdata_handles_operlog(string $uri): void
    {
        $response = $this->call(
            'POST',
            $uri . '?SN=TEST-SN-001&table=OPERLOG',
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "Op=1\tPIN=369\tName=Hiren Sarla"
        );

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unsupportedAliasMethodProvider(): array
    {
        return [
            'api getrequest aspx rejects POST' => ['POST', '/api/iclock/getrequest.aspx'],
            'native getrequest aspx rejects POST' => ['POST', '/iclock/getrequest.aspx'],
            'api devicecmd aspx rejects GET' => ['GET', '/api/iclock/devicecmd.aspx'],
            'native devicecmd aspx rejects GET' => ['GET', '/iclock/devicecmd.aspx'],
        ];
    }

    #[DataProvider('unsupportedAliasMethodProvider')]
    public function test_aspx_aliases_reject_methods_their_canonical_twins_reject(string $method, string $uri): void
    {
        $this->call($method, $uri . '?SN=TEST-SN-001')->assertStatus(405);
    }

    public function test_every_iclock_route_has_an_aspx_twin_with_the_same_contract(): void
    {
        $iclockRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_contains($route->uri(), 'iclock'))
            ->keyBy(fn (RoutingRoute $route): string => $route->uri());

        $this->assertNotEmpty($iclockRoutes);

        foreach ($iclockRoutes as $uri => $route) {
            if (str_ends_with($uri, '.aspx')) {
                continue;
            }

            $aliasUri = $uri . '.aspx';

            $this->assertTrue($iclockRoutes->has($aliasUri), "Missing .aspx twin for {$uri}.");

            $alias = $iclockRoutes->get($aliasUri);
            $this->assertInstanceOf(RoutingRoute::class, $alias);

            $this->assertSame($route->methods(), $alias->methods(), "Method mismatch for {$aliasUri}.");
            $this->assertSame($route->getActionName(), $alias->getActionName(), "Controller mismatch for {$aliasUri}.");
            $this->assertSame($route->gatherMiddleware(), $alias->gatherMiddleware(), "Middleware mismatch for {$aliasUri}.");
        }
    }

    /**
     * Post a webhook payload signed the way a provisioned device signs it.
     *
     * The controller refuses a webhook for a device with no webhook_secret
     * configured, so the webhook in this suite has to be signed.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(string $uri, array $payload): TestResponse
    {
        return $this->postJson($uri, $payload, [
            'X-Signature' => 'sha256=' . hash_hmac('sha256', (string) json_encode($payload), self::WEBHOOK_SECRET),
        ]);
    }
}
