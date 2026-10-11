<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs the queue worker on every queue this application actually uses.
 *
 * queue:work started without --queue consumes the default queue only, so the
 * notification and broadcast jobs sit unprocessed while the worker reports
 * healthy. Documenting a literal --queue list works but silently stops honouring
 * BROADCAST_QUEUE / NOTIFICATIONS_QUEUE the moment either is changed, which is
 * how the two drifted apart in the first place.
 */
final class WorkQueuesCommand extends Command
{
    protected $signature = 'app:work-queues
        {--tries=1 : Maximum attempts per job}
        {--sleep=3 : Seconds to sleep when no job is available}
        {--once : Process a single job and exit}';

    protected $description = 'Run the queue worker on the default, notifications and broadcast queues, honouring the configured queue names.';

    public function handle(): int
    {
        $queues = self::queueList();

        $this->components->info("Consuming queues: {$queues}");

        return Artisan::call('queue:work', [
            '--queue' => $queues,
            '--tries' => (string) $this->option('tries'),
            '--sleep' => (string) $this->option('sleep'),
            '--once' => $this->option('once'),
        ], $this->output);
    }

    /**
     * The queue list, in the order the worker should poll them. An empty
     * configured name is dropped rather than joined, so a blanked variable
     * cannot produce a trailing comma that queue:work would read as an empty
     * queue name.
     */
    public static function queueList(): string
    {
        // config()->string() throws on a null value, and an .env entry of
        // NOTIFICATIONS_QUEUE=null yields exactly that. Safely extract string.
        $configured = static function (string $key, string $default): string {
            $value = config($key);

            return is_string($value) ? $value : $default;
        };

        return implode(',', array_values(array_filter(
            [
                self::defaultQueueName(),
                $configured('queue.notifications_queue', 'notifications'),
                $configured('queue.broadcast_queue', 'broadcast'),
            ],
            static fn (string $queue): bool => $queue !== ''
        )));
    }

    /**
     * The queue the default connection consumes, which is REDIS_QUEUE on the
     * connection this application ships with — not the literal 'default'. Reading
     * it here keeps the whole list derivable from config, which is the reason
     * this command exists: BROADCAST_QUEUE and NOTIFICATIONS_QUEUE are both
     * honoured, so a REDIS_QUEUE override has to be too.
     *
     * A blank configured name is treated as unset rather than obeyed. Returning
     * '' here would drop the default queue from the list entirely — the filter in
     * queueList() discards it — and leave a worker consuming only the notification
     * and broadcast queues, which is the exact silent-starvation failure this
     * command was written to prevent.
     */
    private static function defaultQueueName(): string
    {
        $connection = config()->string('queue.default', '');

        if ($connection === '') {
            return 'default';
        }

        $queue = config("queue.connections.{$connection}.queue");

        return is_string($queue) && trim($queue) !== ''
            ? $queue
            : 'default';
    }
}
