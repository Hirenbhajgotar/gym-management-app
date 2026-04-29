<?php

namespace App\Services\Meta;

use App\Enums\WabaModeEnum;
use App\Enums\WabaStatusEnum;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Interfaces\WabaSetupInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Providers\WhatsAppProviderFactory as ProvidersWhatsAppProviderFactory;
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
                'waba_mode' => WabaModeEnum::INDIVIDUAL,
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

        $verificationResult = $this->verifyWabaConnection($wabaId, $phoneNumberId, $metaAccessToken);

        if (! $verificationResult['success']) {
            return [
                'success' => false,
                'error' => $verificationResult['error'] ?? 'Failed to verify WABA connection with Meta',
            ];
        }

        if (! ($verificationResult['verified'] ?? false)) {
            return [
                'success' => false,
                'error' => 'The provided Phone Number ID does not exist or is not valid in Meta system',
            ];
        }

        return DB::transaction(function () use ($gymId, $businessData, $wabaId, $phoneNumberId, $metaAccessToken, $verificationResult, $phoneNumber) {
            $businessName = $verificationResult['business_name']
                ?? ($businessData['business_name'] ?? null);

            $businessEmail = $businessData['business_email'] ?? null;
            $businessDescription = $businessData['business_description'] ?? '';

            $displayPhoneNumber = $verificationResult['display_phone_number'] ?? $phoneNumber;
            $verifiedPhoneNumberId = $verificationResult['phone_number_id'] ?? $phoneNumberId;
            $normalizedPhoneNumber = $this->normalizePhoneNumber($displayPhoneNumber);
            $countryCode = $this->extractCountryCode($normalizedPhoneNumber);
            $phoneType = $this->determinePhoneType($verificationResult);
            $verificationStatus = $verificationResult['business_verification_status'];
            $wabaStatusEnum = $verificationResult['waba_status']
                ?? WabaStatusEnum::ACTIVE;

            $accountData = [
                'gym_id' => $gymId,
                'business_name' => $businessName,
                'business_email' => $businessEmail,
                'business_description' => $businessDescription,
                'waba_id' => $wabaId,
                'waba_name' => $businessName,
                'waba_mode' => WabaModeEnum::MANUAL,
                'meta_access_token' => $metaAccessToken,
                'embedded_signup_completed' => true,
                'waba_status' => $wabaStatusEnum,
                'provider_type' => WhatsAppProviderTypeEnum::WABA,
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
                'verification_status' => $verificationStatus,
                'verified_at' => now(),
            ];

            $account = WhatsAppAccount::updateOrCreate([
                'gym_id' => $gymId,
            ], $accountData);

            WhatsAppPhoneNumber::updateOrCreate([
                'whatsapp_account_id' => $account->id,
            ], [
                'waba_phone_number_id' => $verifiedPhoneNumberId,
                'phone_number' => $normalizedPhoneNumber,
                'country_code' => $countryCode,
                'friendly_name' => $normalizedPhoneNumber,
                'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                'type' => $phoneType,
            ]);

            ProvidersWhatsAppProviderFactory::invalidateProviderCache($gymId);

            return [
                'success' => true,
                'waba_id' => $wabaId,
                'phone_number_id' => $verifiedPhoneNumberId,
                'display_phone_number' => $normalizedPhoneNumber,
                'business_name' => $businessName,
                'waba_status' => $wabaStatusEnum?->value,
                'verification_status' => $verificationStatus?->value,
                'message' => 'Manual WABA connected successfully',
            ];
        });
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
            ]);

            if (! $phoneResponse['success']) {
                $errorMessage = $phoneResponse['error'] ?? 'Failed to fetch phone number details';

                return $this->verificationError("Meta API error: {$errorMessage}");
            }

            $phoneData = $phoneResponse['data'];

            $wabaResponse = $this->api->get("/{$wabaId}", [
                'fields' => 'id,name,business,business_verification_status,currency,timezone',
            ]);

            $wabaData = $wabaResponse['success'] ? $wabaResponse['data'] : [];

            $businessName = $wabaData['business']['name'] ?? $wabaData['name'] ?? $phoneData['verified_name'] ?? null;

            $businessVerificationStatus = $this->mapBusinessVerificationStatus($wabaData['business_verification_status'] ?? null);
            $wabaStatus = $this->mapWabaStatus($wabaData['business_verification_status'] ?? $wabaData['status'] ?? null);

            return [
                'success' => true,
                'verified' => true,
                'phone_number_id' => $phoneData['id'] ?? $phoneNumberId,
                'display_phone_number' => $phoneData['display_phone_number'] ?? null,
                'verified_name' => $phoneData['verified_name'] ?? null,
                'business_verification_status' => $businessVerificationStatus->value,
                'code_verification_status' => $phoneData['code_verification_status'] ?? null,
                'certificate' => $phoneData['certificate'] ?? null,
                'quality_score' => $phoneData['quality_score'] ?? null,
                'waba_id' => $wabaData['id'] ?? $wabaId,
                'business_name' => $businessName,
                'waba_status' => $wabaStatus->value,
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
            'waba_status' => null,
            'currency' => null,
            'timezone' => null,
        ];
    }

    protected function mapBusinessVerificationStatus(?string $status): WhatsAppAccountVerificationStatusEnum
    {
        return match ($status) {
            'VERIFIED' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'UNVERIFIED' => WhatsAppAccountVerificationStatusEnum::PENDING,
            'PENDING' => WhatsAppAccountVerificationStatusEnum::PENDING,
            'IN_REVIEW' => WhatsAppAccountVerificationStatusEnum::IN_REVIEW,
            'REJECTED' => WhatsAppAccountVerificationStatusEnum::REJECTED,
            default => WhatsAppAccountVerificationStatusEnum::PENDING,
        };
    }

    protected function mapWabaStatus(?string $status): WabaStatusEnum
    {
        return match ($status) {
            'VERIFIED' => WabaStatusEnum::ACTIVE,
            'UNVERIFIED' => WabaStatusEnum::PENDING,
            'PENDING' => WabaStatusEnum::PENDING,
            'IN_REVIEW' => WabaStatusEnum::PENDING,
            default => WabaStatusEnum::PENDING,
        };
    }

    protected function normalizePhoneNumber(string $phoneNumber): string
    {
        if (str_starts_with($phoneNumber, '+')) {
            return $phoneNumber;
        }

        return '+' . ltrim($phoneNumber, '+');
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
        $codeStatus = $verificationResult['code_verification_status'] ?? null;

        if ($codeStatus === 'VERIFIED') {
            return WhatsAppPhoneNumberTypesEnum::BUSINESS;
        }

        return WhatsAppPhoneNumberTypesEnum::BUSINESS;
    }
}
