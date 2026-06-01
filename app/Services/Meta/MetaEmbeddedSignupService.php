<?php

namespace App\Services\Meta;

use App\Interfaces\EmbeddedSignupInterface;
use App\Models\WabaSetupSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Embedded Signup Service.
 *
 * Implements EmbeddedSignupInterface for Meta OAuth embedded signup flow.
 */
class MetaEmbeddedSignupService implements EmbeddedSignupInterface
{
    public function __construct(
        protected MetaContext $context,
        protected MetaApiClient $api
    ) {}

    public function startEmbeddedSignup(int $gymId): string
    {
        $session = WabaSetupSession::createForGym($gymId);
        $config = $this->getMetaAppConfig();

        $params = http_build_query([
            'client_id' => $config['app_id'],
            'redirect_uri' => $config['redirect_uri'],
            'state' => $session->session_id,
            'config_id' => $config['config_id'],
            'response_type' => 'code',
            'scope' => 'whatsapp_business_management,whatsapp_business_messaging,business_management',
        ]);

        return "https://www.facebook.com/{$config['api_version']}/dialog/oauth?{$params}";
    }

    public function getEmbeddedSignupLoginUrl(int $gymId): string
    {
        return $this->startEmbeddedSignup($gymId);
    }

    public function handleEmbeddedSignupCallback(string $code, string $state): array
    {
        $session = WabaSetupSession::where('session_id', $state)->first();

        if (! $session) {
            return [
                'success' => false,
                'error' => 'Invalid session',
            ];
        }

        if ($this->context->gymId !== $session->gym_id) {
            return [
                'success' => false,
                'error' => 'Session mismatch',
            ];
        }

        if ($session->isExpired()) {
            $session->markAsExpired();

            return [
                'success' => false,
                'error' => 'Session expired',
            ];
        }

        $tokenData = $this->exchangeCodeForToken($code);

        if (! $tokenData['success']) {
            $session->markAsFailed();

            return $tokenData;
        }

        $accessToken = $tokenData['access_token'];
        $debugData = $this->debugToken($accessToken);

        if (! $debugData['success']) {
            $session->markAsFailed();

            return $debugData;
        }

        $wabaId = $debugData['waba_id'] ?? null;
        $phoneNumberId = $debugData['phone_number_id'] ?? null;

        if (! $wabaId) {
            $session->markAsFailed();

            return [
                'success' => false,
                'error' => 'No WABA ID found in response',
            ];
        }

        $session->update([
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ]);

        $session->markAsCompleted($debugData);

        return [
            'success' => true,
            'session_id' => $session->session_id,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken,
        ];
    }

    protected function getMetaAppConfig(): array
    {
        return [
            'app_id' => config('services.meta.app_id', ''),
            'app_secret' => config('services.meta.app_secret', ''),
            'api_version' => config('services.meta.api_version', 'v25.0'),
            'redirect_uri' => config('services.meta.redirect_uri', ''),
            'config_id' => config('services.meta.config_id', ''),
        ];
    }

    protected function exchangeCodeForToken(string $code): array
    {
        $config = $this->getMetaAppConfig();

        $response = Http::get("https://graph.facebook.com/{$config['api_version']}/oauth/access_token", [
            'client_id' => $config['app_id'],
            'client_secret' => $config['app_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $code,
        ]);

        if ($response->failed()) {
            Log::error('Failed to exchange code for token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to exchange code for token',
            ];
        }

        $data = $response->json();

        return [
            'success' => true,
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'] ?? 'bearer',
            'expires_in' => $data['expires_in'] ?? null,
        ];
    }

    protected function debugToken(string $accessToken): array
    {
        $config = $this->getMetaAppConfig();
        $appAccessToken = "{$config['app_id']}|{$config['app_secret']}";

        $response = Http::get("https://graph.facebook.com/{$config['api_version']}/debug_token", [
            'input_token' => $accessToken,
            'access_token' => $appAccessToken,
        ]);

        if ($response->failed()) {
            Log::error('Failed to debug token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to validate token',
            ];
        }

        $data = $response->json()['data'] ?? [];

        $wabaId = null;
        $phoneNumberId = null;

        $granularScopes = $data['granular_scopes'] ?? [];
        foreach ($granularScopes as $scope) {
            if ($scope['scope'] === 'whatsapp_business_management' || $scope['scope'] === 'whatsapp_business_messaging') {
                $targetIds = $scope['target_ids'] ?? [];
                if (! empty($targetIds)) {
                    $wabaId = $targetIds[0];
                }
            }
        }

        return [
            'success' => true,
            'user_id' => $data['user_id'] ?? null,
            'app_id' => $data['app_id'] ?? null,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'scopes' => $data['scopes'] ?? [],
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    public function isEnabled(): bool
    {
        $metaAppId = config('services.meta.app_id', '');
        $metaAppSecret = config('services.meta.app_secret', '');
        $configId = config('services.meta.config_id', '');

        return ! empty($metaAppId)
            && ! empty($metaAppSecret)
            && ! empty($configId);
    }
}
