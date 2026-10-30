<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricLogRedactor;
use App\Services\ErrorReporting\RequestOriginResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

class BiometricLogRedactorRound8Test extends TestCase
{
    #[DataProvider('escapedQuoteProvider')]
    public function test_escaped_quotes_do_not_disable_assignment_redaction(string $input, string $expected): void
    {
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);
        $this->assertStringNotContainsString('749201', $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function escapedQuoteProvider(): array
    {
        return [
            'escaped prefix' => ['\\"x\\" pushcommkey=749201', '\\"x\\" pushcommkey=[REDACTED]'],
            'escaped quoted word' => ['note \\"quoted\\" token=749201', 'note \\"quoted\\" token=[REDACTED]'],
            'escaped quoted word colon' => ['note \\"quoted\\" pushcommkey: 749201', 'note \\"quoted\\" pushcommkey: [REDACTED]'],
            'escaped quoted value' => ['pushcommkey=\\"749201\\"', 'pushcommkey=\\"[REDACTED]\\"'],
            'escaped single-quoted value' => ["pushcommkey=\\'749201\\'", "pushcommkey=\\'[REDACTED]\\'"],
        ];
    }

    public function test_json_with_an_escaped_quote_and_assignment_stays_valid(): void
    {
        $input = (string) json_encode(
            ['note' => '\\"x\\" pushcommkey=749201'],
            JSON_UNESCAPED_SLASHES
        );
        $expected = (string) json_encode(
            ['note' => '\\"x\\" pushcommkey=[REDACTED]'],
            JSON_UNESCAPED_SLASHES
        );

        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);
        $this->assertStringNotContainsString('749201', $actual);

        $decoded = json_decode($actual);

        $this->assertInstanceOf(stdClass::class, $decoded);
        $this->assertStringNotContainsString('749201', $decoded->note);
    }

    #[DataProvider('separatedLabelProvider')]
    public function test_separated_labels_and_plural_pii_are_redacted(string $input, string $expected): void
    {
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);
        $this->assertStringNotContainsString('749201', $actual);
        $this->assertStringNotContainsString('4111111111111111', $actual);
        $this->assertStringNotContainsString('1234', $actual);
        $this->assertStringNotContainsString('rawbase64', $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function separatedLabelProvider(): array
    {
        return [
            'space' => ['pushcommkey 749201', 'pushcommkey [REDACTED]'],
            'camel case' => ['PushCommKey 749201', 'PushCommKey [REDACTED]'],
            'words and newline' => ["Push Comm Key\n749201", "Push Comm Key\n[REDACTED]"],
            'comm key' => ['comm_key 749201', 'comm_key [REDACTED]'],
            'adms comm key' => ['adms_comm_key 749201', 'adms_comm_key [REDACTED]'],
            'header prefix' => ['x-pushcommkey 749201', 'x-pushcommkey [REDACTED]'],
            'comma' => ['pushcommkey,749201', 'pushcommkey,[REDACTED]'],
            'semicolon' => ['pushcommkey;749201', 'pushcommkey;[REDACTED]'],
            'slash' => ['pushcommkey/749201', 'pushcommkey/[REDACTED]'],
            'plural pins' => ['Pins 1234', 'Pins [REDACTED]'],
            'plural cards' => ['Cards 4111111111111111', 'Cards [REDACTED]'],
            'plural cards formatted' => ['Cards: 4111 1111 1111 1111', 'Cards: [REDACTED]'],
            'plural photos' => ['Photos rawbase64', 'Photos [REDACTED]'],
            'plural images' => ['Images: rawbase64', 'Images: [REDACTED]'],
        ];
    }

    public function test_separated_labels_are_redacted_through_request_sanitisation(): void
    {
        $redacted = BiometricLogRedactor::sanitizeRequestData([
            'note' => 'pushcommkey 749201',
            'safe' => 'benign',
        ]);

        $this->assertSame('pushcommkey [REDACTED]', $redacted['note']);
        $this->assertSame('benign', $redacted['safe']);
    }

    #[DataProvider('benignTextProvider')]
    public function test_benign_diagnostics_and_short_names_are_preserved(string $input, string $expected): void
    {
        $this->assertSame($expected, BiometricLogRedactor::redactText($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function benignTextProvider(): array
    {
        $benign = 'discard=true spin=1 postcard=abc wildcard=xyz hairpin=zz scorecard=9 secretary=y imageable_type=x tokenizer=ok pinpoint=here';

        return [
            'short-name table' => [$benign, $benign],
            'prose token' => ['Login token expired; user=alice@example.test', 'Login token expired; user=alice@example.test'],
            'prose pin pad' => ['The pin pad is offline; status=degraded', 'The pin pad is offline; status=degraded'],
            'prose clock' => ['token expired at 12:30', 'token expired at 12:30'],
            'prose signature clock' => ['signature verification failed at 12:30:45', 'signature verification failed at 12:30:45'],
            'prose endpoint' => ['token endpoint https://example.test/oauth/token?grant=1', 'token endpoint https://example.test/oauth/token?grant=1'],
            'later fields survive' => ['token=abc123 user=bob status=ok', 'token=[REDACTED] user=bob status=ok'],
            'formatted credential survives later fields' => ['token=123 456 user=bob', 'token=[REDACTED] user=bob'],
            'comma-grouped credential' => ['pushcommkey=749,201', 'pushcommkey=[REDACTED]'],
            'multi-line prose' => [
                "Authentication failed for token service\nuser=alice@example.test\nstatus=active",
                "Authentication failed for token service\nuser=alice@example.test\nstatus=active",
            ],
        ];
    }

    #[DataProvider('urlParameterProvider')]
    public function test_redact_url_never_echoes_a_sensitive_parameter_name(string $query, string $expectedQuery): void
    {
        $base = 'https://example.test/x';
        $actual = BiometricLogRedactor::redactUrl($base . $query);

        $this->assertSame($base . $expectedQuery, $actual);
        $this->assertStringNotContainsString('749201', $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function urlParameterProvider(): array
    {
        return [
            'encoded space' => ['?pushcommkey%20749201', '?pushcommkey=[REDACTED]'],
            'plus' => ['?pushcommkey+749201', '?pushcommkey=[REDACTED]'],
            'comma' => ['?pushcommkey,749201', '?pushcommkey=[REDACTED]'],
            'semicolon' => ['?pushcommkey;749201', '?pushcommkey=[REDACTED]'],
            'slash' => ['?pushcommkey/749201', '?pushcommkey=[REDACTED]'],
            'double encoded space' => ['?pushcommkey%2520749201', '?pushcommkey=[REDACTED]'],
            'encoded comma' => ['?pushcommkey%2C749201', '?pushcommkey=[REDACTED]'],
            'header-style name' => ['?x-pushcommkey+749201', '?x-pushcommkey=[REDACTED]'],
            'nested value' => ['?note=pushcommkey+749201', '?note=[REDACTED]'],
            'benign nested prose' => ['?note=token+expired', '?note=token+expired'],
            'benign sibling' => ['?pushcommkey%20749201&safe=1', '?pushcommkey=[REDACTED]&safe=1'],
        ];
    }

    #[DataProvider('routeTokenProvider')]
    public function test_redact_url_covers_double_slash_and_percent_encoded_route_tokens(string $url, string $expectedPrefix): void
    {
        $token = str_repeat('ab', 32);
        $actual = BiometricLogRedactor::redactUrl(str_replace('<TOKEN>', $token, $url));

        $this->assertSame($expectedPrefix . '[REDACTED]', $actual);
        $this->assertStringNotContainsString($token, $actual);
    }

    public function test_request_origin_resolver_redacts_name_embedded_comm_keys(): void
    {
        foreach (['%20749201', '+749201', ',749201', ';749201', '/749201'] as $suffix) {
            $request = Request::create('http://gym.test/api/iclock/cdata?SN=ABC&pushcommkey' . $suffix, 'GET');
            $origin = (new RequestOriginResolver())->resolve($request);

            $this->assertStringNotContainsString('749201', $origin['requested_url'], $suffix);
            $this->assertStringNotContainsString('749201', $origin['origin_url'], $suffix);
            $this->assertStringContainsString('pushcommkey=[REDACTED]', $origin['requested_url'], $suffix);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routeTokenProvider(): array
    {
        return [
            'canonical' => [
                'https://example.test/api/v1/biometric/webhook/<TOKEN>',
                'https://example.test/api/v1/biometric/webhook/',
            ],
            'double slash' => [
                'https://example.test/sub/api/v1/biometric//webhook//<TOKEN>',
                'https://example.test/sub/api/v1/biometric//webhook//',
            ],
            'percent encoded webhook' => [
                'https://example.test/%2Fwebhook%2F<TOKEN>',
                'https://example.test/%2Fwebhook%2F',
            ],
            'percent encoded biometric webhook' => [
                'https://example.test/%2Fbiometric%2Fwebhook%2F<TOKEN>',
                'https://example.test/%2Fbiometric%2Fwebhook%2F',
            ],
        ];
    }

    public function test_redact_url_caps_processed_parameters_and_drops_the_tail(): void
    {
        $parameters = [];

        for ($i = 0; $i < 200; $i++) {
            $parameters[] = 'p' . $i . '=v' . $i;
        }

        $parameters[] = 'pushcommkey=749201';
        $parameters[] = 'safe=1';

        $redacted = BiometricLogRedactor::redactUrl('https://example.test/x?' . implode('&', $parameters));

        $this->assertStringNotContainsString('749201', $redacted);
        $this->assertStringContainsString('[TRUNCATED]', $redacted);
        $this->assertStringContainsString('p0=v0', $redacted);
    }

    public function test_serialized_redaction_emits_a_complete_valid_token(): void
    {
        $input = 'a:1:{s:11:"pushcommkey";s:6:"749201";}';
        $expected = 'a:1:{s:11:"pushcommkey";s:10:"[REDACTED]";}';

        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);
        $this->assertStringNotContainsString('749201', $actual);

        $decoded = unserialize($actual);

        $this->assertIsArray($decoded);
        $this->assertSame('[REDACTED]', $decoded['pushcommkey'] ?? null);
        $this->assertSame($actual, BiometricLogRedactor::redactText($actual));
    }

    public function test_serialized_redaction_preserves_safe_siblings(): void
    {
        $input = 'a:2:{s:11:"pushcommkey";s:6:"749201";s:4:"safe";i:1;}';
        $expected = 'a:2:{s:11:"pushcommkey";s:10:"[REDACTED]";s:4:"safe";i:1;}';

        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);

        $decoded = unserialize($actual);

        $this->assertIsArray($decoded);
        $this->assertSame('[REDACTED]', $decoded['pushcommkey'] ?? null);
        $this->assertSame(1, $decoded['safe'] ?? null);
        $this->assertSame($actual, BiometricLogRedactor::redactText($actual));
    }

    public function test_serialized_string_content_with_a_sensitive_label_stays_valid(): void
    {
        $input = serialize(['note' => 'pushcommkey 749201']);
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertStringNotContainsString('749201', $actual);
        $this->assertStringContainsString('s:4:"note";', $actual);

        $decoded = unserialize($actual);

        $this->assertIsArray($decoded);
        $this->assertSame('pushcommkey [REDACTED]', $decoded['note'] ?? null);
        $this->assertSame($actual, BiometricLogRedactor::redactText($actual));
    }

    public function test_malformed_serialized_lengths_fail_closed_without_a_key_suffix(): void
    {
        foreach ([
            'a:1:{s:11:"pushcommkey";s:1:"749201";}',
            'a:1:{s:11:"pushcommkey";s:0:"749201";}',
            's:1:"749201"',
            's:0:"749201"',
        ] as $input) {
            $actual = BiometricLogRedactor::redactText($input);

            $this->assertStringNotContainsString('749201', $actual, $input);
            $this->assertStringNotContainsString('201', $actual, $input);
            $this->assertSame('[TRUNCATED]', $actual, $input);
            $this->assertSame($actual, BiometricLogRedactor::redactText($actual), $input);
        }
    }

    public function test_serialized_object_values_are_redacted(): void
    {
        $input = 'O:8:"stdClass":1:{s:11:"pushcommkey";s:6:"749201";}';
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertStringNotContainsString('749201', $actual);

        $decoded = unserialize($actual);

        $this->assertInstanceOf(stdClass::class, $decoded);
        $this->assertSame('[REDACTED]', $decoded->pushcommkey ?? null);
    }

    #[DataProvider('yamlBlockScalarProvider')]
    public function test_yaml_block_scalars_consume_the_indented_block(string $input, string $expected): void
    {
        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);
        $this->assertStringNotContainsString('749201', $actual);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function yamlBlockScalarProvider(): array
    {
        return [
            'literal' => [
                "device:\n  pushcommkey: |\n    749201\n",
                "device:\n  pushcommkey: [REDACTED]\n",
            ],
            'folded' => [
                "device:\n  pushcommkey: >\n    749201\n",
                "device:\n  pushcommkey: [REDACTED]\n",
            ],
            'strip' => [
                "device:\n  pushcommkey: |-\n    749201\n",
                "device:\n  pushcommkey: [REDACTED]\n",
            ],
            'keep' => [
                "device:\n  pushcommkey: >+\n    749201\n",
                "device:\n  pushcommkey: [REDACTED]\n",
            ],
            'sibling preserved' => [
                "device:\n  pushcommkey: |\n    749201\n  name: ok\n",
                "device:\n  pushcommkey: [REDACTED]\n  name: ok\n",
            ],
            'top level' => [
                "pushcommkey: |\n  749201\nnext=ok",
                "pushcommkey: [REDACTED]\nnext=ok",
            ],
            'no trailing newline' => [
                "pushcommkey: |\n  749201",
                'pushcommkey: [REDACTED]',
            ],
        ];
    }

    public function test_redact_json_text_preserves_object_shape_and_precision(): void
    {
        $this->assertSame('{}', BiometricLogRedactor::redactJsonText('{}'));
        $this->assertSame('{"0":"a","1":"b"}', BiometricLogRedactor::redactJsonText('{"0":"a","1":"b"}'));
        $this->assertSame('{"value":1.0}', BiometricLogRedactor::redactJsonText('{"value":1.0}'));
        $this->assertSame('{"id":"12345678901234567890"}', BiometricLogRedactor::redactJsonText('{"id":12345678901234567890}'));
        $this->assertNull(BiometricLogRedactor::redactJsonText('not json'));

        $decoded = json_decode(BiometricLogRedactor::redactJsonText('{"id":12345678901234567890}'), true);

        $this->assertIsArray($decoded);
        $this->assertIsString($decoded['id'] ?? null);
        $this->assertSame('12345678901234567890', $decoded['id'] ?? null);
    }

    public function test_redact_text_uses_the_structural_json_redactor(): void
    {
        $input = '{"pushcommkey":"749201","value":1.0,"big":12345678901234567890}';
        $expected = '{"pushcommkey":"***","value":1.0,"big":"12345678901234567890"}';

        $actual = BiometricLogRedactor::redactText($input);

        $this->assertSame($expected, $actual);
        $this->assertIsArray(json_decode($actual, true));
        $this->assertStringNotContainsString('749201', $actual);
    }

    public function test_generic_error_keys_leaf_redact_instead_of_becoming_a_placeholder(): void
    {
        $this->assertSame(
            '{"error":"Error: payment failed, please retry"}',
            BiometricLogRedactor::redactJsonText('{"error":"Error: payment failed, please retry"}')
        );

        $this->assertSame(
            '{"error":"Error: token=[REDACTED] boom"}',
            BiometricLogRedactor::redactJsonText('{"error":"Error: token=abc123 boom"}')
        );

        $this->assertSame(
            '{"errors":["Error: token=[REDACTED] boom","benign"],"exception":"Exception: boom"}',
            BiometricLogRedactor::redactJsonText('{"errors":["Error: token=abc123 boom","benign"],"exception":"Exception: boom"}')
        );

        $redacted = BiometricLogRedactor::redact([
            'error' => 'Error: payment failed, please retry',
            'errors' => ['Error: token=abc123 boom', 'benign'],
            'exception' => 'Exception: boom',
            'safe' => 'ok',
        ]);

        $this->assertSame('Error: payment failed, please retry', $redacted['error']);
        $this->assertSame(['Error: token=[REDACTED] boom', 'benign'], $redacted['errors']);
        $this->assertSame('Exception: boom', $redacted['exception']);
        $this->assertSame('ok', $redacted['safe']);
    }

    public function test_redaction_is_idempotent_for_every_round_eight_probe(): void
    {
        $inputs = [
            '\\"x\\" pushcommkey=749201',
            'note \\"quoted\\" token=749201',
            'note \\"quoted\\" pushcommkey: 749201',
            'pushcommkey 749201',
            'PushCommKey 749201',
            "Push Comm Key\n749201",
            'comm_key 749201',
            'adms_comm_key 749201',
            'x-pushcommkey 749201',
            'pushcommkey,749201',
            'pushcommkey;749201',
            'pushcommkey/749201',
            'Pins 1234',
            'Cards 4111111111111111',
            'Photos rawbase64',
            'a:1:{s:11:"pushcommkey";s:6:"749201";}',
            'a:2:{s:11:"pushcommkey";s:6:"749201";s:4:"safe";i:1;}',
            serialize(['note' => 'pushcommkey 749201']),
            "device:\n  pushcommkey: |\n    749201\n",
        ];

        foreach ($inputs as $input) {
            $once = BiometricLogRedactor::redactText($input);

            $this->assertSame($once, BiometricLogRedactor::redactText($once), $input);
        }
    }

    public function test_a_two_hundred_kilobyte_assignment_scan_is_linear_and_does_not_leak(): void
    {
        $input = str_repeat('a=', 100000) . ' pushcommkey=123456';

        $start = microtime(true);
        $actual = BiometricLogRedactor::redactText($input);
        $elapsed = microtime(true) - $start;

        $this->assertStringNotContainsString('123456', $actual);
        $this->assertStringEndsWith('pushcommkey=[REDACTED]', $actual);
        $this->assertLessThan(3.0, $elapsed, 'The bounded assignment scan must stay linear on a 200 KB payload.');
    }
}
