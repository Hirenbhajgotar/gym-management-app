<?php

declare(strict_types=1);

namespace Tests\Feature;

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

    public function test_mask_helper(): void
    {
        $this->assertEquals('', BiometricLogRedactor::mask(null));
        $this->assertEquals('****', BiometricLogRedactor::mask('1234'));
        $this->assertEquals('12*45', BiometricLogRedactor::mask('12345'));
        $this->assertEquals('12******90', BiometricLogRedactor::mask('1234567890'));
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
}
