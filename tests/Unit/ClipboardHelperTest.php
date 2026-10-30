<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\ClipboardHelper;
use Tests\TestCase;

class ClipboardHelperTest extends TestCase
{
    public function test_copy_script_never_emits_an_empty_javascript_value_for_invalid_utf8(): void
    {
        $script = ClipboardHelper::copyScript(
            "\xB1\x31",
            'Copied',
            'Copy failed — select the text manually'
        );

        $this->assertStringNotContainsString('const text = ;', $script);
        $this->assertStringContainsString('\ufffd', $script);

        $this->assertSame(1, preg_match('/const text = (".*?");/s', $script, $matches));
        $this->assertIsString(json_decode($matches[1], true));
    }

    public function test_copy_script_escapes_script_tags_quotes_and_ampersands(): void
    {
        $value = "</script><script>alert(\"x\")</script>'&";

        $script = ClipboardHelper::copyScript($value);

        $this->assertStringNotContainsString('</script>', $script);
        $this->assertStringNotContainsString('<script>', $script);
        $this->assertStringContainsString('\u003C', $script);
        $this->assertStringContainsString('\u0022', $script);
        $this->assertStringContainsString('\u0027', $script);
        $this->assertStringContainsString('\u0026', $script);

        $this->assertSame(1, preg_match('/const text = (".*?");/s', $script, $matches));
        $this->assertSame($value, json_decode($matches[1], true));
    }

    public function test_copy_script_encodes_null_success_and_failure_bodies(): void
    {
        $script = ClipboardHelper::copyScript('copy-me');

        $this->assertStringContainsString('const successBody = null;', $script);
        $this->assertStringContainsString('const failureBody = null;', $script);
        $this->assertStringNotContainsString('text === null', $script);
    }

    public function test_copy_script_contains_secure_context_fallback_and_failure_feedback(): void
    {
        $script = ClipboardHelper::copyScript(
            'copy-me',
            'Copied',
            'Copy failed — select the text manually'
        );

        $this->assertStringContainsString('window.isSecureContext', $script);
        $this->assertStringContainsString('document.execCommand', $script);
        $this->assertStringContainsString('Copy failed', $script);
        $this->assertStringNotContainsString('TTL_MINUTES', $script);
    }
}
