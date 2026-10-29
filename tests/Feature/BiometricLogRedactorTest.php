<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ErrorReport;
use App\Enums\ErrorStatus;
use App\Enums\ErrorPriority;
use App\Services\ExceptionStorageService;
use App\Biometrics\Services\BiometricLogRedactor;
use App\Services\ErrorReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BiometricLogRedactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_keys_are_redacted(): void
    {
        $payload = [
            'pin' => '1234',
            'user_pin' => '5678',
            'user_id' => '1042',
            'member_id' => '9988',
            'gym_member_id' => '7766',
            'pushcommkey' => 'supersecretpushkey',
            'comm_key' => 'cloud-provider-comm-key',
            'commkey' => 'adms-commkey-alias',
            'adms_comm_key' => 'adms-comm-key-value',
            'access_token' => 'meta_access_token_secret',
            'app_secret' => 'app_secret_123',
            'webhook_verify_token' => 'verify_token_456',
            'password' => 'secretpass',
        ];

        $redacted = BiometricLogRedactor::redact($payload);

        $this->assertEquals('***', $redacted['pin']);
        $this->assertEquals('***', $redacted['user_pin']);
        $this->assertEquals('***', $redacted['user_id']);
        $this->assertEquals('***', $redacted['member_id']);
        $this->assertEquals('***', $redacted['gym_member_id']);
        $this->assertIsString($redacted['pushcommkey']);
        $this->assertStringContainsString('***', $redacted['pushcommkey']);
        $this->assertStringContainsString('***', (string) $redacted['comm_key']);
        $this->assertStringNotContainsString('cloud-provider-comm-key', (string) $redacted['comm_key']);
        $this->assertStringContainsString('***', (string) $redacted['commkey']);
        $this->assertStringContainsString('***', (string) $redacted['adms_comm_key']);
        $this->assertIsString($redacted['access_token']);
        $this->assertStringContainsString('***', $redacted['access_token']);
        $this->assertIsString($redacted['app_secret']);
        $this->assertStringContainsString('***', $redacted['app_secret']);
        $this->assertIsString($redacted['webhook_verify_token']);
        $this->assertStringContainsString('***', $redacted['webhook_verify_token']);
        $this->assertIsString($redacted['password']);
        $this->assertStringContainsString('***', $redacted['password']);
    }

    public function test_benign_keys_containing_pin_as_substring_are_not_redacted(): void
    {
        $payload = [
            'mapping' => ['field_a' => 'value_a'],
            'pinpoint' => 'location_abc',
            'device_id' => 42,
            'gym_id' => 7,
            'status' => 'active',
        ];

        $redacted = BiometricLogRedactor::redact($payload);

        $this->assertEquals(['field_a' => 'value_a'], $redacted['mapping']);
        $this->assertEquals(42, $redacted['device_id']);
        $this->assertEquals(7, $redacted['gym_id']);
        $this->assertEquals('active', $redacted['status']);
    }

    public function test_nested_arrays_are_recursively_redacted(): void
    {
        $payload = [
            'device' => [
                'id' => 12,
                'pushcommkey' => 'nested_secret_key',
            ],
            'members' => [
                ['user_id' => '100', 'name' => 'Alice'],
                ['user_id' => '101', 'name' => 'Bob'],
            ],
        ];

        $redacted = BiometricLogRedactor::redact($payload);

        $device = $redacted['device'] ?? null;
        $this->assertIsArray($device);
        $this->assertEquals(12, $device['id'] ?? null);
        $pushcommkey = $device['pushcommkey'] ?? null;
        $this->assertIsString($pushcommkey);
        $this->assertStringContainsString('***', $pushcommkey);

        $members = $redacted['members'] ?? null;
        $this->assertIsArray($members);
        $member0 = $members[0] ?? null;
        $member1 = $members[1] ?? null;
        $this->assertIsArray($member0);
        $this->assertIsArray($member1);
        $this->assertEquals('***', $member0['user_id'] ?? null);
        $this->assertEquals('Alice', $member0['name'] ?? null);
        $this->assertEquals('***', $member1['user_id'] ?? null);
        $this->assertEquals('Bob', $member1['name'] ?? null);
    }

    public function test_error_report_persists_no_plaintext_comm_key(): void
    {
        $pushKey = 'P' . bin2hex(random_bytes(4));
        $commKey = 'C' . bin2hex(random_bytes(4));

        $this->app->make('router')->get('/test-adms-error-report', function (ErrorReportService $service) {
            $service->reportError('boom', exception: new RuntimeException('boom'));

            return response('ok');
        });

        $this->withHeaders([
            'X-PushCommKey' => $pushKey,
            'X-Comm-Key' => $commKey,
        ])->get('/test-adms-error-report?pushcommkey=' . $pushKey . '&comm_key=' . $commKey)
            ->assertOk();

        $report = ErrorReport::query()->latest('id')->firstOrFail();

        $this->assertStringNotContainsString($pushKey, (string) $report->markdown_report);
        $this->assertStringNotContainsString($commKey, (string) $report->markdown_report);
        $this->assertStringNotContainsString($pushKey, (string) json_encode($report->context));
        $this->assertStringNotContainsString($commKey, (string) json_encode($report->context));
    }

    public function test_redact_text_closes_dump_label_and_escaped_json_forms(): void
    {
        $cases = [
            '["pushcommkey" => "123456"]' => '["pushcommkey" => "[REDACTED]"]',
            "['pushcommkey' => '123456']" => "['pushcommkey' => '[REDACTED]']",
            "\$_GET['pushcommkey'] = '123456'" => "\$_GET['pushcommkey'] = '[REDACTED]'",
            "Array\n(\n [pushcommkey] => 123456\n)" => "Array\n(\n [pushcommkey] => [REDACTED]\n)",
            'array(1) { ["pushcommkey"]=> string(6) "123456" }' => 'array(1) { ["pushcommkey"]=> "[REDACTED]" }',
            'Push Comm Key: 123456' => 'Push Comm Key: [REDACTED]',
            'PUSH COMM KEY = 123456' => 'PUSH COMM KEY = [REDACTED]',
            'payload: {\\"pushcommkey\\":\\"123456\\"}' => 'payload: {\\"pushcommkey\\":\\"***\\"}',
            'verifyCommKey(123456)' => 'verifyCommKey([REDACTED])',
            'apushcommkey=123456' => 'apushcommkey=[REDACTED]',
            '%pushcommkey=123456' => '%pushcommkey=[REDACTED]',
            '[pushcommkey=123456' => '[pushcommkey=[REDACTED]',
            ']pushcommkey=123456' => ']pushcommkey=[REDACTED]',
            '{"adms_comm_key":["safe]749201"]}' => '{"adms_comm_key":"***"}',
            '{"pushcommkey":{"a":"749201"}}' => '{"pushcommkey":"***"}',
            'pushcommkey%25253D123456' => 'pushcommkey%25253D[REDACTED]',
            '"snapshot": "{\\"data\\":{\\"adms_comm_key\\":\\"749201\\"}"' => '"snapshot": "[REDACTED]"',
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, BiometricLogRedactor::redactText($input), "Unexpected output for: {$input}");
        }
    }

    public function test_redact_text_does_not_corrupt_comparisons_blank_assignments_or_namespaces(): void
    {
        $this->assertSame('$request->token === "abc"', BiometricLogRedactor::redactText('$request->token === "abc"'));
        $this->assertSame('$request->token == "abc"', BiometricLogRedactor::redactText('$request->token == "abc"'));
        $this->assertSame('$request->token != "abc"', BiometricLogRedactor::redactText('$request->token != "abc"'));
        $this->assertSame("token=\nkeep me", BiometricLogRedactor::redactText("token=\nkeep me"));
        $this->assertSame(
            'App\Services\PasswordService::reset() and App\Services\TokenService::refresh()',
            BiometricLogRedactor::redactText('App\Services\PasswordService::reset() and App\Services\TokenService::refresh()')
        );
    }

    public function test_redact_text_keeps_json_structure_and_benign_siblings(): void
    {
        $input = '{"snapshot":"{\\"data\\":{\\"adms_comm_key\\":\\"749201\\",\\"safe\\":\\"benign\\"}}"}';

        $redacted = BiometricLogRedactor::redactText($input);
        $decoded = json_decode($redacted, true);

        $this->assertIsArray($decoded);
        $this->assertStringNotContainsString('749201', $redacted);
        $this->assertStringContainsString('benign', $redacted);
        $this->assertStringContainsString('data', $redacted);

        $numeric = BiometricLogRedactor::redactText('{"adms_comm_key":749201,"safe":"ok"}');
        $this->assertIsArray(json_decode($numeric, true));
        $this->assertStringNotContainsString('749201', $numeric);
        $this->assertStringContainsString('"safe":"ok"', $numeric);
    }

    public function test_redact_url_handles_encoded_delimiters_and_nested_urls(): void
    {
        foreach (['%3D', '%253D', '%25253D'] as $delimiter) {
            $url = 'https://example.test/x?pushcommkey' . $delimiter . '749201&safe=1';

            $redacted = BiometricLogRedactor::redactUrl($url);

            $this->assertStringNotContainsString('749201', $redacted, $url);
            $this->assertStringContainsString('safe=1', $redacted, $url);
        }

        $nested = BiometricLogRedactor::redactUrl('https://example.test/x?next=https%3A%2F%2Fevil.test%2F%3Fpushcommkey%3D749201');

        $this->assertStringNotContainsString('749201', $nested);
        $this->assertStringContainsString('next=[REDACTED]', $nested);
    }

    public function test_redacted_markdown_masks_biometric_pii_in_context_json(): void
    {
        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'boom',
            'markdown_report' => null,
            'stack_trace' => null,
            'context' => [
                'pin' => '1234',
                'card' => '4111111111111111',
                'template_data' => 'raw-template-secret',
                'photo' => 'raw-photo-base64',
                'safe' => 'benign-sibling',
            ],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        $exported = $report->toRedactedMarkdown();

        foreach (['1234', '4111111111111111', 'raw-template-secret', 'raw-photo-base64'] as $secret) {
            $this->assertStringNotContainsString($secret, $exported);
        }

        $this->assertStringContainsString('benign-sibling', $exported);
    }

    public function test_mask_helper(): void
    {
        $this->assertEquals('', BiometricLogRedactor::mask(null));
        $this->assertEquals('****', BiometricLogRedactor::mask('1234'));
        $this->assertEquals('12*45', BiometricLogRedactor::mask('12345'));
        $this->assertEquals('12******90', BiometricLogRedactor::mask('1234567890'));
    }

    public function test_six_digit_comm_key_is_fully_masked(): void
    {
        $redacted = BiometricLogRedactor::redact([
            'pushcommkey' => '749201',
            'x-pushcommkey' => '123456',
        ]);

        $this->assertSame('***', $redacted['pushcommkey']);
        $this->assertSame('***', $redacted['x-pushcommkey']);
        $this->assertStringNotContainsString('7', $redacted['pushcommkey']);
        $this->assertStringNotContainsString('1', $redacted['x-pushcommkey']);
    }

    public function test_redact_headers_removes_sensitive_authorization_and_cookies(): void
    {
        $headers = [
            'authorization' => ['Bearer secret-jwt-token-xyz'],
            'Authorization' => ['Bearer secret-jwt-token-xyz'],
            'cookie' => ['session_id=123456789; remember_token=secret'],
            'Set-Cookie' => ['session_id=987654321; Path=/; HttpOnly'],
            'x-api-key' => ['my-super-secret-api-key'],
            'proxy-authorization' => ['Basic dXNlcjpwYXNz'],
            'x-pushcommkey' => ['my-adms-secret-commkey'],
            'X-PushCommKey' => ['my-adms-secret-commkey'],
            'x-device-token' => ['secret-device-tok'],
            'x-signature' => ['secret-hmac-signature'],
            'custom-header' => ['unapproved-header'],
            'content-type' => ['application/json'],
            'accept' => ['application/json'],
        ];

        $redacted = BiometricLogRedactor::redactHeaders($headers);

        $this->assertSame(['[REDACTED]'], $redacted['authorization']);
        $this->assertSame(['[REDACTED]'], $redacted['Authorization']);
        $this->assertSame(['[REDACTED]'], $redacted['cookie']);
        $this->assertSame(['[REDACTED]'], $redacted['Set-Cookie']);
        $this->assertSame(['[REDACTED]'], $redacted['x-api-key']);
        $this->assertSame(['[REDACTED]'], $redacted['proxy-authorization']);
        $this->assertSame(['[REDACTED]'], $redacted['x-pushcommkey']);
        $this->assertSame(['[REDACTED]'], $redacted['X-PushCommKey']);
        $this->assertSame(['[REDACTED]'], $redacted['x-device-token']);
        $this->assertSame(['[REDACTED]'], $redacted['x-signature']);
        $this->assertSame(['[REDACTED]'], $redacted['custom-header']);

        // Safe allow-listed headers are preserved
        $this->assertSame(['application/json'], $redacted['content-type']);
        $this->assertSame(['application/json'], $redacted['accept']);
    }

    public function test_error_report_service_redacts_request_headers(): void
    {
        $this->app->make('router')->get('/test-error-report', function (ErrorReportService $service) {
            $report = $service->reportError('Test failure');

            return response()->json($report->context);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer sensitive-token-here',
            'Cookie' => 'laravel_session=secret-session-data',
            'X-Api-Key' => 'test-key-abc',
            'X-PushCommKey' => 'secret-adms-commkey-in-header',
            'Custom-Header' => 'should-be-redacted-by-allowlist',
            'Accept' => 'application/json',
        ])->getJson('/test-error-report');

        $response->assertOk();
        $context = $response->json();
        $this->assertIsArray($context);
        $this->assertArrayHasKey('headers', $context);
        $headers = $context['headers'];
        $this->assertIsArray($headers);

        // Sensitive and unapproved headers must be redacted
        $this->assertSame(['[REDACTED]'], $headers['authorization'] ?? null);
        $this->assertSame(['[REDACTED]'], $headers['cookie'] ?? null);
        $this->assertSame(['[REDACTED]'], $headers['x-api-key'] ?? null);
        $this->assertSame(['[REDACTED]'], $headers['x-pushcommkey'] ?? null);
        $this->assertSame(['[REDACTED]'], $headers['custom-header'] ?? null);

        // Safe allow-listed header preserved
        $this->assertSame(['application/json'], $headers['accept'] ?? null);
    }

    public function test_failure_reason_is_redacted(): void
    {
        $payload = [
            'failure_reason' => 'Secret credential failed during sync',
            'failurereason' => 'Sensitive device error detail',
            'command_status' => 'FAILED',
        ];

        $redacted = BiometricLogRedactor::redact($payload);

        $this->assertIsString($redacted['failure_reason']);
        $this->assertStringContainsString('***', $redacted['failure_reason']);
        $this->assertStringNotContainsString('Secret credential', $redacted['failure_reason']);

        $this->assertIsString($redacted['failurereason']);
        $this->assertStringContainsString('***', $redacted['failurereason']);
        $this->assertStringNotContainsString('Sensitive device error', $redacted['failurereason']);

        $this->assertSame('FAILED', $redacted['command_status']);
    }

    public function test_redact_handles_throwable_safely(): void
    {
        $exception = new RuntimeException('Database connection lost');

        $payload = [
            'status' => 'error',
            'exception' => $exception,
        ];

        $redacted = BiometricLogRedactor::redact($payload);

        $this->assertSame('error', $redacted['status']);
        $this->assertSame('[EXCEPTION: RuntimeException]', $redacted['exception']);
    }

    public function test_redact_text_handles_assignments_bearer_tokens_and_double_encoding(): void
    {
        $secret = 'P' . bin2hex(random_bytes(4));

        $text = "failed for pushcommkey={$secret}; Authorization: Bearer abc.def.ghi; pushcomm%256Bey=749201";

        $redacted = BiometricLogRedactor::redactText($text);

        $this->assertStringNotContainsString($secret, $redacted);
        $this->assertStringNotContainsString('abc.def.ghi', $redacted);
        $this->assertStringNotContainsString('749201', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    public function test_exception_message_trace_and_markdown_are_redacted_in_error_reports(): void
    {
        $secret = 'P' . bin2hex(random_bytes(4));

        $exception = new RuntimeException("failed for pushcommkey={$secret}");

        $report = resolve(ErrorReportService::class)->reportError(
            $exception->getMessage(),
            userDescription: "operator saw pushcommkey={$secret}",
            exception: $exception,
        );

        $this->assertStringNotContainsString($secret, (string) $report->error_message);
        $this->assertStringNotContainsString($secret, (string) $report->user_description);
        $this->assertStringNotContainsString($secret, (string) $report->stack_trace);
        $this->assertStringNotContainsString($secret, (string) $report->markdown_report);
        $this->assertStringNotContainsString($secret, (string) json_encode($report->context));
    }

    public function test_stack_trace_does_not_include_call_arguments(): void
    {
        $secret = 'P' . bin2hex(random_bytes(4));

        try {
            (function (string $value): void {
                throw new RuntimeException('failed while handling the value');
            })($secret);
        } catch (RuntimeException $exception) {
            $report = resolve(ErrorReportService::class)->reportError(
                $exception->getMessage(),
                exception: $exception,
            );
        }

        $this->assertStringNotContainsString($secret, (string) ($report->stack_trace ?? ''));
        $this->assertStringNotContainsString($secret, (string) ($report->markdown_report ?? ''));
    }

    public function test_exception_storage_session_payload_is_redacted(): void
    {
        $secret = 'P' . bin2hex(random_bytes(4));
        $exception = new RuntimeException("failed for pushcommkey={$secret}");

        $id = resolve(ExceptionStorageService::class)->store($exception);
        $payload = session()->get($id);

        $this->assertIsArray($payload);
        $this->assertStringNotContainsString($secret, (string) json_encode($payload));
    }

    public function test_redact_for_presentation_masks_sensitive_values(): void
    {
        $payload = [
            'Pin' => '1042',
            'Name' => 'John Doe',
            'password' => 'secret123',
            'device_id' => 5,
        ];

        $presentation = BiometricLogRedactor::redactForPresentation($payload);

        $this->assertArrayHasKey('Pin', $presentation);
        $this->assertSame('***', $presentation['Pin']);
        $this->assertSame('Jo****oe', $presentation['Name']);
        $this->assertSame(5, $presentation['device_id']);
    }

    public function test_redact_text_preserves_line_structure_and_benign_text(): void
    {
        $secret = 'P' . bin2hex(random_bytes(4));

        $text = "# Title\n\n- a pushcommkey={$secret}\n- 1+2 and 100%25 stay";

        $redacted = BiometricLogRedactor::redactText($text);

        $this->assertSame("# Title\n\n- a pushcommkey=[REDACTED]\n- 1+2 and 100%25 stay", $redacted);
        $this->assertStringNotContainsString($secret, $redacted);
    }

    public function test_redact_text_does_not_corrupt_benign_diagnostics(): void
    {
        $this->assertSame(
            'Error: payment failed, please retry',
            BiometricLogRedactor::redactText('Error: payment failed, please retry')
        );
        $this->assertSame('### Error: boom', BiometricLogRedactor::redactText('### Error: boom'));
        $this->assertSame(
            'Exception: RuntimeException in /app/foo.php',
            BiometricLogRedactor::redactText('Exception: RuntimeException in /app/foo.php')
        );
        $this->assertSame(
            'data: customer record not found',
            BiometricLogRedactor::redactText('data: customer record not found')
        );
    }

    public function test_redact_text_closes_the_known_bypass_shapes(): void
    {
        $cases = [
            'URL: https://example.test/x?pushcommkey=123456',
            '- **File**: x.php:1 pushcommkey=123456',
            'SN=ABC-123 pushcommkey=123456',
            'Request failed: pushcommkey=123456',
            'Warning: token=abcdef',
            'pushcommkey[0]=123456',
            'pushcommkey%3D123456',
            'X-PushCommKey: 123456',
            'X-Comm-Key: 123456',
            'X-Adms-Comm-Key: 123456',
            'X-Device-Token: 123456',
            'pushcommkey="unterminated 123456',
            '{"pushcommkey":"abc\\"def"}',
            '{"data":{"adms_comm_key":"123456"}}',
            '{"adms_comm_key":["749201"]}',
            '"snapshot": "{\\"data\\":{\\"adms_comm_key\\":\\"749201\\"}}"',
            "verifyCommKey('749201')",
            'POST /api/v1/biometric/webhook/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        ];

        foreach ($cases as $case) {
            $redacted = BiometricLogRedactor::redactText($case);

            foreach (['123456', '749201', 'abcdef', '0123456789abcdef0123456789abcdef'] as $secret) {
                $this->assertStringNotContainsString($secret, $redacted, "Leaked [{$secret}] from: {$case}");
            }
        }
    }

    public function test_redact_text_preserves_crlf_and_tabs_while_redacting(): void
    {
        $text = "line1\r\n\tpushcommkey=123456\r\nline3";

        $this->assertSame("line1\r\n\tpushcommkey=[REDACTED]\r\nline3", BiometricLogRedactor::redactText($text));
    }

    public function test_redact_text_does_not_corrupt_php_namespace_separators(): void
    {
        $text = 'App\Services\PasswordService::reset() and App\Services\TokenService::refresh()';

        $this->assertSame($text, BiometricLogRedactor::redactText($text));
    }

    public function test_redact_text_survives_a_bounded_large_input_quickly(): void
    {
        $input = str_repeat('a', 16384);

        $start = microtime(true);
        $redacted = BiometricLogRedactor::redactText($input);
        $elapsed = microtime(true) - $start;

        $this->assertSame($input, $redacted);
        $this->assertLessThan(2.0, $elapsed, 'redactText() must stay linear on a 16 KB input.');
    }

    public function test_redact_url_redacts_the_webhook_path_token_and_header_variants(): void
    {
        $url = 'https://example.test/api/v1/biometric/webhook/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef?X-Comm-Key=749201&safe=1';

        $redacted = BiometricLogRedactor::redactUrl($url);

        $this->assertStringNotContainsString('0123456789abcdef0123456789abcdef', $redacted);
        $this->assertStringNotContainsString('749201', $redacted);
        $this->assertStringContainsString('/api/v1/biometric/webhook/[REDACTED]', $redacted);
        $this->assertStringContainsString('safe=1', $redacted);
    }

    public function test_redact_headers_redacts_credentials_inside_allowed_values(): void
    {
        $redacted = BiometricLogRedactor::redactHeaders([
            'user-agent' => ['client pushcommkey=123456'],
            'origin' => ['https://example.test/x?pushcommkey=749201'],
            'accept' => ['application/json'],
        ]);

        $this->assertStringNotContainsString('123456', (string) json_encode($redacted['user-agent']));
        $this->assertStringNotContainsString('749201', (string) json_encode($redacted['origin']));
        $this->assertSame(['application/json'], $redacted['accept']);
    }

    public function test_redact_strings_preserves_benign_leaves_and_redacts_urls(): void
    {
        $redacted = BiometricLogRedactor::redactStrings([
            'route' => 'test.route',
            'origin_url' => 'https://example.test/x?pushcommkey=749201',
            'nested' => ['referer' => 'https://example.test/api/v1/biometric/webhook/0123456789abcdef'],
        ]);

        $this->assertSame('test.route', $redacted['route']);
        $this->assertStringNotContainsString('749201', (string) $redacted['origin_url']);
        $this->assertStringNotContainsString('0123456789abcdef', (string) ($redacted['nested']['referer'] ?? ''));
    }

    public function test_redact_text_handles_basic_digest_array_dumps_and_header_names(): void
    {
        $text = "Authorization: Basic dXNlcjpwYXNz\n"
            . "Proxy-Authorization: Digest username=\"u\", response=\"abcdef\"\n"
            . "array ( 'pushcommkey' => '123456' )\n"
            . "X-API-Key: secret-api-key\n"
            . "admsCommKeyHash: deadbeef\n"
            . 'admsCommKeySalt: cafebabe';

        $redacted = BiometricLogRedactor::redactText($text);

        $this->assertStringNotContainsString('dXNlcjpwYXNz', $redacted);
        $this->assertStringNotContainsString('abcdef', $redacted);
        $this->assertStringNotContainsString('123456', $redacted);
        $this->assertStringNotContainsString('secret-api-key', $redacted);
        $this->assertStringNotContainsString('deadbeef', $redacted);
        $this->assertStringNotContainsString('cafebabe', $redacted);
        $this->assertStringContainsString('Authorization: Basic [REDACTED]', $redacted);
        $this->assertStringContainsString('Proxy-Authorization: Digest [REDACTED]', $redacted);
    }

    public function test_redact_handles_dotted_paths_and_serialized_json(): void
    {
        $redacted = BiometricLogRedactor::redact([
            'data.adms_comm_key' => '749201',
            'updates.data.provider_config.webhook_secret' => 'webhook-secret',
            'snapshot' => json_encode([
                'data' => [
                    'adms_comm_key' => '123456',
                    'provider_config' => ['webhook_secret' => 'nested-secret'],
                ],
            ], JSON_THROW_ON_ERROR),
            'safe' => 'value',
        ]);

        $encoded = (string) json_encode($redacted);

        $this->assertStringNotContainsString('749201', $encoded);
        $this->assertStringNotContainsString('webhook-secret', $encoded);
        $this->assertStringNotContainsString('123456', $encoded);
        $this->assertStringNotContainsString('nested-secret', $encoded);
        $this->assertSame('value', $redacted['safe']);
    }

    public function test_error_report_from_a_webhook_request_does_not_store_the_path_token(): void
    {
        $token = str_repeat('ab', 32);

        $this->app->make('router')->get('/test/api/v1/biometric/webhook/{token}', function (ErrorReportService $service) {
            $service->reportError('boom');

            return response('ok');
        });

        $this->get('/test/api/v1/biometric/webhook/' . $token)->assertOk();

        $report = ErrorReport::query()->latest('id')->firstOrFail();

        foreach (['origin_url', 'context', 'markdown_report', 'error_message'] as $field) {
            $this->assertStringNotContainsString($token, (string) json_encode($report->{$field}));
        }
    }

    public function test_livewire_snapshot_and_updates_are_not_stored_in_error_reports(): void
    {
        // Generate at runtime so the formatter's source-code snippet does not
        // contain the literal value being asserted absent.
        $commKey = (string) random_int(100000, 999999);
        $webhookSecret = 'whsec_' . bin2hex(random_bytes(6));

        $this->app->make('router')->post('/test-livewire-error-report', function (ErrorReportService $service) {
            $service->reportError('boom', exception: new RuntimeException('boom'));

            return response('ok');
        });

        $this->post('/test-livewire-error-report', [
            'components' => [[
                'snapshot' => json_encode([
                    'data' => [
                        'adms_comm_key' => $commKey,
                        'provider_config' => ['webhook_secret' => $webhookSecret],
                    ],
                ], JSON_THROW_ON_ERROR),
            ]],
            'updates' => [
                'data.adms_comm_key' => $commKey,
                'data.provider_config.webhook_secret' => $webhookSecret,
            ],
        ])->assertOk();

        $report = ErrorReport::query()->latest('id')->firstOrFail();

        foreach (['context', 'markdown_report', 'error_message', 'stack_trace'] as $field) {
            $encoded = (string) json_encode($report->{$field});
            $this->assertStringNotContainsString($commKey, $encoded);
            $this->assertStringNotContainsString($webhookSecret, $encoded);
        }

        $requestData = $report->context['request_data'] ?? [];
        $this->assertIsArray($requestData);
        $this->assertArrayNotHasKey('components', $requestData);
        $this->assertArrayNotHasKey('updates', $requestData);

        $sessionPayload = (string) json_encode(session()->all());
        $this->assertStringNotContainsString($commKey, $sessionPayload);
        $this->assertStringNotContainsString($webhookSecret, $sessionPayload);
    }
}
