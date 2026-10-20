<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * `DB_URL` neutralisation is a pre-boot concern: by the time an in-process test body runs, the
 * application is already booted, so the harness code that removes the ambient URL is
 * unreachable from this process. This test therefore proves it from the outside, by spawning
 * a child PHPUnit run for `tests/Fixtures/AmbientDatabaseUrlProbeTest.php` with an ambient
 * `DB_URL` pointing at `gym_app`.
 *
 * The child is harmless: the probe carries no `RefreshDatabase`, reads merged configuration
 * only, never dials a connection and never migrates. If the neutralisation regresses, the
 * child either aborts on its own `Tests\TestCase` guard before any connection or fails its
 * probe assertions against the merged config — either way the child exits non-zero here. The
 * `DB_URL` host `127.0.0.1:3306` is never contacted; it exists only to make the URL parseable.
 */
final class DatabaseUrlHarnessSafetyTest extends TestCase
{
    /** Lives outside the phpunit testsuites; only ever executed by the child process below. */
    private const CHILD_PROBE = 'tests/Fixtures/AmbientDatabaseUrlProbeTest.php';

    private const HOSTILE_DATABASE_URL = 'mysql://root:root@127.0.0.1:3306/gym_app';

    public function test_an_ambient_db_url_cannot_re_target_the_test_run_at_the_main_database(): void
    {
        $process = new Process([
            PHP_BINARY,
            'vendor/phpunit/phpunit/phpunit',
            '-c',
            $this->currentConfigurationPath(),
            '--do-not-cache-result',
            self::CHILD_PROBE,
        ]);

        $process->setWorkingDirectory(base_path());
        $process->setEnv(['DB_URL' => self::HOSTILE_DATABASE_URL]);
        $process->setTimeout(180);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "DB_URL child probe failed:\n"
                . "STDOUT:\n{$process->getOutput()}\n"
                . "STDERR:\n{$process->getErrorOutput()}"
        );
    }

    /**
     * The configuration file this PHPUnit process was started with, so the child inherits the
     * same isolated per-runner config rather than silently falling back to the shared default.
     *
     * `phpunit.xml` itself is the safe fallback: it forces `DB_DATABASE` to a test database, so
     * a child started without an explicit `-c` still cannot resolve to `gym_app`.
     */
    private function currentConfigurationPath(): string
    {
        $arguments = is_array($_SERVER['argv'] ?? null) ? array_values($_SERVER['argv']) : [];

        foreach ($arguments as $index => $argument) {
            $option = (string) $argument;

            if (($option === '-c' || $option === '--configuration') && isset($arguments[$index + 1])) {
                return (string) $arguments[$index + 1];
            }

            if (str_starts_with($option, '--configuration=')) {
                return substr($option, strlen('--configuration='));
            }
        }

        return 'phpunit.xml';
    }
}
