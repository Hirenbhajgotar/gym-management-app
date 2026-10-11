<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\WorkQueuesCommand;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkQueuesCommandTest extends TestCase
{
    /**
     * The whole point of the class is that BROADCAST_QUEUE / NOTIFICATIONS_QUEUE
     * take effect without editing the command. Nothing asserted that, so a
     * regression back to a literal list would leave the suite green while the
     * worker silently stopped consuming the renamed queue.
     */
    #[Test]
    public function it_builds_the_queue_list_from_configuration(): void
    {
        config()->set('queue.notifications_queue', 'tenant-notifications');
        config()->set('queue.broadcast_queue', 'tenant-broadcast');

        $this->assertSame(
            'default,tenant-notifications,tenant-broadcast',
            WorkQueuesCommand::queueList()
        );
    }

    #[Test]
    public function it_falls_back_to_the_documented_queue_names(): void
    {
        config()->set('queue.notifications_queue');
        config()->set('queue.broadcast_queue');

        $this->assertSame('default,notifications,broadcast', WorkQueuesCommand::queueList());
    }

    /**
     * An operator who blanks one of the variables must not produce a stray comma
     * that queue:work would read as an empty queue name.
     */
    #[Test]
    public function it_drops_an_empty_configured_queue_name(): void
    {
        config()->set('queue.notifications_queue', '');
        config()->set('queue.broadcast_queue', 'broadcast');

        $this->assertSame('default,broadcast', WorkQueuesCommand::queueList());
    }

    /**
     * REDIS_QUEUE is the third name the list is derived from, so overriding it
     * has to reach the worker too — the same reason the other two are here.
     */
    #[Test]
    public function it_honours_a_renamed_default_connection_queue(): void
    {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.queue', 'core');
        config()->set('queue.notifications_queue', 'notifications');
        config()->set('queue.broadcast_queue', 'broadcast');

        $this->assertSame('core,notifications,broadcast', WorkQueuesCommand::queueList());
    }

    /**
     * A connection that declares no queue key at all falls back to 'default'.
     */
    #[Test]
    public function it_falls_back_to_default_when_the_connection_declares_no_queue(): void
    {
        config()->set('queue.default', 'redis');
        config()->offsetUnset('queue.connections.redis.queue');
        config()->set('queue.notifications_queue', 'notifications');
        config()->set('queue.broadcast_queue', 'broadcast');

        $this->assertSame('default,notifications,broadcast', WorkQueuesCommand::queueList());
    }

    /**
     * A blanked REDIS_QUEUE is the same unset as a missing one. Obeying it would
     * return '', which queueList() filters out, and the worker would silently
     * consume only the notification and broadcast queues.
     */
    #[Test]
    public function it_falls_back_to_default_when_the_connection_queue_name_is_blank(): void
    {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.queue', '');
        config()->set('queue.notifications_queue', 'notifications');
        config()->set('queue.broadcast_queue', 'broadcast');

        $this->assertSame(
            'default,notifications,broadcast',
            WorkQueuesCommand::queueList()
        );
    }

    /**
     * queue:work against an empty synchronous queue returns immediately with
     * --once, so this exercises the real delegation rather than a stand-in.
     */
    #[Test]
    public function it_delegates_to_the_queue_worker(): void
    {
        $this->artisan('app:work-queues', ['--once' => true, '--tries' => 1, '--sleep' => 0])
            ->assertSuccessful();
    }

    #[Test]
    public function it_is_registered_under_the_documented_name(): void
    {
        $this->assertArrayHasKey('app:work-queues', Artisan::all());
        $this->assertInstanceOf(WorkQueuesCommand::class, Artisan::all()['app:work-queues']);
    }
}
