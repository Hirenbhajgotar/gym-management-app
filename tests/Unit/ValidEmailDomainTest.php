<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\ValidEmailDomain;
use Closure;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ValidEmailDomain::class)]
final class ValidEmailDomainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ValidEmailDomain::$resolver = null;
        Cache::flush();
    }

    protected function tearDown(): void
    {
        ValidEmailDomain::$resolver = null;
        Cache::flush();
        parent::tearDown();
    }

    /**
     * @return Closure(string): PotentiallyTranslatedString
     */
    private function failCallback(bool &$failed, string &$failMessage = ''): Closure
    {
        return function (string $message) use (&$failed, &$failMessage): PotentiallyTranslatedString {
            $failed = true;
            $failMessage = $message;

            /** @var Translator $translator */
            $translator = app('translator');

            return new PotentiallyTranslatedString($message, $translator);
        };
    }

    #[Test]
    public function it_passes_when_resolver_returns_true(): void
    {
        ValidEmailDomain::$resolver = fn (string $domain): bool => true;

        $rule = new ValidEmailDomain();
        $failed = false;

        $rule->validate('email', 'test@example.com', $this->failCallback($failed));

        $this->assertFalse($failed);
    }

    #[Test]
    public function it_fails_when_resolver_returns_false(): void
    {
        ValidEmailDomain::$resolver = fn (string $domain): bool => false;

        $rule = new ValidEmailDomain();
        $failed = false;
        $failMessage = '';

        $rule->validate('email', 'test@invalid-domain.xyz', $this->failCallback($failed, $failMessage));

        $this->assertTrue($failed);
        $this->assertStringContainsString('The :attribute domain does not appear to be valid', $failMessage);
    }

    #[Test]
    public function it_skips_when_dns_check_disabled_in_config(): void
    {
        Config::set('request-demo.dns_check_enabled', false);
        ValidEmailDomain::$resolver = fn (string $domain): bool => false;

        $rule = new ValidEmailDomain();
        $failed = false;

        $rule->validate('email', 'test@invalid-domain.xyz', $this->failCallback($failed));

        $this->assertFalse($failed);
    }

    #[Test]
    public function it_ignores_invalid_email_format_and_empty_domain(): void
    {
        $rule = new ValidEmailDomain();
        $failed = false;

        $rule->validate('email', 'not-an-email', $this->failCallback($failed));
        $rule->validate('email', 'user@', $this->failCallback($failed));
        $rule->validate('email', ['array-value'], $this->failCallback($failed));

        $this->assertFalse($failed);
    }

    #[Test]
    public function it_ignores_overly_long_domain_names(): void
    {
        $rule = new ValidEmailDomain();
        $failed = false;

        $longDomain = str_repeat('a', 255) . '.com';
        $rule->validate('email', "user@{$longDomain}", $this->failCallback($failed));

        $this->assertFalse($failed);
    }
}
