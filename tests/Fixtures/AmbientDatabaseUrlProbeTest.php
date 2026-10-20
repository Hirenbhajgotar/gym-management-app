<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Child-process probe for the ambient-`DB_URL` neutralisation in `Tests\TestCase`.
 *
 * This class lives outside the phpunit testsuites on purpose: it must never run in a normal
 * suite, where an ambient `DB_URL` is not guaranteed. It is executed only by
 * `DatabaseUrlHarnessSafetyTest`, which spawns a fresh PHPUnit process for this exact file
 * with an ambient `DB_URL` pointing at `gym_app`. Without the harness neutralisation the boot
 * would merge the URL over the mirrored `DB_*` keys and resolve to `gym_app`; the assertions
 * below read merged configuration only — no PDO dial and no migration happen here.
 */
final class AmbientDatabaseUrlProbeTest extends TestCase
{
    public function test_ambient_database_url_is_neutralised_before_boot(): void
    {
        $this->assertNull(
            config('database.connections.mysql.url'),
            'The ambient DB_URL must be removed from the environment before Laravel bootstraps.'
        );

        $databaseName = DB::connection()->getDatabaseName();

        $this->assertNotSame(
            'gym_app',
            $databaseName,
            'The ambient DB_URL re-targeted the test run at the main database.'
        );

        $this->assertTrue(
            TestCase::isTestDatabaseName($databaseName),
            "The connection resolved '{$databaseName}', which is not a test database name."
        );
    }
}
