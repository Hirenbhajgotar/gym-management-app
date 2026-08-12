<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymMember;
use App\Notifications\BirthdayNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Mockery\Expectation;
use Tests\TestCase;

/**
 * Tests for BirthdayNotification.
 *
 * Covers constructor null guard, mail rendering, WhatsApp sandbox mode,
 * cache key format, gym ID resolution, and channel resolution.
 */
class BirthdayNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createMemberWithGym(?Gym $gym = null): GymMember
    {
        $gym ??= Gym::factory()->create();

        return GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'John Doe',
            'phone' => '+14155551234',
        ]);
    }

    public function test_constructor_throws_when_gym_is_null(): void
    {
        /** @var Mockery\MockInterface&GymMember $member */
        $member = Mockery::mock(GymMember::class);

        // Each argument matcher must be registered on its own expectation;
        // chaining ->with() on a single expectation replaces the matcher.
        /** @var Expectation $expectation */
        $expectation = $member->shouldReceive('getAttribute');
        $expectation->with('gym')->andReturn(null);

        /** @var Expectation $expectation */
        $expectation = $member->shouldReceive('getAttribute');
        $expectation->with('id')->andReturn(1);

        /** @var Expectation $expectation */
        $expectation = $member->shouldReceive('getAttribute');
        $expectation->with('gym_id')->andReturn(null);

        /** @var Expectation $expectation */
        $expectation = $member->shouldReceive('getAttribute');
        $expectation->with('name')->andReturn('Test');

        /** @var Expectation $expectation */
        $expectation = $member->shouldReceive('getAttribute');
        $expectation->with('phone')->andReturn('+14155551234');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot send birthday notification');

        new BirthdayNotification($member);
    }

    public function test_to_mail_returns_correct_subject(): void
    {
        $gym = Gym::factory()->create(['name' => 'FitLife Gym']);
        $member = $this->createMemberWithGym($gym);

        $notification = new BirthdayNotification($member);
        $mail = $notification->toMail($member);

        $this->assertStringContainsString('John Doe', $mail->subject);
        $this->assertStringContainsString('Happy Birthday', $mail->subject);
    }

    public function test_to_mail_uses_gym_name(): void
    {
        $gym = Gym::factory()->create(['name' => 'PowerHouse Fitness']);
        $member = $this->createMemberWithGym($gym);

        $notification = new BirthdayNotification($member);
        $mail = $notification->toMail($member);

        $this->assertStringContainsString('PowerHouse Fitness', (string) $mail->render());
    }

    public function test_to_whatsapp_sandbox_returns_correct_payload(): void
    {
        config(['services.twilio.use_sandbox' => true]);

        $gym = Gym::factory()->create(['name' => 'Test Gym']);
        $member = $this->createMemberWithGym($gym);

        $notification = new BirthdayNotification($member);
        $payload = $notification->toWhatsApp($member);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('to', $payload);
        $this->assertArrayHasKey('body', $payload);

        /** @var string $body */
        $body = $payload['body'];
        $this->assertStringContainsString('John Doe', $body);
        $this->assertStringContainsString('Test Gym', $body);
    }

    public function test_to_whatsapp_returns_null_when_no_phone(): void
    {
        config(['services.twilio.use_sandbox' => false]);

        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'phone' => '',
        ]);

        $notification = new BirthdayNotification($member);
        $payload = $notification->toWhatsApp($member);

        $this->assertNull($payload);
    }

    public function test_birthday_cache_key_format(): void
    {
        $cacheKey = BirthdayNotification::birthdayCacheKey(123);

        $this->assertSame('birthday_sent_123_' . now()->year, $cacheKey);
    }

    public function test_get_gym_id_returns_member_gym_id(): void
    {
        $gym = Gym::factory()->create();
        $member = $this->createMemberWithGym($gym);

        $notification = new BirthdayNotification($member);

        $this->assertSame($gym->id, $notification->getGymId($member));
    }

    public function test_via_returns_channels_from_resolver(): void
    {
        $gym = Gym::factory()->create();
        $member = $this->createMemberWithGym($gym);

        $notification = new BirthdayNotification($member);
        $channels = $notification->via($member);

        // via() always returns an array; verify it's a list of strings
        $this->assertNotEmpty($channels);
        foreach ($channels as $channel) {
            $this->assertIsString($channel);
        }
    }
}
