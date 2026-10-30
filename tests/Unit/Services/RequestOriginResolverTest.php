<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ErrorReporting\RequestOriginResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RequestOriginResolverTest extends TestCase
{
    #[Test]
    public function adms_query_secrets_are_redacted_from_requested_and_origin_urls(): void
    {
        $request = Request::create(
            'http://gym.test/api/iclock/cdata?SN=ABC123&pushcommkey=749201&table=ATTLOG',
            'GET',
            server: ['HTTP_REFERER' => 'http://gym.test/api/iclock/cdata?SN=ABC123&pushcommkey=749201']
        );

        $origin = (new RequestOriginResolver())->resolve($request);

        $this->assertStringContainsString('pushcommkey=[REDACTED]', $origin['requested_url']);
        $this->assertStringContainsString('pushcommkey=[REDACTED]', $origin['origin_url']);
        $this->assertStringNotContainsString('749201', $origin['requested_url']);
        $this->assertStringNotContainsString('749201', $origin['origin_url']);
        $this->assertStringContainsString('SN=ABC123', $origin['requested_url']);
    }

    #[Test]
    public function redact_url_masks_all_supported_secret_parameters_case_insensitively(): void
    {
        $resolver = new RequestOriginResolver();

        $this->assertSame(
            'https://gym.test/x?PushCommKey=[REDACTED]&token=[REDACTED]&device_token=[REDACTED]&comm_key=[REDACTED]&safe=1',
            $resolver->redactUrl('https://gym.test/x?PushCommKey=749201&token=abc&device_token=def&comm_key=ghi&safe=1')
        );
    }

    #[Test]
    public function redact_url_masks_encoded_names_and_array_style_parameters(): void
    {
        $resolver = new RequestOriginResolver();

        $this->assertSame(
            'https://gym.test/x?pushcomm%6Bey=[REDACTED]&pushcommkey[0]=[REDACTED]&safe=1',
            $resolver->redactUrl('https://gym.test/x?pushcomm%6Bey=749201&pushcommkey[0]=123456&safe=1')
        );

        $this->assertStringNotContainsString(
            '749201',
            $resolver->redactUrl('https://gym.test/x?pushcomm%6Bey=749201')
        );
    }

    #[Test]
    public function redact_url_masks_double_encoded_names_and_strips_control_characters(): void
    {
        $resolver = new RequestOriginResolver();

        $doubleEncoded = $resolver->redactUrl('https://gym.test/x?pushcomm%256Bey=749201&safe=1');
        $this->assertStringNotContainsString('749201', $doubleEncoded);
        $this->assertStringContainsString('[REDACTED]', $doubleEncoded);

        $newline = $resolver->redactUrl('https://gym.test/x?pushcommkey=123%0A456');
        $this->assertStringNotContainsString("\n", $newline);
        $this->assertStringNotContainsString('123', $newline);
    }
}
