<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for Meta Graph API.
 *
 * Wraps Laravel Http facade with Meta-specific authentication,
 * error handling, and response formatting.
 */
class MetaApiClient
{
    public function __construct(
        protected MetaContext $context
    ) {}

    /**
     * Make an API request to Meta Graph API.
     *
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @param  string  $endpoint  API endpoint (e.g., '/messages')
     * @param  array  $data  Request payload
     * @return array{success: bool, data?: array, error?: string}
     */
    public function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->context->getAccessToken();
        $baseUrl = $this->context->getApiBaseUrl();

        if (! $token) {
            return [
                'success' => false,
                'error' => 'Meta access token not configured',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->$method("{$baseUrl}{$endpoint}", $data);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? $response->body();

                return [
                    'success' => false,
                    'error' => $error,
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Meta API request failed', [
                'gym_id' => $this->context->gymId,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make a GET request.
     */
    public function get(string $endpoint, array $data = []): array
    {
        return $this->makeRequest('get', $endpoint, $data);
    }

    /**
     * Make a POST request.
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->makeRequest('post', $endpoint, $data);
    }

    /**
     * Make a raw HTTP request (for OAuth, etc.)
     */
    public function makeRawRequest(string $url, array $params = []): array
    {
        try {
            $response = Http::get($url, $params);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'status' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
