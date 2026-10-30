<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricLogRedactor;
use App\Models\ErrorReport;
use stdClass;
use Tests\TestCase;

class ErrorReportMarkdownProjectionTest extends TestCase
{
    public function test_fenced_request_headers_block_uses_the_deny_by_default_allow_list(): void
    {
        $markdown = "# Error: boom\n\n"
            . "### Request Headers\n\n"
            . "```\n"
            . "Accept: application/json\n"
            . "User-Agent: GymClient/1.0\n"
            . "php-auth-pw: hunter2\n"
            . "x-custom-auth: custom-secret\n"
            . "```\n\n"
            . "## Next Section\n\n"
            . "benign-sibling\n";

        $exported = $this->reportWithMarkdown($markdown)->toRedactedMarkdown();

        $this->assertStringNotContainsString('hunter2', $exported);
        $this->assertStringNotContainsString('custom-secret', $exported);
        $this->assertStringContainsString('application/json', $exported);
        $this->assertStringContainsString('GymClient/1.0', $exported);
        $this->assertStringContainsString('benign-sibling', $exported);
        $this->assertMatchesRegularExpression('/php-auth-pw:\s*\[REDACTED\]/', $exported);
        $this->assertMatchesRegularExpression('/x-custom-auth:\s*\[REDACTED\]/', $exported);
    }

    public function test_unfenced_headers_block_redacts_until_the_blank_line_and_denies_folded_lines(): void
    {
        $markdown = "## Headers\n"
            . "accept: application/json\n"
            . "  folded-secret-value\n"
            . "x-custom-auth: custom-secret\n"
            . "\n"
            . "## Diagnostics\n\n"
            . "benign-sibling\n";

        $exported = $this->reportWithMarkdown($markdown)->toRedactedMarkdown();

        $this->assertStringNotContainsString('folded-secret-value', $exported);
        $this->assertStringNotContainsString('custom-secret', $exported);
        $this->assertStringContainsString('application/json', $exported);
        $this->assertMatchesRegularExpression('/^\[REDACTED\]$/m', $exported);
        $this->assertStringContainsString('## Diagnostics', $exported);
        $this->assertStringContainsString('benign-sibling', $exported);
    }

    public function test_headers_heading_inside_an_unrelated_fence_does_not_start_redaction(): void
    {
        $markdown = "# Error: boom\n\n"
            . "```\n"
            . "### Headers\n"
            . "benign-tail\n"
            . "```\n\n"
            . "benign-after-fence\n";

        $exported = $this->reportWithMarkdown($markdown)->toRedactedMarkdown();

        $this->assertStringContainsString('### Headers', $exported);
        $this->assertStringContainsString('benign-tail', $exported);
        $this->assertStringContainsString('benign-after-fence', $exported);
    }

    public function test_unfenced_headers_block_keeps_going_across_an_intro_line_and_blank_line(): void
    {
        $markdown = "### Headers\n"
            . "Captured request metadata\n"
            . "\n"
            . "Authorization: Bearer bearer-secret-token\n"
            . "Cookie: session=cookie-secret\n"
            . "\n"
            . "## Diagnostics\n\n"
            . "benign-sibling\n";

        $exported = $this->reportWithMarkdown($markdown)->toRedactedMarkdown();

        $this->assertStringNotContainsString('bearer-secret-token', $exported);
        $this->assertStringNotContainsString('cookie-secret', $exported);
        $this->assertMatchesRegularExpression(
            '/\[REDACTED\]\n\nAuthorization: \[REDACTED\]/',
            $exported,
            'The intro line is denied but must not end the block before the blank line.'
        );
        $this->assertStringContainsString("Cookie: [REDACTED]\n\n## Diagnostics", $exported);
        $this->assertStringContainsString('benign-sibling', $exported);
    }

    public function test_crlf_markdown_round_trips_and_does_not_leak(): void
    {
        $markdown = "# Error: boom\r\n"
            . "\r\n"
            . "## Request Headers\r\n"
            . "\r\n"
            . "```\r\n"
            . "content-type: application/json\r\n"
            . "x-custom-auth: custom-secret\r\n"
            . "php-auth-pw: hunter2\r\n"
            . "```\r\n"
            . "\r\n"
            . "## Next Section\r\n"
            . "\r\n"
            . "benign-sibling\r\n";

        $exported = $this->reportWithMarkdown($markdown)->toRedactedMarkdown();

        $this->assertStringNotContainsString('custom-secret', $exported);
        $this->assertStringNotContainsString('hunter2', $exported);
        $this->assertStringContainsString('application/json', $exported);
        $this->assertStringContainsString("## Next Section\r\n\r\nbenign-sibling\r\n", $exported);
        $this->assertStringNotContainsString(
            "\n",
            str_replace("\r\n", '', $exported),
            'CRLF input must not gain LF-only line endings.'
        );
    }

    public function test_denied_headers_are_redacted_case_insensitively(): void
    {
        $markdown = "### headers\n\n"
            . "```\n"
            . "X-Custom-Auth: custom-secret\n"
            . "PHP-AUTH-PW: hunter2\n"
            . "```\n";

        $exported = $this->reportWithMarkdown($markdown)->toRedactedMarkdown();

        $this->assertStringNotContainsString('custom-secret', $exported);
        $this->assertStringNotContainsString('hunter2', $exported);
    }

    public function test_numeric_indexed_header_lists_use_the_allow_list_projection(): void
    {
        $headers = [
            'accept: application/json',
            'php-auth-pw: hunter2',
            'x-custom-auth: custom-secret',
            'user-agent: GymClient/1.0',
        ];

        $redacted = (string) json_encode(BiometricLogRedactor::redactHeaders($headers), JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString('hunter2', $redacted);
        $this->assertStringNotContainsString('custom-secret', $redacted);
        $this->assertStringContainsString('application/json', $redacted);
        $this->assertStringContainsString('GymClient/1.0', $redacted);

        $report = new ErrorReport([
            'context' => [
                'headers' => $headers,
                'safe' => 'benign-sibling',
            ],
        ]);

        $context = (string) json_encode($report->redactedContextForDisplay(), JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString('hunter2', $context);
        $this->assertStringNotContainsString('custom-secret', $context);
        $this->assertStringContainsString('application/json', $context);
        $this->assertStringContainsString('benign-sibling', $context);
    }

    public function test_json_code_fences_preserve_structure_and_precision(): void
    {
        $json = '{"empty":{},"0":"a","1":"b","ratio":1.0,"id":12345678901234567890,'
            . '"error":"failed pushcommkey=749201","safe":"ok"}';

        $markdown = "# Error: boom\n\n### Context\n\n```json\n{$json}\n```\n";

        $exported = $this->reportWithMarkdown($markdown)->toRedactedMarkdown();

        $this->assertMatchesRegularExpression('/"empty"\s*:\s*\{\s*\}/', $exported);
        $this->assertDoesNotMatchRegularExpression('/"empty"\s*:\s*\[\s*\]/', $exported);
        $this->assertMatchesRegularExpression('/"0"\s*:\s*"a"/', $exported);
        $this->assertMatchesRegularExpression('/"1"\s*:\s*"b"/', $exported);
        $this->assertMatchesRegularExpression('/"ratio"\s*:\s*1\.0(?![0-9])/', $exported);
        $this->assertStringContainsString('12345678901234567890', $exported);
        $this->assertDoesNotMatchRegularExpression('/\d\.\d+e\+19/i', $exported);
        $this->assertStringNotContainsString('749201', $exported);
        $this->assertMatchesRegularExpression('/"error"\s*:\s*"failed pushcommkey=\[REDACTED\]"/', $exported);
        $this->assertDoesNotMatchRegularExpression('/"error"\s*:\s*"\*\*\*"/', $exported);
        $this->assertMatchesRegularExpression('/"safe"\s*:\s*"ok"/', $exported);

        $this->assertSame(1, preg_match('/```json\s*(?P<json>\{.*?\})\s*```/s', $exported, $matches));
        $decoded = json_decode($matches['json']);

        $this->assertInstanceOf(stdClass::class, $decoded);
        $this->assertInstanceOf(stdClass::class, $decoded->empty);
        $this->assertSame('a', $decoded->{'0'});
        $this->assertSame('b', $decoded->{'1'});
        $this->assertSame(1.0, $decoded->ratio);
    }

    public function test_legacy_markdown_appends_redacted_database_diagnostics(): void
    {
        $report = new ErrorReport([
            'error_message' => 'boom',
            'error_type' => 'exception',
            'context' => [
                'database_error' => [
                    'sqlstate' => '23000',
                    'driver_code' => 1062,
                    'driver_message' => "Duplicate entry pushcommkey='ABCDEF' for key 'biometric_devices.pushcommkey'",
                ],
            ],
        ]);

        $exported = $report->toRedactedMarkdown();

        $this->assertStringContainsString('## Database Error', $exported);
        $this->assertStringContainsString('- **SQLSTATE**: 23000', $exported);
        $this->assertStringContainsString('- **Driver code**: 1062', $exported);
        $this->assertStringNotContainsString('ABCDEF', $exported);
        $this->assertStringContainsString('pushcommkey=\'[REDACTED]\'', $exported);
    }

    private function reportWithMarkdown(string $markdown): ErrorReport
    {
        return new ErrorReport(['markdown_report' => $markdown]);
    }
}
