<?php

namespace App\Services\Meta;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;

/**
 * Shared context for all Meta WhatsApp services.
 *
 * Contains common state: gymId, account, API configuration.
 * Used by MetaApiClient and all Meta services for consistent access.
 */
class MetaContext
{
    public readonly int $gymId;

    public readonly string $apiVersion;

    private ?WhatsAppAccount $account = null;

    private ?WhatsAppPhoneNumber $phoneNumber = null;

    public function __construct(int $gymId)
    {
        $this->gymId = $gymId;
        $this->apiVersion = config('services.whatsapp.api_version', 'v25.0');

        // Lazy-load account
        $this->account = WhatsAppAccount::with('phoneNumber')
            ->where('gym_id', $gymId)
            ->first();

        $this->phoneNumber = $this->account?->phoneNumber;
    }

    /**
     * Get the WhatsApp account.
     */
    public function getAccount(): ?WhatsAppAccount
    {
        return $this->account;
    }

    /**
     * Get the access token for API calls.
     */
    public function getAccessToken(): ?string
    {
        return $this->account?->meta_access_token;
    }

    /**
     * Get the WABA ID for API calls.
     */
    public function getWabaId(): ?string
    {
        return $this->account?->waba_id;
    }

    /**
     * Get the API base URL.
     */
    public function getApiBaseUrl(): string
    {
        return 'https://graph.facebook.com/' . $this->apiVersion;
    }

    /**
     * Get the phone number ID.
     */
    public function getPhoneNumberId(): ?string
    {
        return $this->phoneNumber?->waba_phone_number_id;
    }

    /**
     * Get the phone number.
     */
    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber?->phone_number;
    }

    /**
     * Check if account is configured.
     */
    public function hasAccount(): bool
    {
        return $this->account !== null;
    }

    /**
     * Check if access token is available.
     */
    public function hasAccessToken(): bool
    {
        return filled($this->getAccessToken());
    }

    /**
     * Check if WABA is configured.
     */
    public function hasWaba(): bool
    {
        return filled($this->getWabaId());
    }

    /**
     * Check if phone number is configured.
     */
    public function hasPhoneNumber(): bool
    {
        return filled($this->getPhoneNumberId());
    }

    /**
     * Check if account has required configuration for messaging.
     */
    public function canSendMessages(): array
    {
        if (! $this->account) {
            return ['success' => false, 'error' => 'WhatsApp account not configured'];
        }

        if (! $this->getAccessToken()) {
            return ['success' => false, 'error' => 'Meta access token not configured'];
        }

        if (! $this->getPhoneNumberId()) {
            return ['success' => false, 'error' => 'Phone number not configured'];
        }

        if (! $this->getWabaId()) {
            return ['success' => false, 'error' => 'WABA not configured'];
        }

        return ['success' => true];
    }
}
