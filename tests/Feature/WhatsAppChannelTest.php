<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Notifications\WhatsAppChannel;
use App\Services\GymContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use stdClass;
use Tests\TestCase;

/**
 * Unit tests for WhatsAppChannel::send().
 *
 * Tests input validation, gym ID resolution, and error handling.
 * Provider interaction is tested via integration tests with sandbox mode.
 *
 * @see WhatsAppProviderFactoryTest for factory/caching tests
 */
class WhatsAppChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Log facade to prevent debug/error calls from failing tests
        // (MetaWhatsAppProvider has unimplemented interface methods, causing fatal errors)
        Log::shouldReceive('debug')->zeroOrMoreTimes()->andReturnNull();
        Log::shouldReceive('error')->zeroOrMoreTimes()->andReturnNull();
        Log::shouldReceive('warning')->zeroOrMoreTimes()->andReturnNull();
        Log::shouldReceive('critical')->zeroOrMoreTimes()->andReturnNull();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function makeNotification(array $message): Notification
    {
        return new class($message) extends Notification
        {
            /**
             * @param  array<string, mixed>  $msg
             */
            public function __construct(private array $msg) {}

            /**
             * @return array<string, mixed>
             */
            public function toWhatsApp(object $notifiable): array
            {
                return $this->msg;
            }
        };
    }

    private function createMockResolver(): GymContextResolver&MockInterface
    {
        /** @var GymContextResolver&Mockery\MockInterface $resolver */
        $resolver = Mockery::mock(GymContextResolver::class)->shouldIgnoreMissing();

        return $resolver;
    }

    /**
     * Create a simple object with an id property for testing.
     */
    private function createMockNotifiable(int $id = 1): object
    {
        $notifiable = new stdClass();
        $notifiable->id = $id;

        return $notifiable;
    }

    public function test_send_returns_null_when_notification_lacks_to_whatsapp(): void
    {
        $resolver = $this->createMockResolver();
        $channel = new WhatsAppChannel($resolver);

        $notification = new class() extends Notification {};

        $result = $channel->send($this->createMockNotifiable(), $notification);

        $this->assertNull($result);
    }

    public function test_send_returns_null_when_message_is_empty(): void
    {
        $resolver = $this->createMockResolver();
        $channel = new WhatsAppChannel($resolver);

        $notification = $this->makeNotification([]);
        $notifiable = $this->createMockNotifiable();

        $result = $channel->send($notifiable, $notification);

        $this->assertNull($result);
    }

    public function test_send_returns_null_when_message_missing_recipient(): void
    {
        $resolver = $this->createMockResolver();
        $channel = new WhatsAppChannel($resolver);

        $notification = $this->makeNotification(['body' => 'Hello']);
        $notifiable = $this->createMockNotifiable();

        $result = $channel->send($notifiable, $notification);

        $this->assertNull($result);
    }

    public function test_send_returns_null_when_message_has_no_body_or_template(): void
    {
        $resolver = $this->createMockResolver();
        $channel = new WhatsAppChannel($resolver);

        $notification = $this->makeNotification(['to' => '+14155551234']);
        $notifiable = $this->createMockNotifiable();

        $result = $channel->send($notifiable, $notification);

        $this->assertNull($result);
    }

    public function test_send_logs_error_and_returns_null_when_gym_id_unresolvable(): void
    {
        /** @var GymContextResolver&MockInterface $resolver */
        $resolver = $this->createMockResolver();

        /** @var Mockery\Expectation $expectation */
        $expectation = $resolver->shouldReceive('resolve');
        $expectation->andReturn(null);

        $channel = new WhatsAppChannel($resolver);

        $notification = $this->makeNotification([
            'to' => '+14155551234',
            'body' => 'Hello',
        ]);

        $notifiable = $this->createMockNotifiable(42);

        $result = $channel->send($notifiable, $notification);

        $this->assertNull($result);
    }

    public function test_send_rejects_message_with_neither_body_nor_template(): void
    {
        $resolver = $this->createMockResolver();
        $channel = new WhatsAppChannel($resolver);

        $notification = $this->makeNotification([
            'to' => '+14155551234',
        ]);
        $notifiable = $this->createMockNotifiable();

        $result = $channel->send($notifiable, $notification);

        $this->assertNull($result);
    }
}
