<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricLogRedactor;
use App\Services\ErrorReporting\RequestOriginResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BiometricLogRedactorRound9Test extends TestCase
{
    #[DataProvider('structuralJsonProvider')]
    public function test_json_documents_with_only_a_sensitive_key_are_redacted(string $input, string $expected): void
    {
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);
        $this->assertStringNotContainsString('749201', $actual);
        $this->assertStringNotContainsString('FMR-TEMPLATE', $actual);
        $this->assertStringNotContainsString('M-123', $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function structuralJsonProvider(): array
    {
        return [
            'data only' => ['{"data":"749201"}', '{"data":"***"}'],
            'fingerprint only' => ['{"fingerprint":"FMR-TEMPLATE"}', '{"fingerprint":"***"}'],
            'member id only' => ['{"user_id":"M-123"}', '{"user_id":"***"}'],
            'nested data only' => ['{"data":{"data":"749201"}}', '{"data":{"data":"***"}}'],
            'prefixed document' => ['Exception: {"data":"749201"}', 'Exception: {"data":"***"}'],
        ];
    }

    #[DataProvider('separatedLabelProvider')]
    public function test_separated_labels_redact_alphanumeric_and_grouped_values(string $input, string $expected): void
    {
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);
        $this->assertStringNotContainsString('ABCDEF', $actual);
        $this->assertStringNotContainsString('12ab34', $actual);
        $this->assertStringNotContainsString('123-456', $actual);
        $this->assertStringNotContainsString('4111-1111', $actual);
        $this->assertStringNotContainsString('749201', $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function separatedLabelProvider(): array
    {
        return [
            'space alpha key' => ['pushcommkey ABCDEF', 'pushcommkey [REDACTED]'],
            'camel alpha key' => ['PushCommKey ABCDEF', 'PushCommKey [REDACTED]'],
            'comm key alpha' => ['comm_key ABCDEF', 'comm_key [REDACTED]'],
            'mixed key' => ['pushcommkey 12ab34', 'pushcommkey [REDACTED]'],
            'hyphenated key' => ['pushcommkey=123-456', 'pushcommkey=[REDACTED]'],
            'hyphenated card' => ['Cards: 4111-1111-1111-1111', 'Cards: [REDACTED]'],
            'var dump annotation' => ['pushcommkey string(6) "749201"', 'pushcommkey [REDACTED]'],
            'serialized value' => ['pushcommkey: s:6:"749201";', 'pushcommkey: [REDACTED]'],
        ];
    }

    public function test_separated_label_redaction_runs_through_request_sanitisation(): void
    {
        $redacted = BiometricLogRedactor::sanitizeRequestData([
            'note' => 'pushcommkey ABCDEF',
            'safe' => 'benign',
        ]);

        $this->assertSame('pushcommkey [REDACTED]', $redacted['note']);
        $this->assertSame('benign', $redacted['safe']);
    }

    #[DataProvider('urlNameProvider')]
    public function test_redact_url_never_echoes_alphabetic_or_grouped_name_suffixes(string $query, string $expectedQuery): void
    {
        $base = 'https://example.test/x';
        $actual = BiometricLogRedactor::redactUrl($base . $query);

        $this->assertSame($base . $expectedQuery, $actual);
        $this->assertStringNotContainsString('ABC', $actual);
        $this->assertStringNotContainsString('749201', $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function urlNameProvider(): array
    {
        return [
            'alphabetic suffix' => ['?pushcommkeyABC', '?pushcommkey=[REDACTED]'],
            'alphabetic digit suffix' => ['?pushcommkeyABC123', '?pushcommkey=[REDACTED]'],
            'dash suffix' => ['?pushcommkey-ABCDEF', '?pushcommkey=[REDACTED]'],
            'nested value' => ['?note=pushcommkeyABC', '?note=[REDACTED]'],
            'array style name' => ['?pushcommkey[0]=749201', '?pushcommkey[0]=[REDACTED]'],
            'array style with key suffix' => ['?pushcommkeyABC[0]=749201', '?pushcommkey=[REDACTED]'],
        ];
    }

    public function test_redact_text_redacts_name_embedded_comm_keys_in_urls(): void
    {
        $this->assertSame('?pushcommkey=[REDACTED]', BiometricLogRedactor::redactText('?pushcommkeyABC'));
        $this->assertSame('?note=[REDACTED]', BiometricLogRedactor::redactText('?note=pushcommkeyABC'));
    }

    public function test_request_origin_resolver_redacts_alphabetic_name_suffixes(): void
    {
        $request = Request::create('http://gym.test/api/iclock/cdata?SN=ABC&pushcommkeyABC', 'GET');

        $origin = (new RequestOriginResolver())->resolve($request);

        $this->assertStringNotContainsString('pushcommkeyABC', $origin['requested_url']);
        $this->assertStringNotContainsString('pushcommkeyABC', $origin['origin_url']);
        $this->assertStringContainsString('pushcommkey=[REDACTED]', $origin['requested_url']);

        $nested = Request::create('http://gym.test/path?note=pushcommkeyABC', 'GET');
        $nestedOrigin = (new RequestOriginResolver())->resolve($nested);

        $this->assertStringNotContainsString('pushcommkeyABC', $nestedOrigin['requested_url']);
        $this->assertStringContainsString('note=[REDACTED]', $nestedOrigin['requested_url']);
    }

    #[DataProvider('malformedSerializedProvider')]
    public function test_malformed_serialized_containers_fail_closed(string $input): void
    {
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame('[TRUNCATED]', $actual);
        $this->assertSame($actual, BiometricLogRedactor::redactText($actual));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedSerializedProvider(): array
    {
        return [
            'negative array count' => ['a:-1:{s:11:"pushcommkey";s:6:"749201";}'],
            'non-numeric array count' => ['a:x:{s:11:"pushcommkey";s:6:"749201";}'],
            'negative object count' => ['O:-1:"stdClass":1:{s:11:"pushcommkey";s:6:"749201";}'],
            'negative string length' => ['s:-1:"749201"'],
            'non-numeric string length' => ['s:x:"749201"'],
        ];
    }

    public function test_digit_group_heavy_value_stays_linear_and_redacts(): void
    {
        $input = 'pushcommkey=' . implode(',', array_fill(0, 6000, '1'));
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame('pushcommkey=[REDACTED]', $actual);
        $this->assertStringNotContainsString('REDACTION FAILED', $actual);

        $spaced = 'pushcommkey=' . implode(' ', array_fill(0, 6000, '1'));
        $this->assertSame('pushcommkey=[REDACTED]', BiometricLogRedactor::redactText($spaced));
    }

    #[DataProvider('yamlIndicatorProvider')]
    public function test_yaml_explicit_indentation_indicators_redact_the_body(string $input): void
    {
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertStringNotContainsString('749201', $actual);
        $this->assertStringContainsString('[REDACTED]', $actual);
        $this->assertStringContainsString('next: ok', $actual);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function yamlIndicatorProvider(): array
    {
        return [
            'explicit indent' => ["pushcommkey: |2\n  749201\nnext: ok"],
            'explicit indent strip' => ["pushcommkey: |2-\n  749201\nnext: ok"],
            'strip indent' => ["pushcommkey: |-2\n  749201\nnext: ok"],
            'folded indent' => ["pushcommkey: >2\n  749201\nnext: ok"],
            'zero indent body' => ["pushcommkey: |\n749201\nnext: ok"],
        ];
    }

    #[DataProvider('encodedSeparatorProvider')]
    public function test_bare_encoded_separators_are_redacted(string $input, string $expected): void
    {
        $this->assertSame($expected, BiometricLogRedactor::redactText($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function encodedSeparatorProvider(): array
    {
        return [
            'encoded space' => ['pushcommkey%20749201', 'pushcommkey%20[REDACTED]'],
            'plus' => ['pushcommkey+749201', 'pushcommkey+[REDACTED]'],
            'encoded comma' => ['pushcommkey%2C749201', 'pushcommkey%2C[REDACTED]'],
            'encoded slash' => ['pushcommkey%2F749201', 'pushcommkey%2F[REDACTED]'],
            'encoded semicolon' => ['pushcommkey%3B749201', 'pushcommkey%3B[REDACTED]'],
            'encoded underscore' => ['pushcommkey%5F749201', 'pushcommkey%5F[REDACTED]'],
        ];
    }

    #[DataProvider('credentialAliasProvider')]
    public function test_credential_aliases_bare_schemes_and_userinfo_are_redacted(string $input, string $expected): void
    {
        $this->assertSame($expected, BiometricLogRedactor::redactText($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function credentialAliasProvider(): array
    {
        return [
            'pwd' => ['pwd=749201', 'pwd=[REDACTED]'],
            'passwd' => ['passwd=749201', 'passwd=[REDACTED]'],
            'bare basic' => ['Basic dXNlcjpwYXNz', 'Basic [REDACTED]'],
            'bare digest' => ['Digest dXNlcjpwYXNz', 'Digest [REDACTED]'],
            'url userinfo' => ['https://user:pass@host/path', 'https://[REDACTED]@host/path'],
            'prose basic survives' => ['Basic authentication failed', 'Basic authentication failed'],
        ];
    }

    public function test_url_userinfo_is_stripped_from_urls(): void
    {
        $this->assertSame(
            'https://[REDACTED]@host/path?x=1',
            BiometricLogRedactor::redactUrl('https://user:pass@host/path?x=1')
        );
    }

    #[DataProvider('webhookPathProvider')]
    public function test_mixed_and_double_encoded_webhook_paths_are_redacted(string $prefix): void
    {
        $token = str_repeat('a', 64);
        $input = $prefix . $token;

        $text = BiometricLogRedactor::redactText($input);
        $url = BiometricLogRedactor::redactUrl($input);

        $this->assertSame($prefix . '[REDACTED]', $text);
        $this->assertSame($prefix . '[REDACTED]', $url);
        $this->assertStringNotContainsString($token, $text);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function webhookPathProvider(): array
    {
        return [
            'encoded webhook' => ['%2Fwebhook/'],
            'mixed encoding' => ['/%2Fwebhook/'],
            'double encoding' => ['%252Fwebhook%252F'],
            'encoded biometric webhook' => ['%2Fbiometric%2Fwebhook%2F'],
        ];
    }

    public function test_url_cap_boundary_redacts_a_key_at_position_two_hundred(): void
    {
        $parameters = [];

        for ($i = 0; $i < 199; $i++) {
            $parameters[] = 'p' . $i . '=v' . $i;
        }

        $parameters[] = 'pushcommkey=749201';
        $parameters[] = 'safe=1';

        $redacted = BiometricLogRedactor::redactUrl('https://example.test/x?' . implode('&', $parameters));

        $this->assertStringNotContainsString('749201', $redacted);
        $this->assertStringContainsString('pushcommkey=[REDACTED]', $redacted);
        $this->assertStringContainsString('[TRUNCATED]', $redacted);
    }

    public function test_round_nine_probes_are_idempotent(): void
    {
        $probes = [
            'pushcommkey ABCDEF',
            'pushcommkey=123-456',
            'pushcommkey string(6) "749201"',
            'pushcommkey: s:6:"749201";',
            '{"data":"749201"}',
            '{"fingerprint":"FMR-TEMPLATE"}',
            'a:-1:{s:11:"pushcommkey";s:6:"749201";}',
            's:x:"749201"',
            '?pushcommkeyABC',
            '?note=pushcommkeyABC',
            'pwd=749201',
            'Basic dXNlcjpwYXNz',
            'https://user:pass@host/path',
        ];

        foreach ($probes as $probe) {
            $once = BiometricLogRedactor::redactText($probe);
            $twice = BiometricLogRedactor::redactText($once);

            $this->assertSame($once, $twice, $probe);
            $this->assertStringNotContainsString('749201', $once, $probe);
            $this->assertStringNotContainsString('ABC', $once, $probe);
        }
    }

    #[DataProvider('benignLargePayloadProvider')]
    public function test_large_benign_payloads_are_bounded(int $pairs): void
    {
        $input = implode(' ', array_map(
            static fn (int $index): string => 'a' . $index . '=v' . $index,
            range(1, $pairs)
        ));

        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame(strlen($input), strlen($actual));
        $this->assertStringNotContainsString('REDACTION FAILED', $actual);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function benignLargePayloadProvider(): array
    {
        return [
            'ten thousand pairs' => [10000],
            'twenty thousand pairs' => [20000],
        ];
    }
}
