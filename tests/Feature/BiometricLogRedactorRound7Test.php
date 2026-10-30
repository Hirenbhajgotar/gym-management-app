<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricLogRedactor;
use Tests\TestCase;

class BiometricLogRedactorRound7Test extends TestCase
{
    public function test_redact_text_handles_real_multiline_dump_output(): void
    {
        $varDump = "array(1) {\n  [\"pushcommkey\"]=>\n  string(6) \"123456\"\n}";
        $this->assertSame(
            "array(1) {\n  [\"pushcommkey\"]=>\n  \"[REDACTED]\"\n}",
            BiometricLogRedactor::redactText($varDump)
        );

        $printR = "Array\n(\n    [pushcommkey] => Array\n        (\n            [0] => 123456\n        )\n)";
        $this->assertSame(
            "Array\n(\n    [pushcommkey] => [REDACTED]\n)",
            BiometricLogRedactor::redactText($printR)
        );

        $nested = 'array(2) { ["pushcommkey"]=> array(1) { [0]=> string(6) "123456" } }';
        $this->assertSame(
            'array(2) { ["pushcommkey"]=> [REDACTED] }',
            BiometricLogRedactor::redactText($nested)
        );
    }

    public function test_redact_text_handles_newline_labels_and_colonless_support_labels(): void
    {
        $this->assertSame(
            "pushcommkey:\n[REDACTED]",
            BiometricLogRedactor::redactText("pushcommkey:\n123456")
        );

        $this->assertSame(
            "Push Comm Key:\n[REDACTED]",
            BiometricLogRedactor::redactText("Push Comm Key:\n123456")
        );

        $this->assertSame(
            'Push Comm Key [REDACTED]',
            BiometricLogRedactor::redactText('Push Comm Key 123456')
        );
    }

    public function test_plural_biometric_pii_names_are_redacted_in_text_and_structures(): void
    {
        $text = "Cards: 4111 1111 1111 1111\nPins: 1234\nPhotos: rawbase64\nImages: rawbase64";

        $redactedText = BiometricLogRedactor::redactText($text);

        foreach (['4111', '1234', 'rawbase64'] as $secret) {
            $this->assertStringNotContainsString($secret, $redactedText);
        }

        $redacted = BiometricLogRedactor::redact([
            'cards' => '4111111111111111',
            'pins' => [1234],
            'photos' => ['rawbase64'],
            'images' => ['rawbase64'],
        ]);

        $encoded = (string) json_encode($redacted);
        $this->assertStringNotContainsString('4111111111111111', $encoded);
        $this->assertStringNotContainsString('1234', $encoded);
        $this->assertStringNotContainsString('rawbase64', $encoded);
    }

    public function test_assignment_scanner_never_appends_an_unprocessed_tail(): void
    {
        $input = str_repeat('a=', 100001) . ' pushcommkey=123456';

        $redacted = BiometricLogRedactor::redactText($input);

        $this->assertStringNotContainsString('123456', $redacted);
        $this->assertStringEndsWith('pushcommkey=[REDACTED]', $redacted);
    }

    public function test_redact_strings_preserves_multiline_leaves_and_benign_short_words(): void
    {
        $redacted = BiometricLogRedactor::redactStrings([
            'msg' => "line1\npushcommkey=123456\nline3",
        ]);

        $this->assertSame(
            "line1\npushcommkey=[REDACTED]\nline3",
            $redacted['msg']
        );

        $benign = 'discard=true spin=1 postcard=abc wildcard=xyz hairpin=zz scorecard=9 secretary=y imageable_type=x tokenizer=ok pinpoint=here';
        $this->assertSame($benign, BiometricLogRedactor::redactText($benign));
    }

    public function test_residual_encoded_serialized_and_long_prefix_forms_are_redacted(): void
    {
        $serialized = 'a:1:{s:11:"pushcommkey";s:6:"749201";}';
        $doubleEncoded = (string) json_encode((string) json_encode(['adms_comm_key' => '749201']));
        $longPrefix = str_repeat('a', 300) . 'pushcommkey=749201';

        foreach ([
            $serialized,
            $doubleEncoded,
            $longPrefix,
            'pushcommkey=749,201',
            'payload: \"pushcommkey\":\"749201\"',
            '\"pushcommkey\":\"749201\"',
            '\"pushcommkey\":\"749201',
            "frame: BiometricWebhookController->handle('super-secret-token'",
        ] as $input) {
            $redacted = BiometricLogRedactor::redactText($input);

            $this->assertStringNotContainsString('749201', $redacted, $input);
            $this->assertStringNotContainsString('super-secret-token', $redacted, $input);
        }
    }

    public function test_nested_double_encoded_url_delimiters_are_redacted_at_write_time(): void
    {
        $url = 'https://example.test/x?next=https%3A%2F%2Fevil.test%2F%3Fpushcommkey%253D749201&safe=1';

        $redacted = BiometricLogRedactor::redactUrl($url);

        $this->assertStringNotContainsString('749201', $redacted);
        $this->assertStringContainsString('next=[REDACTED]', $redacted);
        $this->assertStringContainsString('safe=1', $redacted);
    }

    public function test_redact_headers_handles_list_shaped_historical_headers(): void
    {
        $redacted = BiometricLogRedactor::redactHeaders([
            0 => 'X-Comm-Key: 749201',
            'accept' => ['application/json'],
        ]);

        $this->assertStringNotContainsString('749201', (string) json_encode($redacted));
        $this->assertSame(['application/json'], $redacted['accept']);
    }

    public function test_request_redaction_caps_the_number_of_processed_leaves(): void
    {
        $payload = [];

        for ($i = 0; $i < 2000; $i++) {
            $payload['field_' . $i] = 'benign';
        }

        $payload['late_note'] = 'pushcommkey=749201';

        $redacted = BiometricLogRedactor::sanitizeRequestData($payload);
        $encoded = (string) json_encode($redacted);

        $this->assertStringNotContainsString('749201', $encoded);
        $this->assertSame('[TRUNCATED]', $redacted['late_note']);
    }
}
