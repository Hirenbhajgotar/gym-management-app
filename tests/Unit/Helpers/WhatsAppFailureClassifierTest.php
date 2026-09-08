<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\WhatsAppFailureClassifier;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\TestCase;
use Twilio\Exceptions\RestException;

class WhatsAppFailureClassifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_identifies_laravel_connection_exception_as_connection_error(): void
    {
        $e = new ConnectionException('Connection timed out');
        $this->assertTrue(WhatsAppFailureClassifier::isConnectionException($e));
    }

    #[Test]
    public function it_identifies_guzzle_connect_exception_as_connection_error(): void
    {
        /** @var RequestInterface $requestMock */
        $requestMock = Mockery::mock(RequestInterface::class);
        $e = new ConnectException('Connection refused', $requestMock);
        $this->assertTrue(WhatsAppFailureClassifier::isConnectionException($e));
    }

    #[Test]
    public function it_identifies_guzzle_request_exception_with_curl_timeout_errno(): void
    {
        /** @var RequestInterface $requestMock */
        $requestMock = Mockery::mock(RequestInterface::class);
        // errno = 28 is CURLE_OPERATION_TIMEOUTED
        $e = new RequestException('Error communicating with server', $requestMock, null, null, ['errno' => 28]);
        $this->assertTrue(WhatsAppFailureClassifier::isConnectionException($e));
    }

    #[Test]
    public function it_identifies_twilio_rest_exception_with_timeout_http_status_as_connection_error(): void
    {
        // Twilio RestException with HTTP status 408 (Request Timeout)
        $e = new RestException('Twilio timeout occurred', 12345, 408);
        $this->assertTrue(WhatsAppFailureClassifier::isConnectionException($e));
    }

    #[Test]
    public function it_identifies_wrapped_previous_connection_exceptions_correctly(): void
    {
        $connException = new ConnectionException('Connection timed out');
        $wrapped = new RuntimeException('Failed operation', 0, $connException);

        $this->assertTrue(WhatsAppFailureClassifier::isConnectionException($wrapped));
    }

    #[Test]
    public function it_does_not_classify_twilio_terminal_error_as_connection_error(): void
    {
        // Twilio error 21608 is not a timeout/connection issue (terminal error)
        $e = new RestException('The number is not a valid WhatsApp number', 21608, 400);
        $this->assertFalse(WhatsAppFailureClassifier::isConnectionException($e));
    }

    #[Test]
    public function it_identifies_transient_http_statuses(): void
    {
        foreach ([408, 500, 502, 503, 504, 524] as $status) {
            $this->assertTrue(WhatsAppFailureClassifier::isTransientServerError($status));
            $e = new RestException("Error with status {$status}", 12345, $status);
            $this->assertTrue(WhatsAppFailureClassifier::isConnectionException($e));
        }

        $this->assertFalse(WhatsAppFailureClassifier::isTransientServerError(400));
        $this->assertFalse(WhatsAppFailureClassifier::isTransientServerError(404));
        $this->assertFalse(WhatsAppFailureClassifier::isTransientServerError(422));
    }

    #[Test]
    public function it_identifies_connection_messages_by_substring(): void
    {
        $substrings = ['timeout', 'timed out', 'unable to connect', 'connect timeout', 'connection refused'];
        foreach ($substrings as $sub) {
            $this->assertTrue(WhatsAppFailureClassifier::isConnectionMessage("Some error: {$sub} in response"));
        }

        $this->assertFalse(WhatsAppFailureClassifier::isConnectionMessage('Invalid template parameters'));
    }

    #[Test]
    public function it_identifies_rate_limit_exceptions_and_messages(): void
    {
        $rest429 = new RestException('Rate limit exceeded', 12345, 429);
        $this->assertTrue(WhatsAppFailureClassifier::isRateLimitException($rest429));

        $wrappedRateLimit = new RuntimeException('Call failed', 0, $rest429);
        $this->assertTrue(WhatsAppFailureClassifier::isRateLimitException($wrappedRateLimit));

        $eCode429 = new RuntimeException('Too many calls', 429);
        $this->assertTrue(WhatsAppFailureClassifier::isRateLimitException($eCode429));

        $this->assertTrue(WhatsAppFailureClassifier::isRateLimitMessage('Rate limit exceeded for provider'));
        $this->assertTrue(WhatsAppFailureClassifier::isRateLimitMessage('Too many requests, retry after 30 seconds'));
        $this->assertTrue(WhatsAppFailureClassifier::isRateLimitMessage('Too many messages dispatched'));
        $this->assertFalse(WhatsAppFailureClassifier::isRateLimitMessage('Too many parameters provided for template'));
        $this->assertFalse(WhatsAppFailureClassifier::isRateLimitMessage('Too many recipients in batch payload'));
        $this->assertTrue(WhatsAppFailureClassifier::isRateLimitStatusCode(429));
        $this->assertFalse(WhatsAppFailureClassifier::isRateLimitStatusCode(500));
    }
}
