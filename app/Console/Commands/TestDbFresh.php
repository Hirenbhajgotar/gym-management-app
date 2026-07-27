<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

use function Laravel\Prompts\confirm;

final class TestDbFresh extends Command
{
    protected $signature = 'app:test-db-fresh
        {--seed : Run seeders after migrating}
        {--seeder= : Specific seeder class to run}
        {--drop-views : Drop all database views}
        {--drop-types : Drop all database types (Postgres only)}';

    protected $description = 'Safely reset the TEST database (gym_app_test) and re-run migrations. Refuses to run against the main database.';

    private const PRODUCTION_DATABASE_NAME = 'gym_app';

    private const EXPECTED_TEST_DATABASE_NAME = 'gym_app_test';

    public function handle(): int
    {
        DB::purge('mysql');
        putenv('DB_DATABASE=' . self::EXPECTED_TEST_DATABASE_NAME);
        $_ENV['DB_DATABASE'] = self::EXPECTED_TEST_DATABASE_NAME;
        $_SERVER['DB_DATABASE'] = self::EXPECTED_TEST_DATABASE_NAME;

        $this->laravel->make('config')->set('database.connections.mysql.database', self::EXPECTED_TEST_DATABASE_NAME);
        DB::reconnect('mysql');

        $database = (string) config('database.connections.mysql.database');
        $environment = (string) $this->laravel->environment();

        $this->newLine();
        $this->line('  <bg=blue;fg=white> TEST DATABASE RESET </>');
        $this->newLine();

        $this->line("  Environment : <info>{$environment}</info>");
        $this->line("  Database    : <info>{$database}</info>");
        $this->line('  Host        : <info>' . config('database.connections.mysql.host') . '</info>');
        $this->newLine();

        if ($database === self::PRODUCTION_DATABASE_NAME) {
            $this->error('  ABORTED: This command refuses to run against the production database.');
            $this->error("  Resolved database: '{$database}'");
            $this->newLine();
            $this->line('  Use one of the following instead:');
            $this->line('    • <comment>composer test:db:reset</comment>');
            $this->line('    • <comment>docker-compose exec app php artisan app:test-db-fresh --env=testing</comment>');
            $this->line('    • <comment>docker-compose exec app php artisan migrate:fresh --env=testing</comment>');
            $this->newLine();

            return self::FAILURE;
        }

        if ($database !== self::EXPECTED_TEST_DATABASE_NAME) {
            $this->warn("  WARNING: Resolved database '{$database}' is not the expected test database '" . self::EXPECTED_TEST_DATABASE_NAME . "'.");
            $this->warn('  Proceeding because the resolved database is not the production database.');

            if (! $this->option('no-interaction') && ! confirm('  Continue?', default: false)) {
                $this->info('  Aborted by user.');

                return self::FAILURE;
            }
        }

        if ($environment !== 'testing') {
            $this->warn("  WARNING: Application environment is '{$environment}', not 'testing'.");
            $this->warn('  It is strongly recommended to pass --env=testing so .env.testing is loaded.');
            $this->newLine();

            if (! $this->option('no-interaction') && ! confirm('  Continue anyway?', default: false)) {
                $this->info('  Aborted by user.');

                return self::FAILURE;
            }
        }

        $this->info('  Proceeding to drop all tables and re-run migrations on the test database...');
        $this->newLine();

        try {
            // DB::disconnect('mysql');
            DB::purge('mysql');
            DB::reconnect('mysql');
            DB::getPdo();
        } catch (Throwable $e) {
            $this->error('  ABORTED: Cannot connect to the test database.');
            $this->error('  ' . $e->getMessage());
            $this->newLine();
            $this->line('  Tip: Run <comment>docker-compose up -d db</comment> and wait for MySQL to be ready.');

            return self::FAILURE;
        }

        $arguments = [
            '--database' => 'mysql',
            '--drop-views' => true,
        ];

        if ($this->option('seed')) {
            $arguments['--seed'] = true;
        }

        if ($this->option('seeder')) {
            $arguments['--seeder'] = (string) $this->option('seeder');
        }

        if ($this->option('drop-types')) {
            $arguments['--drop-types'] = true;
        }

        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        $exitCode = $this->call('migrate:fresh', $arguments);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        if ($exitCode === 0) {
            $this->newLine();
            $this->info("  DONE: Test database '{$database}' has been reset and migrations re-run.");
        } else {
            $this->newLine();
            $this->error("  FAILED: migrate:fresh exited with code {$exitCode}.");
        }

        return $exitCode;
    }
}
