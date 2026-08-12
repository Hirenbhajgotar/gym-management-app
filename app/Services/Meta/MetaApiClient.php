<?php

declare(strict_types=1);

namespace App\Services\Meta;

use Exception;
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
    public function makeRequest(string $method, string $endpoint, array $data = [], ?string $token = null): array
    {
        $token = $token ?? $this->context->getAccessToken();
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
                /** @var array{message?: string, code?: int, error_subcode?: int, error_user_title?: string, error_user_msg?: string, error_data?: array{details?: mixed}} $errorBody */
                $errorBody = (array) ($response->json('error') ?? []);
                $message = $errorBody['message'] ?? null;
                $fallbackBody = $response->body();
                $error = is_string($message) ? $message : (is_string($fallbackBody) ? $fallbackBody : '');

                $code = $errorBody['code'] ?? null;
                if (is_int($code)) {
                    $error = "(#{$code}) {$error}";
                }

                $metaParts = [];

                $errorSubcode = $errorBody['error_subcode'] ?? null;
                if (is_int($errorSubcode)) {
                    $metaParts[] = "subcode={$errorSubcode}";
                }

                $errorUserTitle = $errorBody['error_user_title'] ?? null;
                if (is_string($errorUserTitle) && $errorUserTitle !== '') {
                    $metaParts[] = "title: {$errorUserTitle}";
                }

                $errorUserMsg = $errorBody['error_user_msg'] ?? null;
                if (is_string($errorUserMsg) && $errorUserMsg !== '') {
                    $metaParts[] = $errorUserMsg;
                }

                if ($metaParts !== []) {
                    $error .= ' | ' . implode(' | ', $metaParts);
                }

                $errorData = $errorBody['error_data'] ?? null;
                $details = is_array($errorData) ? ($errorData['details'] ?? null) : null;
                if ($details !== null && $details !== []) {
                    $detailText = is_array($details)
                        ? (string) json_encode($details)
                        : (is_scalar($details) ? (string) $details : '');
                    if ($detailText !== '') {
                        $error .= ' (' . $detailText . ')';
                    }
                }

                return [
                    'success' => false,
                    'error' => $error,
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];
        } catch (Exception $e) {
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
     *
     * @param  string  $endpoint  API endpoint (e.g., '/phone_number_id')
     * @param  array<string, mixed>  $data  Query parameters
     * @return array{success: bool, data?: array, error?: string}
     */
    public function get(string $endpoint, array $data = [], ?string $token = null): array
    {
        return $this->makeRequest('get', $endpoint, $data, $token);
    }

    /**
     * Make a POST request.
     *
     * @param  string  $endpoint  API endpoint (e.g., '/messages')
     * @param  array<string, mixed>  $data  Request payload
     * @return array{success: bool, data?: array, error?: string}
     */
    public function post(string $endpoint, array $data = [], ?string $token = null): array
    {
        return $this->makeRequest('post', $endpoint, $data, $token);
    }
}
