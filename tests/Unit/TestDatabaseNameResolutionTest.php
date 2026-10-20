<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\TestCase;

/**
 * Pins the database-name acceptance rule used by `Tests\TestCase::resolveTestDatabaseName()`.
 *
 * The rule decides whether a `.phpunit-<id>.xml`-supplied name may replace the shared
 * `gym_app_test` default, so it must accept only genuine test databases: a loose substring
 * check would accept `contest` or `latest_gym` and could point a run at a non-test database.
 *
 * `databaseNameFromUrl()` backs the pre-boot `DB_URL` guard and is pinned here too, because
 * its predecessor (`str_contains($url, '/gym_app')`) false-positived on `/gym_app_test`.
 */
final class TestDatabaseNameResolutionTest extends BaseTestCase
{
    #[Test]
    #[DataProvider('databaseNames')]
    public function it_accepts_only_whole_test_segment_names(?string $name, bool $expected): void
    {
        $this->assertSame($expected, TestCase::isTestDatabaseName($name));
    }

    #[Test]
    #[DataProvider('databaseUrlNames')]
    public function it_extracts_the_database_segment_from_a_connection_url(?string $url, ?string $expected): void
    {
        $this->assertSame($expected, TestCase::databaseNameFromUrl($url));
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public static function databaseNames(): array
    {
        return [
            'null' => [null, false],
            'empty string' => ['', false],
            'main database' => ['gym_app', false],
            'default test database' => ['gym_app_test', true],
            'suffixed test database' => ['gym_app_test_r3', true],
            'prefixed test database' => ['test_gym', true],
            'substring only: contest' => ['contest', false],
            'substring only: latest_gym' => ['latest_gym', false],
            'test segment swallowed by a longer word' => ['gym_app_testdata', false],
        ];
    }

    /**
     * Cases the pre-boot `DB_URL` guard must classify exactly: its previous substring check
     * treated `/gym_app_test` as `/gym_app`, so the whole segment — query string, trailing
     * slash and percent-encoding included — has to be pinned.
     *
     * @return array<string, array{?string, ?string}>
     */
    public static function databaseUrlNames(): array
    {
        return [
            'full mysql url' => ['mysql://root:root@127.0.0.1:3306/gym_app_test', 'gym_app_test'],
            'main database url' => ['mysql://root:root@127.0.0.1:3306/gym_app', 'gym_app'],
            'url with query string' => ['mysql://root:root@127.0.0.1:3306/gym_app_test?charset=utf8mb4', 'gym_app_test'],
            'trailing slash is preserved, not stripped' => ['mysql://root:root@127.0.0.1:3306/gym_app_test/', 'gym_app_test/'],
            'percent-encoded name' => ['mysql://root:root@127.0.0.1:3306/gym%5Fapp%5Ftest', 'gym_app_test'],
            'missing path' => ['mysql://root:root@127.0.0.1:3306', null],
            'empty string' => ['', null],
            'null' => [null, null],
            'bare absolute path' => ['/gym_app_test', 'gym_app_test'],
        ];
    }
}
