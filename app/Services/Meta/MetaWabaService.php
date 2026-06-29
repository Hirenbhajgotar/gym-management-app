<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Interfaces\WabaSetupInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Providers\WhatsAppProviderFactory;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Meta WABA Service.
 *
 * Implements WabaSetupInterface for WhatsApp Business Account setup
 * via Meta Graph API.
 */
class MetaWabaService implements WabaSetupInterface
{
    public function __construct(
        protected MetaContext $context,
        protected MetaApiClient $api
    ) {}

    public function createWaba(int $gymId, array $businessProfile): array
    {
        return [
            'success' => false,
            'error' => 'WABA creation must be done through Meta Business Manager or embedded signup',
        ];
    }

    public function connectIndividualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array
    {
        $account = $this->context->getAccount();

        if ($account) {
            $account->update([
                'waba_id' => $wabaId,
                'meta_access_token' => $metaAccessToken,
            ]);
        }

        return [
            'success' => true,
            'waba_id' => $wabaId,
            'message' => 'Individual WABA connected successfully',
        ];
    }

    public function connectManualWaba(
        int $gymId,
        string $wabaId,
        string $phoneNumber,
        ?string $metaAccessToken = null,
        ?string $phoneNumberId = null,
        ?array $businessData = null
    ): array {
        if (! $metaAccessToken || ! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Meta Access Token and Phone Number ID are required to connect a WhatsApp Business Account. This ensures your credentials are validated with Meta before connecting.',
            ];
        }

        try {
            return Cache::lock("meta:manual-waba:{$gymId}", 60)->block(15, function () use ($gymId, $wabaId, $phoneNumber, $metaAccessToken, $phoneNumberId, $businessData) {
                $verificationResult = $this->verifyWabaConnection($wabaId, $phoneNumberId, $metaAccessToken);

                if (! $verificationResult['success'] || ! ($verificationResult['verified'] ?? false)) {
                    return [
                        'success' => false,
                        'error' => $verificationResult['error'] ?? 'The provided Phone Number ID does not exist or is not valid in Meta system',
                    ];
                }

                $businessName = $verificationResult['business_name'] ?? ($businessData['business_name'] ?? null);
                $displayPhoneNumber = $verificationResult['display_phone_number'] ?? $phoneNumber;
                $normalizedPhoneNumber = $this->normalizePhoneNumber($displayPhoneNumber);

                return DB::transaction(function () use ($gymId, $businessData, $wabaId, $phoneNumberId, $metaAccessToken, $verificationResult, $businessName, $normalizedPhoneNumber) {
                    $account = WhatsAppAccount::query()->updateOrCreate([
                        'gym_id' => $gymId,
                    ], [
                        'business_name' => htmlspecialchars(strip_tags($businessName)),
                        'business_email' => filter_var($businessData['business_email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
                        'business_description' => htmlspecialchars(strip_tags($businessData['business_description'] ?? '')),
                        'waba_id' => $wabaId,
                        'waba_name' => $businessName,
                        'meta_access_token' => $metaAccessToken,
                        'embedded_signup_completed' => true,
                        'status' => $this->mapWabaStatus($verificationResult['status'] ?? null),
                        'provider_type' => WhatsAppProviderTypeEnum::WABA,
                        'verification_status' => $this->mapBusinessVerificationStatus($verificationResult['business_verification_status'] ?? null),
                        'verified_at' => now(),
                    ]);

                    WhatsAppPhoneNumber::query()->updateOrCreate([
                        'whatsapp_account_id' => $account->id,
                    ], [
                        'waba_phone_number_id' => $verificationResult['phone_number_id'] ?? $phoneNumberId,
                        'phone_number' => $normalizedPhoneNumber,
                        'country_code' => $this->extractCountryCode($normalizedPhoneNumber),
                        'friendly_name' => $normalizedPhoneNumber,
                        'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                        'type' => $this->determinePhoneType($verificationResult),
                    ]);

                    WhatsAppProviderFactory::invalidateProviderCache($gymId);

                    return [
                        'success' => true,
                        'waba_id' => $wabaId,
                        'phone_number_id' => $verificationResult['phone_number_id'] ?? $phoneNumberId,
                        'display_phone_number' => $normalizedPhoneNumber,
                        'business_name' => $businessName,
                        'status' => $verificationResult['status'],
                        'verification_status' => $verificationResult['business_verification_status'],
                        'message' => 'Manual WABA connected successfully',
                    ];
                });
            });
        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'error' => 'WABA connection is currently in progress. Please wait a moment.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect manual WABA', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'An unexpected error occurred while connecting WABA.',
            ];
        }
    }

    public function verifyWabaConnection(string $wabaId, string $phoneNumberId, ?string $accessToken = null): array
    {
        $token = $accessToken ?? $this->context->getAccessToken();

        if (! $token) {
            return $this->verificationError('Meta access token is required for verification');
        }

        try {
            $phoneResponse = $this->api->get("/{$phoneNumberId}", [
                'fields' => 'id,display_phone_number,code_verification_status,certificate,verified_name,quality_score,message_template_namespace',
            ], $token);

            if (! $phoneResponse['success']) {
                return $this->verificationError('Meta API error: ' . ($phoneResponse['error'] ?? 'Failed to fetch phone number details'));
            }

            $wabaResponse = $this->api->get("/{$wabaId}", [
                'fields' => 'id,name,business,account_review_status,business_verification_status,currency,timezone',
            ], $token);

            $phoneData = $phoneResponse['data'];
            $wabaData = $wabaResponse['success'] ? $wabaResponse['data'] : [];

            $businessName = $wabaData['business']['name'] ?? $wabaData['name'] ?? $phoneData['verified_name'] ?? null;

            return [
                'success' => true,
                'verified' => true,
                'phone_number_id' => $phoneData['id'] ?? $phoneNumberId,
                'display_phone_number' => $phoneData['display_phone_number'] ?? null,
                'verified_name' => $phoneData['verified_name'] ?? null,
                'business_verification_status' => $this->mapBusinessVerificationStatus($wabaData['business_verification_status'] ?? null)->value,
                'code_verification_status' => $phoneData['code_verification_status'] ?? null,
                'certificate' => $phoneData['certificate'] ?? null,
                'quality_score' => $phoneData['quality_score'] ?? null,
                'waba_id' => $wabaData['id'] ?? $wabaId,
                'business_name' => $businessName,
                'status' => $this->mapWabaStatus($wabaData['account_review_status'] ?? null)->value,
                'currency' => $wabaData['currency'] ?? null,
                'timezone' => $wabaData['timezone'] ?? null,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to verify WABA connection', [
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return $this->verificationError('Failed to verify WABA connection: ' . $e->getMessage());
        }
    }

    protected function verificationError(string $error): array
    {
        return [
            'success' => false,
            'error' => $error,
            'verified' => false,
            'phone_number_id' => null,
            'display_phone_number' => null,
            'verified_name' => null,
            'code_verification_status' => null,
            'business_verification_status' => null,
            'certificate' => null,
            'quality_score' => null,
            'waba_id' => null,
            'business_name' => null,
            'status' => null,
            'currency' => null,
            'timezone' => null,
        ];
    }

    protected function mapBusinessVerificationStatus(?string $status): WhatsAppAccountVerificationStatusEnum
    {
        return WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus($status);
    }

    protected function mapWabaStatus(?string $status): WhatsAppAccountStatusEnum
    {
        return WhatsAppAccountStatusEnum::fromMetaReviewStatus($status);
    }

    protected function normalizePhoneNumber(string $phoneNumber): string
    {
        return str_starts_with($phoneNumber, '+') ? $phoneNumber : '+' . ltrim($phoneNumber, '+');
    }

    protected function extractCountryCode(string $phoneNumber): ?string
    {
        if (preg_match('/^\+(\d{1,3})/', $phoneNumber, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function determinePhoneType(?array $verificationResult): WhatsAppPhoneNumberTypesEnum
    {
        return WhatsAppPhoneNumberTypesEnum::BUSINESS;
    }

    /**
     * Complete embedded signup - save newly created WABA to database.
     *
     * This method is called after MetaEmbeddedSignupService::handleEmbeddedSignupCallback()
     * successfully processes the OAuth callback. It saves the newly created WABA details
     * to the WhatsAppAccount.
     *
     * Note: For embedded signup, phone_number_id is often null because the user selects
     * their phone number in the next step of the wizard. We only save the phone number
     * if a phone_number_id is explicitly provided.
     *
     * @param  int  $gymId  The gym ID
     * @param  string  $wabaId  The newly created WABA ID from Meta
     * @param  string  $accessToken  The Meta access token from OAuth
     * @param  string|null  $phoneNumberId  The phone number ID (optional - may be null)
     * @return array{
     *     success: bool,
     *     waba_id?: string,
     *     message?: string,
     *     error?: string
     * }
     */
    public function completeEmbeddedSignup(int $gymId, string $wabaId, string $accessToken, ?string $phoneNumberId = null): array
    {
        try {
            return Cache::lock("meta:embedded-signup:{$gymId}", 60)->block(15, function () use ($gymId, $wabaId, $accessToken, $phoneNumberId) {
                // Double-check if already processed (Meta callback deduplication)
                $existingAccount = WhatsAppAccount::query()->where('gym_id', $gymId)
                    ->where('waba_id', $wabaId)
                    ->where('embedded_signup_completed', true)
                    ->first();

                if ($existingAccount) {
                    return [
                        'success' => true,
                        'waba_id' => $wabaId,
                        'message' => 'Embedded signup already completed',
                    ];
                }

                // Get or create WhatsAppAccount for the gym
                $account = WhatsAppAccount::query()->firstOrCreate(['gym_id' => $gymId], ['provider_type' => WhatsAppProviderTypeEnum::WABA]);

                // Update with WABA details from embedded signup
                // provider_type = WABA because user created it via embedded signup (owns it)
                // status = PENDING because it needs verification with Meta
                $account->update([
                    'waba_id' => $wabaId,
                    'meta_access_token' => $accessToken,
                    'embedded_signup_completed' => true,
                    'status' => WhatsAppAccountStatusEnum::PENDING,
                ]);

                // Note: phone_number_id is often null in embedded signup
                // (user selects phone in next step of the wizard)
                // Only create phone record if we have actual phone number ID
                if ($phoneNumberId) {
                    // Fetch phone details from Meta API to get the display phone number
                    $phoneDetails = $this->fetchPhoneDetails($phoneNumberId, $accessToken);

                    if ($phoneDetails['success'] && $phoneDetails['display_phone_number']) {
                        $normalizedPhoneNumber = $this->normalizePhoneNumber($phoneDetails['display_phone_number']);

                        WhatsAppPhoneNumber::query()->updateOrCreate(['whatsapp_account_id' => $account->id], [
                            'waba_phone_number_id' => $phoneNumberId,
                            'phone_number' => $normalizedPhoneNumber,
                            'country_code' => $this->extractCountryCode($normalizedPhoneNumber),
                            'friendly_name' => $normalizedPhoneNumber,
                            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
                        ]);
                    }
                }

                // Invalidate provider cache so factory recreates provider with new credentials
                WhatsAppProviderFactory::invalidateProviderCache($gymId);

                return [
                    'success' => true,
                    'waba_id' => $wabaId,
                    'message' => 'Embedded signup completed successfully',
                ];
            });

        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'error' => 'Processing embedded signup. Please wait.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to complete embedded signup: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch phone number details from Meta API.
     *
     * @param  string  $phoneNumberId  The phone number ID
     * @param  string  $accessToken  The Meta access token
     * @return array{success: bool, display_phone_number?: string, error?: string}
     */
    protected function fetchPhoneDetails(string $phoneNumberId, string $accessToken): array
    {
        try {
            $response = $this->api->get("/{$phoneNumberId}", [
                'fields' => 'id,display_phone_number',
            ], $accessToken);

            if (! $response['success']) {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to fetch phone details',
                ];
            }

            return [
                'success' => true,
                'display_phone_number' => $response['data']['display_phone_number'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch phone details from Meta', [
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get WABA account details including review and verification status.
     *
     * Used by SyncWabaStatus job to fetch current account_review_status and
     * business_verification_status from Meta API.
     *
     * @param  string  $wabaId  The WhatsApp Business Account ID
     * @param  string  $accessToken  The Meta access token
     * @return array{
     *     success: bool,
     *     data?: array{
     *         id: string,
     *         name: string,
     *         account_review_status: string,
     *         business_verification_status: string,
     *         currency?: string,
     *         timezone?: string
     *     },
     *     error?: string
     * }
     */
    public function getWabaDetails(string $wabaId, string $accessToken): array
    {
        try {
            $response = $this->api->get("/{$wabaId}", [
                'fields' => 'id,name,account_review_status,business_verification_status',
            ], $accessToken);

            if (! $response['success']) {
                $metaError = $response['error'] ?? 'Unknown error';

                Log::error('Failed to get WABA details from Meta API', [
                    'waba_id' => $wabaId,
                    'meta_error' => $metaError,
                ]);

                return [
                    'success' => false,
                    'error' => "Failed to retrieve WABA details from Meta API: {$metaError}",
                ];
            }

            return [
                'success' => true,
                'data' => $response['data'],
            ];
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();

            Log::error('Exception while getting WABA details', [
                'waba_id' => $wabaId,
                'error' => $exceptionMessage,
            ]);

            return [
                'success' => false,
                'error' => "Exception while retrieving WABA details from Meta: {$exceptionMessage}",
            ];
        }
    }
}
