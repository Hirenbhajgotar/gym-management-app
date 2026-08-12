<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Helpers\PhoneNumber;
use App\Interfaces\WabaSetupInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Providers\WhatsAppProviderFactory;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
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

                if (! $verificationResult['success'] || $verificationResult['verified'] !== true) {
                    return [
                        'success' => false,
                        'error' => $verificationResult['error'] ?? 'The provided Phone Number ID does not exist or is not valid in Meta system',
                    ];
                }

                $businessName = $verificationResult['business_name'] ?? ($businessData['business_name'] ?? null);
                $displayPhoneNumber = $verificationResult['display_phone_number'] ?? $phoneNumber;
                $normalizedPhoneNumber = $this->normalizePhoneNumber($displayPhoneNumber);

                return DB::transaction(function () use ($gymId, $wabaId, $phoneNumberId, $metaAccessToken, $verificationResult, $businessName, $normalizedPhoneNumber, $businessData) {
                    $this->persistWabaConnection($gymId, $wabaId, $phoneNumberId, $metaAccessToken, $verificationResult, $businessName, $normalizedPhoneNumber, $businessData);

                    WhatsAppProviderFactory::invalidateProviderCache($gymId);

                    return [
                        'success' => true,
                        'waba_id' => $wabaId,
                        'phone_number_id' => $verificationResult['phone_number_id'] ?? $phoneNumberId,
                        'display_phone_number' => $normalizedPhoneNumber,
                        'business_name' => $businessName,
                        'status' => $verificationResult['status'] ?? null,
                        'verification_status' => $verificationResult['business_verification_status'] ?? null,
                        'message' => 'Manual WABA connected successfully',
                    ];
                }, 5);
            });
        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'error' => 'WABA connection is currently in progress. Please wait a moment.',
            ];
        } catch (Exception $e) {
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

    /**
     * Persist verified WABA connection to database.
     *
     * @param  array<string, mixed>  $verificationResult
     * @param  array{business_name?: string, business_email?: string, business_description?: string}  $businessData
     */
    protected function persistWabaConnection(
        int $gymId,
        string $wabaId,
        string $phoneNumberId,
        string $metaAccessToken,
        array $verificationResult,
        ?string $businessName,
        string $normalizedPhoneNumber,
        ?array $businessData = null
    ): void {
        $account = WhatsAppAccount::query()->updateOrCreate([
            'gym_id' => $gymId,
        ], [
            'business_name' => htmlspecialchars(strip_tags($businessName ?? '')),
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
            'waba_phone_number_id' => $verificationResult['phone_number_id'] ?? $phoneNumberId,
        ], [
            'phone_number' => $normalizedPhoneNumber,
            'country_code' => $this->extractCountryCode($normalizedPhoneNumber),
            'friendly_name' => $normalizedPhoneNumber,
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => $this->determinePhoneType($verificationResult),
        ]);
    }

    /**
     * Verify WABA and phone number ID via Meta API.
     *
     * @param  string  $wabaId  The WhatsApp Business Account ID
     * @param  string  $phoneNumberId  The Phone Number ID
     * @param  string|null  $accessToken  Optional access token (uses account token if not provided)
     * @return array{
     *     success: bool,
     *     verified: bool,
     *     phone_number_id?: string,
     *     display_phone_number?: string,
     *     verified_name?: string,
     *     business_verification_status?: string,
     *     phone_number_status?: string,
     *     quality_score?: string,
     *     waba_id?: string,
     *     business_name?: string,
     *     status?: string,
     *     currency?: string,
     *     timezone?: string,
     *     error?: string
     * }
     */
    public function verifyWabaConnection(string $wabaId, string $phoneNumberId, ?string $accessToken = null): array
    {
        $token = $accessToken ?? $this->context->getAccessToken();

        if (! $token) {
            return $this->verificationError('Meta access token is required for verification');
        }

        try {
            $phoneResponse = $this->api->get("/{$phoneNumberId}", [
                'fields' => 'id,display_phone_number,verified_name,quality_score,status',
            ], $token);

            if (! $phoneResponse['success']) {
                return $this->verificationError('Meta API error: ' . ($phoneResponse['error'] ?? 'Failed to fetch phone number details'));
            }

            $wabaResponse = $this->api->get("/{$wabaId}", [
                'fields' => 'id,name,business,account_review_status,business_verification_status,currency,timezone',
            ], $token);

            if (! $wabaResponse['success']) {
                return $this->verificationError('Meta API error: ' . ($wabaResponse['error'] ?? 'Failed to WABA details'));
            }

            /** @var array<string, mixed> $phoneData */
            $phoneData = $phoneResponse['data'] ?? [];
            /** @var array<string, mixed> $wabaData */
            $wabaData = $wabaResponse['data'] ?? [];

            return $this->buildVerificationResult(
                $this->extractPhoneData($phoneData),
                $this->extractWabaData($wabaData, $phoneData),
                $wabaId,
                $phoneNumberId
            );
        } catch (Exception $e) {
            Log::error('Failed to verify WABA connection', [
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return $this->verificationError('Failed to verify WABA connection: ' . $e->getMessage());
        }
    }

    /**
     * Extract phone number data from Meta API response.
     *
     * @param  array<string, mixed>  $phoneData
     * @return array{
     *     id: string|null,
     *     display_phone_number: string|null,
     *     verified_name: string|null,
     *     quality_score: string|null,
     *     status: string|null
     * }
     */
    protected function extractPhoneData(array $phoneData): array
    {
        return [
            'id' => $this->toStringOrNull(Arr::get($phoneData, 'id')),
            'display_phone_number' => $this->toStringOrNull(Arr::get($phoneData, 'display_phone_number')),
            'verified_name' => $this->toStringOrNull(Arr::get($phoneData, 'verified_name')),
            'quality_score' => $this->toStringOrNull(Arr::get($phoneData, 'quality_score')),
            'status' => $this->toStringOrNull(Arr::get($phoneData, 'status')),
        ];
    }

    /**
     * Extract WABA data from Meta API response.
     *
     * @param  array<string, mixed>  $wabaData
     * @param  array<string, mixed>  $phoneData
     * @return array{
     *     id: string|null,
     *     business_name: string|null,
     *     account_review_status: string|null,
     *     business_verification_status: string|null,
     *     currency: string|null,
     *     timezone: string|null
     * }
     */
    protected function extractWabaData(array $wabaData, array $phoneData): array
    {
        return [
            'id' => $this->toStringOrNull(Arr::get($wabaData, 'id')),
            'business_name' => $this->toStringOrNull(Arr::get($wabaData, 'business.name') ?? Arr::get($wabaData, 'name') ?? Arr::get($phoneData, 'verified_name')),
            'account_review_status' => $this->toStringOrNull(Arr::get($wabaData, 'account_review_status')),
            'business_verification_status' => $this->toStringOrNull(Arr::get($wabaData, 'business_verification_status')),
            'currency' => $this->toStringOrNull(Arr::get($wabaData, 'currency')),
            'timezone' => $this->toStringOrNull(Arr::get($wabaData, 'timezone')),
        ];
    }

    /**
     * Build the verification result array from extracted phone and WABA data.
     *
     * @param  array<string, mixed>  $phone
     * @param  array<string, mixed>  $waba
     * @return array{
     *     success: bool,
     *     verified: bool,
     *     phone_number_id: string,
     *     waba_id: string,
     *     display_phone_number?: string,
     *     verified_name?: string,
     *     business_verification_status?: string,
     *     phone_number_status?: string,
     *     quality_score?: string,
     *     business_name?: string,
     *     status?: string,
     *     currency?: string,
     *     timezone?: string
     * }
     */
    protected function buildVerificationResult(array $phone, array $waba, string $fallbackWabaId, string $fallbackPhoneId): array
    {
        $result = [
            'success' => true,
            'verified' => true,
            'phone_number_id' => is_string(Arr::get($phone, 'id')) ? Arr::get($phone, 'id') : $fallbackPhoneId,
            'waba_id' => is_string(Arr::get($waba, 'id')) ? Arr::get($waba, 'id') : $fallbackWabaId,
        ];

        if (is_string(Arr::get($phone, 'display_phone_number'))) {
            $result['display_phone_number'] = Arr::get($phone, 'display_phone_number');
        }
        if (is_string(Arr::get($phone, 'verified_name'))) {
            $result['verified_name'] = Arr::get($phone, 'verified_name');
        }
        if (is_string(Arr::get($waba, 'business_verification_status'))) {
            $result['business_verification_status'] = $this->mapBusinessVerificationStatus(Arr::get($waba, 'business_verification_status'))->value;
        }
        if (is_string(Arr::get($phone, 'status'))) {
            $result['phone_number_status'] = $this->mapPhoneNumberStatus(Arr::get($phone, 'status'))->value;
        }
        if (is_string(Arr::get($phone, 'quality_score'))) {
            $result['quality_score'] = Arr::get($phone, 'quality_score');
        }
        if (is_string(Arr::get($waba, 'business_name'))) {
            $result['business_name'] = Arr::get($waba, 'business_name');
        }
        if (is_string(Arr::get($waba, 'account_review_status'))) {
            $result['status'] = $this->mapWabaStatus(Arr::get($waba, 'account_review_status'))->value;
        }
        if (is_string(Arr::get($waba, 'currency'))) {
            $result['currency'] = Arr::get($waba, 'currency');
        }
        if (is_string(Arr::get($waba, 'timezone'))) {
            $result['timezone'] = Arr::get($waba, 'timezone');
        }

        return $result;
    }

    /**
     * @return array{success: false, verified: false, error: string}
     */
    protected function verificationError(string $error): array
    {
        return [
            'success' => false,
            'error' => $error,
            'verified' => false,
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

    protected function mapPhoneNumberStatus(?string $status): WhatsAppPhoneNumberStatusEnum
    {
        return WhatsAppPhoneNumberStatusEnum::fromMetaPhoneNumberStatus($status);
    }

    /**
     * Convert a value to string or null if it's not a string.
     */
    protected function toStringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    protected function normalizePhoneNumber(string $phoneNumber): string
    {
        return PhoneNumber::formatPhoneNumberWithCountryCode($phoneNumber);
    }

    protected function extractCountryCode(string $phoneNumber): ?string
    {
        return PhoneNumber::getIsoCountryCode($phoneNumber) ?? config()->string('app.default_country_iso', 'IN');
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
                $phoneDetails = null;
                if ($phoneNumberId) {
                    // Fetch phone details from Meta API to get the display phone number
                    $phoneDetails = $this->fetchPhoneDetails($phoneNumberId, $accessToken);
                }

                return DB::transaction(function () use ($gymId, $wabaId, $accessToken, $phoneNumberId, $phoneDetails) {
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
                    if ($phoneNumberId && filled($phoneDetails)) {
                        if ($phoneDetails['success'] && $phoneDetails['display_phone_number']) {
                            $normalizedPhoneNumber = $this->normalizePhoneNumber($phoneDetails['display_phone_number']);

                            WhatsAppPhoneNumber::query()->updateOrCreate(
                                [
                                    'whatsapp_account_id' => $account->id,
                                    'waba_phone_number_id' => $phoneNumberId,
                                ], [
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
                }, 5);
            });
        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'error' => 'Processing embedded signup. Please wait.',
            ];
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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

            /** @var array{id: string, name: string, account_review_status: string, business_verification_status: string, currency?: string, timezone?: string} $responseData */
            $responseData = $response['data'];

            return [
                'success' => true,
                'data' => $responseData,
            ];
        } catch (Exception $e) {
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
