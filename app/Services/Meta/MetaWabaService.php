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

        $verificationPhoneId = $verificationResult['phone_number_id'] ?? null;
        $phoneId = is_string($verificationPhoneId) ? $verificationPhoneId : $phoneNumberId;

        $this->persistPhoneNumber(
            $account->id,
            $phoneId,
            $normalizedPhoneNumber,
            $this->determinePhoneType($verificationResult)
        );
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
     * Persist (upsert) a WhatsApp phone number for an account.
     *
     * Normalizes the display number, derives the ISO country code, and upserts the
     * record keyed by (account, waba_phone_number_id). Centralizing this avoids the
     * duplicated updateOrCreate blocks previously scattered across the embedded
     * signup, WABA connection, and phone-number sync flows.
     */
    protected function persistPhoneNumber(
        int $accountId,
        string $phoneNumberId,
        string $displayPhoneNumber,
        WhatsAppPhoneNumberTypesEnum $type = WhatsAppPhoneNumberTypesEnum::BUSINESS
    ): void {
        $normalized = $this->normalizePhoneNumber($displayPhoneNumber);

        WhatsAppPhoneNumber::query()->updateOrCreate(
            [
                'whatsapp_account_id' => $accountId,
            ],
            [
                'waba_phone_number_id' => $phoneNumberId,
                'phone_number' => $normalized,
                'country_code' => $this->extractCountryCode($normalized),
                'friendly_name' => $normalized,
                'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                'type' => $type,
            ]
        );
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

                    // Get or create the WhatsAppAccount for the gym and persist WABA details
                    // atomically. provider_type = WABA because the user created it via embedded
                    // signup (owns it); status = PENDING because it still needs verification
                    // with Meta. updateOrCreate avoids the brief window where firstOrCreate()
                    // leaves a row without waba_id before the subsequent update() runs.
                    $account = WhatsAppAccount::query()->updateOrCreate(
                        ['gym_id' => $gymId],
                        [
                            'provider_type' => WhatsAppProviderTypeEnum::WABA,
                            'waba_id' => $wabaId,
                            'meta_access_token' => $accessToken,
                            'embedded_signup_completed' => true,
                            'status' => WhatsAppAccountStatusEnum::PENDING,
                        ]
                    );

                    // Note: phone_number_id is often null in embedded signup
                    // (user selects phone in next step of the wizard).
                    // Only persist a phone record when we actually have a phone number ID
                    // and successfully fetched its display number from Meta.
                    if ($phoneNumberId && filled($phoneDetails)) {
                        if ($phoneDetails['success'] && $phoneDetails['display_phone_number']) {
                            // Persist (upsert) the phone number, then attempt Cloud API registration.
                            $this->persistPhoneNumber($account->id, $phoneNumberId, $phoneDetails['display_phone_number']);

                            $registrationPin = config()->string('services.whatsapp.default_registration_pin', '123456');
                            $registrationResult = $this->registerPhoneNumber($phoneNumberId, $registrationPin, $accessToken);

                            if (! $registrationResult['success']) {
                                Log::warning('WhatsApp phone number registration failed during embedded signup', [
                                    'gym_id' => $gymId,
                                    'phone_number_id' => $phoneNumberId,
                                    'error' => $registrationResult['error'] ?? 'Unknown error',
                                ]);
                            }
                        }
                    } else {
                        // Query Meta API for any registered phone numbers under this WABA.
                        $syncResult = $this->syncWabaPhoneNumbers($gymId, $wabaId, $accessToken);

                        if (! $syncResult['success']) {
                            Log::warning('WhatsApp phone number sync failed during embedded signup', [
                                'gym_id' => $gymId,
                                'waba_id' => $wabaId,
                                'error' => $syncResult['error'] ?? 'Unknown error',
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

            $rawResponseData = $response['data'] ?? null;
            if (! is_array($rawResponseData)) {
                return [
                    'success' => false,
                    'error' => 'Failed to retrieve WABA details from Meta API: Missing data in response',
                ];
            }

            /** @var array{id: string, name: string, account_review_status: string, business_verification_status: string, currency?: string, timezone?: string} $responseData */
            $responseData = $rawResponseData;

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

    /**
     * Fetch all phone numbers registered under a WABA from Meta API.
     *
     * @param  string  $wabaId  The WhatsApp Business Account ID
     * @param  string|null  $accessToken  The Meta access token
     * @return array{
     *     success: bool,
     *     data?: array<int, array{id: string, display_phone_number: string, verified_name?: string, status?: string, quality_score?: string}>,
     *     error?: string
     * }
     */
    public function getWabaPhoneNumbers(string $wabaId, ?string $accessToken = null): array
    {
        $token = $accessToken ?? $this->context->getAccessToken();

        if (! $token) {
            return [
                'success' => false,
                'error' => 'Meta access token is required to fetch WABA phone numbers.',
            ];
        }

        try {
            $response = $this->api->get("/{$wabaId}/phone_numbers", [
                'fields' => 'id,display_phone_number,verified_name,status,quality_score',
            ], $token);

            if (! $response['success']) {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to fetch WABA phone numbers',
                ];
            }

            /** @var array<int, array{id: string, display_phone_number: string, verified_name?: string, status?: string, quality_score?: string}> $phoneNumbers */
            $phoneNumbers = (array) ($response['data']['data'] ?? []);

            return [
                'success' => true,
                'data' => $phoneNumbers,
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch WABA phone numbers', [
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Exception fetching WABA phone numbers: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Register/activate a phone number with Meta Cloud API.
     *
     * @param  string  $phoneNumberId  The Meta phone number ID
     * @param  string  $pin  The 6-digit PIN for registration
     * @param  string|null  $accessToken  The Meta access token
     * @return array{
     *     success: bool,
     *     message?: string,
     *     error?: string
     * }
     */
    public function registerPhoneNumber(string $phoneNumberId, ?string $pin = null, ?string $accessToken = null): array
    {
        $token = $accessToken ?? $this->context->getAccessToken();
        $pin = filled($pin) ? $pin : config()->string('services.whatsapp.default_registration_pin', '123456');

        if (! $token) {
            return [
                'success' => false,
                'error' => 'Meta access token is required to register phone number.',
            ];
        }

        try {
            $response = $this->api->post("/{$phoneNumberId}/register", [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
            ], $token);

            if (! $response['success']) {
                $rawError = $response['error'] ?? 'Failed to register phone number with Meta Cloud API';

                if (str_contains(strtolower($rawError), 'pin') || str_contains($rawError, '133005')) {
                    $rawError = 'Meta 2-Step Verification PIN mismatch. Please enter the exact 6-digit PIN configured in Meta Business Manager for this phone number.';
                }

                Log::warning('Meta Cloud API phone number registration warning', [
                    'phone_number_id' => $phoneNumberId,
                    'error' => $rawError,
                ]);

                return [
                    'success' => false,
                    'error' => $rawError,
                ];
            }

            Log::info('Meta Cloud API phone number registered successfully', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return [
                'success' => true,
                'message' => 'Phone number registered successfully on Meta Cloud API',
            ];
        } catch (Exception $e) {
            Log::error('Exception while registering phone number with Meta', [
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Exception registering phone number: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sync and register WABA phone numbers for a gym.
     *
     * Queries Meta API for all phone numbers under the WABA, updates local database,
     * and attempts Cloud API registration for any pending numbers.
     *
     * @param  int  $gymId  The gym ID
     * @param  string|null  $wabaId  The WABA ID (optional)
     * @param  string|null  $accessToken  Meta Access token (optional)
     * @param  string  $pin  6-digit PIN for registration
     * @return array{success: bool, synced_count?: int, synced?: array<int, array{phone_number_id: string, phone_number: string, status: string|null}>, error?: string}
     */
    public function syncWabaPhoneNumbers(int $gymId, ?string $wabaId = null, ?string $accessToken = null, ?string $pin = null): array
    {
        $pin = filled($pin) ? $pin : config()->string('services.whatsapp.default_registration_pin', '123456');

        // Guard against concurrent runs from the queued job and the Filament action.
        $lock = Cache::lock("whatsapp:sync_waba_phones:{$gymId}", 60);

        if (! $lock->get()) {
            return ['success' => false, 'error' => 'Phone sync is already in progress for this gym'];
        }

        try {
            return $this->syncWabaPhoneNumbersLocked($gymId, $wabaId, $accessToken, $pin);
        } finally {
            $lock->release();
        }
    }

    /**
     * Perform the actual phone-number sync once the per-gym lock has been acquired.
     *
     * @return array{success: bool, synced_count?: int, synced?: array<int, array{phone_number_id: string, phone_number: string, status: string|null}>, error?: string}
     */
    protected function syncWabaPhoneNumbersLocked(int $gymId, ?string $wabaId, ?string $accessToken, string $pin): array
    {
        $account = WhatsAppAccount::query()->where('gym_id', $gymId)->first();
        if (! $account) {
            return ['success' => false, 'error' => 'WhatsApp account not found for gym'];
        }

        $targetWabaId = $wabaId ?? $account->waba_id;
        $targetToken = $accessToken ?? $account->meta_access_token;

        if (! $targetWabaId || ! $targetToken) {
            return ['success' => false, 'error' => 'WABA ID or Meta access token missing'];
        }

        $result = $this->getWabaPhoneNumbers($targetWabaId, $targetToken);
        if (! $result['success'] || ! isset($result['data']) || blank($result['data'])) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'No phone numbers found under this WABA in Meta system',
            ];
        }

        // Each WhatsAppAccount has a 1-to-1 relationship with WhatsAppPhoneNumber.
        // Select the single primary phone number to sync for this account.
        $phoneNumbers = $result['data'];
        $existingPhone = $account->phoneNumber;
        $targetPhoneData = null;

        if ($existingPhone) {
            foreach ($phoneNumbers as $phoneData) {
                if ($phoneData['id'] === $existingPhone->waba_phone_number_id) {
                    $targetPhoneData = $phoneData;
                    break;
                }
            }
        }

        if (! $targetPhoneData) {
            foreach ($phoneNumbers as $phoneData) {
                if (filled($phoneData['id']) && filled($phoneData['display_phone_number'])) {
                    $targetPhoneData = $phoneData;
                    break;
                }
            }
        }

        if (! $targetPhoneData) {
            return [
                'success' => false,
                'error' => 'No valid phone number found under this WABA in Meta system',
            ];
        }

        $phoneId = $targetPhoneData['id'];
        $displayNumber = $targetPhoneData['display_phone_number'];
        $metaStatus = $targetPhoneData['status'] ?? null;
        $failed = 0;

        $normalizedNumber = $this->normalizePhoneNumber($displayNumber);

        // If phone number status in Meta is PENDING or UNVERIFIED, attempt auto-registration via POST /{phoneId}/register
        $isPending = in_array(strtoupper((string) $metaStatus), ['PENDING', 'UNVERIFIED', 'NOT_REGISTERED'], true);
        if ($isPending) {
            $regResult = $this->registerPhoneNumber($phoneId, $pin, $targetToken);
            if ($regResult['success']) {
                $metaStatus = 'CONNECTED';
            } else {
                $failed++;
                Log::warning('WABA phone number registration failed during sync', [
                    'gym_id' => $gymId,
                    'phone_number_id' => $phoneId,
                    'error' => $regResult['error'] ?? 'Unknown error',
                ]);
            }
        }

        $this->persistPhoneNumber($account->id, $phoneId, $normalizedNumber);

        $synced = [
            [
                'phone_number_id' => $phoneId,
                'phone_number' => $normalizedNumber,
                'status' => $metaStatus,
            ],
        ];

        // Only auto-verify when phone number registered successfully AND the
        // account is already active. Never clobber review-derived status: the
        // SyncWabaStatus job owns status/verification based on Meta's review verdict.
        if ($failed === 0 && filled($synced) && $account->status === WhatsAppAccountStatusEnum::ACTIVE) {
            $account->update([
                'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
                'verified_at' => now(),
            ]);
        }

        WhatsAppProviderFactory::invalidateProviderCache($gymId);

        return [
            'success' => true,
            'synced_count' => count($synced),
            'synced' => $synced,
        ];
    }
}
