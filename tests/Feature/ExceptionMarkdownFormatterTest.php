<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ErrorReporting\ExceptionMarkdownFormatter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class ExceptionMarkdownFormatterTest extends TestCase
{
    public function test_query_string_pushcommkey_is_not_written_to_the_markdown_report(): void
    {
        $secret = 'K' . bin2hex(random_bytes(4));

        $request = Request::create('/api/iclock/cdata?SN=SN-1&pushcommkey=' . $secret, 'GET');

        $markdown = resolve(ExceptionMarkdownFormatter::class)->format(
            new RuntimeException('ADMS exploded'),
            $request,
            [
                'requested_url' => '/api/iclock/cdata?SN=SN-1&pushcommkey=[REDACTED]',
                'origin_url' => '/api/iclock/cdata?SN=SN-1&pushcommkey=[REDACTED]',
                'livewire_component' => null,
            ]
        );

        $this->assertStringNotContainsString($secret, $markdown);
        $this->assertStringContainsString('[REDACTED]', $markdown);
    }

    public function test_request_body_comm_keys_and_pushcommkey_headers_are_redacted(): void
    {
        $pushKey = 'P' . bin2hex(random_bytes(4));
        $commKey = 'C' . bin2hex(random_bytes(4));

        $request = Request::create(
            '/api/iclock/cdata?SN=SN-1',
            'POST',
            ['comm_key' => $commKey, 'pushcommkey' => $pushKey, 'other' => 'safe-value']
        );
        $request->headers->set('X-PushCommKey', $pushKey);
        $request->headers->set('X-Comm-Key', $commKey);

        $markdown = resolve(ExceptionMarkdownFormatter::class)->format(
            new RuntimeException('ADMS exploded'),
            $request,
            [
                'requested_url' => '/api/iclock/cdata',
                'origin_url' => '/api/iclock/cdata',
                'livewire_component' => null,
            ]
        );

        $this->assertStringNotContainsString($pushKey, $markdown);
        $this->assertStringNotContainsString($commKey, $markdown);
        $this->assertStringContainsString('safe-value', $markdown);
    }

    public function test_exception_message_cannot_inject_a_markdown_section_or_leak_a_key(): void
    {
        $secret = 'P' . bin2hex(random_bytes(4));

        $markdown = resolve(ExceptionMarkdownFormatter::class)->format(
            new RuntimeException("pushcommkey={$secret}\n## Injected section"),
            Request::create('/api/iclock/cdata', 'GET'),
            [
                'requested_url' => '/api/iclock/cdata',
                'origin_url' => '/api/iclock/cdata',
                'livewire_component' => null,
            ]
        );

        $this->assertStringNotContainsString($secret, $markdown);
        $this->assertStringNotContainsString("\n## Injected section", $markdown);
        $this->assertStringContainsString('## Injected section', $markdown);
    }

    public function test_query_exception_markdown_appends_redacted_database_diagnostics(): void
    {
        $secret = 'ABCDEF';
        $exception = $this->queryExceptionWithSecret($secret);

        $markdown = resolve(ExceptionMarkdownFormatter::class)->format(
            $exception,
            Request::create('/api/iclock/cdata', 'GET'),
            [
                'requested_url' => '/api/iclock/cdata',
                'origin_url' => '/api/iclock/cdata',
                'livewire_component' => null,
            ]
        );

        $databaseErrorOffset = strpos($markdown, '## Database Error');

        $this->assertIsInt($databaseErrorOffset);

        $databaseSection = substr($markdown, $databaseErrorOffset);
        $titleLine = explode("\n", $markdown, 2)[0];

        $this->assertStringContainsString('- **SQLSTATE**: 23000', $databaseSection);
        $this->assertStringContainsString('- **Driver code**: 1062', $databaseSection);
        $this->assertStringContainsString('- **SQL**:', $databaseSection);
        $this->assertStringContainsString('[REDACTED]', $databaseSection);
        $this->assertStringNotContainsString($secret, $databaseSection);
        $this->assertStringNotContainsString($secret, $titleLine);
    }

    private function queryExceptionWithSecret(string $secret): QueryException
    {
        $driverMessage = "Duplicate entry pushcommkey='{$secret}' for key 'biometric_devices.pushcommkey'";

        $previous = new class($driverMessage) extends PDOException
        {
            public function __construct(string $message)
            {
                parent::__construct($message, 0);

                $this->code = '23000';
                $this->errorInfo = ['23000', 1062, $message];
            }
        };

        return new QueryException(
            'mysql',
            "select * from biometric_devices where pushcommkey = '{$secret}'",
            [],
            $previous,
        );
    }
}
