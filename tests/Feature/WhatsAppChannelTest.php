<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Notifications\WhatsAppChannel;
use App\Services\GymContextResolver;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\ExpectationInterface;
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
        $resolver = Mockery::mock(GymContextResolver::class);

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
        $resolver = $this->createMockResolver();

        /** @var ExpectationInterface $resolveExpectation */
        $resolveExpectation = $resolver->shouldReceive('resolve');
        $resolveExpectation->andReturn(null);

        $channel = new WhatsAppChannel($resolver);

        $notification = $this->makeNotification([
            'to' => '+14155551234',
            'body' => 'Hello',
        ]);

        $notifiable = $this->createMockNotifiable(42);

        Log::shouldReceive('error')->andReturnNull()->with(
            'Unable to determine gym ID for WhatsApp notification',
            Mockery::on(fn (array $ctx) => $ctx['notifiable_id'] === 42),
        );

        $result = $channel->send($notifiable, $notification);

        $this->assertNull($result);
    }

    public function test_send_accepts_message_with_body_only(): void
    {
        $resolver = $this->createMockResolver();

        /** @var ExpectationInterface $resolveExpectation */
        $resolveExpectation = $resolver->shouldReceive('resolve');
        $resolveExpectation->andReturn(1);

        $channel = new WhatsAppChannel($resolver);

        $notification = $this->makeNotification([
            'to' => '+14155551234',
            'body' => 'Hello World',
        ]);

        $notifiable = $this->createMockNotifiable();

        $result = $channel->send($notifiable, $notification);

        $this->assertIsArray($result);
    }

    public function test_send_accepts_message_with_template_name_only(): void
    {
        $resolver = $this->createMockResolver();

        /** @var ExpectationInterface $resolveExpectation */
        $resolveExpectation = $resolver->shouldReceive('resolve');
        $resolveExpectation->andReturn(1);

        $channel = new WhatsAppChannel($resolver);

        $notification = $this->makeNotification([
            'to' => '+14155551234',
            'template_name' => 'membership_expiry',
            'template_variables' => ['1' => 'John'],
        ]);

        $notifiable = $this->createMockNotifiable();

        $result = $channel->send($notifiable, $notification);

        $this->assertIsArray($result);
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
