<?php

namespace App\Services;

use App\Enums\WabaModeEnum;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Models\WabaSetupSession;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * EmbeddedSignupService - Legacy service for WhatsApp embedded signup.
 *
 * @deprecated Use MetaWhatsAppProvider::startEmbeddedSignup() and ::handleEmbeddedSignupCallback() instead.
 *             This service is kept for backward compatibility with existing route handlers.
 *             Methods delegate to provider implementation where applicable.
 * @see \App\Providers\MetaWhatsAppProvider::startEmbeddedSignup()
 * @see \App\Providers\MetaWhatsAppProvider::handleEmbeddedSignupCallback()
 */
class EmbeddedSignupService
{
    private string $metaAppId;

    private string $metaAppSecret;

    private string $metaApiVersion;

    private string $metaRedirectUri;

    private string $metaConfigId;

    private string $masterAccountSid;

    private string $masterAuthToken;

    private bool $embeddedSignupEnabled;

    public function __construct()
    {
        $this->metaAppId = config('services.twilio.meta.app_id', '');
        $this->metaAppSecret = config('services.twilio.meta.app_secret', '');
        $this->metaApiVersion = config('services.twilio.meta.api_version', 'v19.0');
        $this->metaRedirectUri = config('services.twilio.meta.redirect_uri', '');
        $this->metaConfigId = config('services.twilio.meta.config_id', '');

        $this->masterAccountSid = config('services.twilio.master_sid', '');
        $this->masterAuthToken = config('services.twilio.master_token', '');

        $this->embeddedSignupEnabled = config('services.twilio.embedded_signup_enabled', false);
    }

    public function isEnabled(): bool
    {
        return $this->embeddedSignupEnabled
            && ! empty($this->metaAppId)
            && ! empty($this->metaAppSecret);
    }

    /**
     * @deprecated Use MetaWhatsAppProvider::startEmbeddedSignup() instead.
     */
    public function getLoginUrl(int $gymId): string
    {
        $session = WabaSetupSession::createForGym($gymId);

        $params = http_build_query([
            'client_id' => $this->metaAppId,
            'redirect_uri' => $this->metaRedirectUri,
            'state' => $session->session_id,
            'config_id' => $this->metaConfigId,
            'response_type' => 'code',
            'scope' => 'whatsapp_business_management,whatsapp_business_messaging,business_management',
        ]);

        return "https://www.facebook.com/{$this->metaApiVersion}/dialog/oauth?{$params}";
    }

    /**
     * @deprecated Use MetaWhatsAppProvider::handleEmbeddedSignupCallback() instead.
     */
    public function handleCallback(string $code, string $state): array
    {
        $session = WabaSetupSession::where('session_id', $state)->first();

        if (! $session) {
            return [
                'success' => false,
                'error' => 'Invalid session',
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

        Log::info('Embedded Signup token exchange', [
            'token_data' => $tokenData,
        ]);

        if (! $tokenData['success']) {
            $session->markAsFailed();

            return $tokenData;
        }

        $accessToken = $tokenData['access_token'];

        $debugData = $this->debugToken($accessToken);

        Log::info('Embedded Signup debug token', [
            'debug_data' => $debugData,
        ]);

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

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->metaApiVersion}/oauth/access_token", [
            'client_id' => $this->metaAppId,
            'client_secret' => $this->metaAppSecret,
            'redirect_uri' => $this->metaRedirectUri,
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

    public function debugToken(string $accessToken): array
    {
        $appAccessToken = "{$this->metaAppId}|{$this->metaAppSecret}";

        $response = Http::get("https://graph.facebook.com/{$this->metaApiVersion}/debug_token", [
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

    public function getWabaDetails(string $wabaId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/{$this->metaApiVersion}/{$wabaId}", [
                'fields' => 'id,name,account_review_status,business_verification_status,ownership_tier,currency,timezone_id',
            ]);

        if ($response->failed()) {
            Log::error('Failed to get WABA details', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get WABA details',
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    public function connectWabaToSubaccount(
        int $gymId,
        string $wabaId,
        string $phoneNumber,
        string $metaAccessToken
    ): array {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            return [
                'success' => false,
                'error' => 'WhatsApp account not found for gym',
            ];
        }

        Log::info('Connecting WABA to subaccount', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'phone_number' => $phoneNumber,
            'account' => $account->toArray(),
        ]);

        try {
            $sid = $account->twilio_account_sid;
            $token = $account->twilio_auth_token;

            // If phone number is provided, create the sender
            // Note: During embedded signup callback, phone number may be empty
            // as the user selects their phone number in step 3 of the wizard
            $senderSid = null;
            if (! empty($phoneNumber)) {
                // Register WhatsApp sender via Twilio Messaging v2 Channels API
                // // Adding asForm() because Twilio API requires application/x-www-form-urlencoded
                // $response = Http::withBasicAuth($sid, $token)
                //     ->asForm()
                //     ->post('https://messaging.twilio.com/v2/Channels/Senders', [
                //         'Sender' => 'whatsapp:' . $phoneNumber,
                //         'Configuration.waba_id' => $wabaId,
                //     ]);

                // if ($response->failed()) {
                //     Log::error('Failed to create WhatsApp sender', [
                //         'gym_id' => $gymId,
                //         'error' => $response->json('message') ?? $response->body(),
                //     ]);

                //     return [
                //         'success' => false,
                //         'error' => $response->json('message') ?? 'Failed to register WhatsApp sender',
                //     ];
                // }

                // $senderSid = $response->json('sid');

                // // Attach sender to messaging service if it exists
                // if ($account->messaging_service_sid && $senderSid) {
                //     $response = Http::withBasicAuth($sid, $token)
                //         ->asForm()
                //         ->post("https://messaging.twilio.com/v1/Services/{$account->messaging_service_sid}/ChannelSenders", [
                //             'Sid' => $senderSid,
                //         ]);
                //     // Not a hard failure if this fails — log and continue
                //     if ($response->failed()) {
                //         Log::error('Failed to attach sender to messaging service', [
                //             'gym_id' => $gymId,
                //             'error' => $response->json('message') ?? $response->body(),
                //         ]);
                //     }
                // }

                $subClient = new Client(
                    $account->twilio_account_sid,
                    $account->twilio_auth_token
                );

                $senderSid = app(TwilioAccountManager::class)->registerWhatsAppSender($subClient, $account, $phoneNumber, $wabaId);
            }

            $account->update([
                'waba_id' => $wabaId,
                'waba_mode' => WabaModeEnum::INDIVIDUAL,
                'meta_access_token' => $metaAccessToken,
                'embedded_signup_completed' => true,
                'status' => WhatsAppAccountStatusEnum::PENDING,
            ]);

            return [
                'success' => true,
                'sender_sid' => $senderSid,
                'waba_id' => $wabaId,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect WABA to subaccount', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function connectManualWaba(
        int $gymId,
        string $wabaId,
        string $phoneNumber,
        ?string $metaAccessToken = null
    ): array {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            return [
                'success' => false,
                'error' => 'WhatsApp account not found for gym',
            ];
        }

        try {
            $subClient = new Client(
                $account->twilio_account_sid,
                $account->twilio_auth_token // Fixed: access the attribute correctly
            );

            $request = \Twilio\Rest\Messaging\V2\ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                'sender_id' => 'whatsapp:' . $phoneNumber,
                'configuration' => \Twilio\Rest\Messaging\V2\ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                    'waba_id' => $wabaId,
                ]),
            ]);

            $sender = $subClient->messaging->v2->channelsSenders->create($request);

            $updateData = [
                'waba_id' => $wabaId,
                'waba_mode' => WabaModeEnum::MANUAL->value,
                'embedded_signup_completed' => false,
                'status' => WhatsAppAccountStatusEnum::PENDING->value,
            ];

            if ($metaAccessToken) {
                $updateData['meta_access_token'] = $metaAccessToken;
            }

            $account->update($updateData);

            return [
                'success' => true,
                'sender_sid' => $sender->sid,
                'waba_id' => $wabaId,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect manual WABA', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function validateSession(string $sessionId, int $gymId): bool
    {
        $session = WabaSetupSession::where('session_id', $sessionId)
            ->where('gym_id', $gymId)
            ->first();

        if (! $session) {
            return false;
        }

        return $session->isPending() && ! $session->isExpired();
    }
}
