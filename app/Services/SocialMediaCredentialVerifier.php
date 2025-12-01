<?php

namespace App\Services;

use App\Enums\SocialPlatform;
use App\Models\TenantSocialConfig;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaCredentialVerifier
{
    /**
     * Verify the credentials for a social media configuration
     */
    public function verify(TenantSocialConfig $config): array
    {
        /**
         * @var \App\Enums\SocialPlatform $platform
         */
        $platform = $config->platform;
        try {
            return match ($platform) {
                SocialPlatform::Facebook => $this->verifyFacebook($config),
                SocialPlatform::Instagram => $this->verifyInstagram($config),
                SocialPlatform::WhatsApp => $this->verifyWhatsApp($config),
                SocialPlatform::YouTube => $this->verifyYouTube($config),
                default => throw new Exception("Platform {$platform->value} verification not implemented"),
            };
        } catch (Exception $e) {
            Log::error('Social media credential verification failed', [
                'platform' => $platform->value,
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Verify Facebook credentials
     */
    protected function verifyFacebook(TenantSocialConfig $config): array
    {
        if (! $config->access_token) {
            throw new Exception('Access token is required for Facebook verification');
        }

        // Verify the access token by fetching the user's pages
        $response = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $config->access_token,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Failed to verify Facebook credentials');
            throw new Exception($error);
        }

        $data = $response->json();
        $pages = $data['data'] ?? [];

        // If page_id is specified, verify it exists
        if ($config->page_id) {
            $pageExists = collect($pages)->contains('id', $config->page_id);
            if (! $pageExists) {
                throw new Exception("Page ID {$config->page_id} not found in your Facebook account");
            }
        }

        return [
            'success' => true,
            'message' => 'Facebook credentials verified successfully',
            'data' => [
                'pages' => $pages,
                'page_count' => count($pages),
            ],
        ];
    }

    /**
     * Verify Instagram credentials
     */
    protected function verifyInstagram(TenantSocialConfig $config): array
    {
        if (! $config->access_token) {
            throw new Exception('Access token is required for Instagram verification');
        }

        // Instagram Business API - verify by fetching account info
        if ($config->page_id) {
            $response = Http::get("https://graph.facebook.com/v18.0/{$config->page_id}", [
                'fields' => 'instagram_business_account,name,username',
                'access_token' => $config->access_token,
            ]);

            if ($response->failed()) {
                $error = $response->json('error.message', 'Failed to verify Instagram credentials');
                throw new Exception($error);
            }

            $data = $response->json();

            return [
                'success' => true,
                'message' => 'Instagram credentials verified successfully',
                'data' => $data,
            ];
        }

        // Fallback: verify token is valid
        $response = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $config->access_token,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Failed to verify Instagram credentials');
            throw new Exception($error);
        }

        return [
            'success' => true,
            'message' => 'Instagram token verified successfully',
            'data' => $response->json(),
        ];
    }

    /**
     * Verify WhatsApp credentials
     */
    protected function verifyWhatsApp(TenantSocialConfig $config): array
    {
        if (! $config->access_token || ! $config->phone_number_id) {
            throw new Exception('Access token and phone number ID are required for WhatsApp verification');
        }

        // Verify WhatsApp Business API credentials
        $response = Http::withToken($config->access_token)
            ->get("https://graph.facebook.com/v18.0/{$config->phone_number_id}");

        if ($response->failed()) {
            $error = $response->json('error.message', 'Failed to verify WhatsApp credentials');
            throw new Exception($error);
        }

        $data = $response->json();

        return [
            'success' => true,
            'message' => 'WhatsApp credentials verified successfully',
            'data' => $data,
        ];
    }

    /**
     * Verify YouTube credentials
     */
    protected function verifyYouTube(TenantSocialConfig $config): array
    {
        // YouTube verification would require OAuth2 implementation
        // This is a placeholder for future implementation
        throw new Exception('YouTube credential verification is not yet implemented');
    }
}
