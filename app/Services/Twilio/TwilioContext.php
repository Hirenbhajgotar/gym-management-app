<?php

namespace App\Services\Twilio;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Exception;
use Twilio\Rest\Client;

/**
 * Shared context for all Twilio services.
 * Contains common state and client management.
 */
class TwilioContext
{
    public readonly ?int $gymId;

    public readonly bool $useSandbox;

    public readonly string $sharedWabaId;

    // public readonly bool $useIndividualWaba;

    public readonly string $masterAccountSid;

    public readonly string $masterAuthToken;

    private ?Client $masterClient = null;

    private ?Client $subAccountClient = null;

    private ?WhatsAppAccount $account = null;

    private ?WhatsAppPhoneNumber $phoneNumber = null;

    public function __construct(?int $gymId = null)
    {
        $this->gymId = $gymId;
        $this->useSandbox = (bool) config('services.twilio.use_sandbox', true);
        $this->sharedWabaId = (string) config('services.twilio.shared_waba_id', '');
        // $this->useIndividualWaba = config('services.twilio.use_individual_waba', false);
        $this->masterAccountSid = (string) config('services.twilio.master_sid', '');
        $this->masterAuthToken = (string) config('services.twilio.master_token', '');

        throw_if(blank($this->masterAccountSid) || blank($this->masterAuthToken), Exception::class, 'Twilio credentials not configured');

        // Eager load account and phone number (skip for global operations without gym context)
        if ($gymId !== null) {
            $this->account = WhatsAppAccount::query()->where('gym_id', $gymId)->first();
            $this->phoneNumber = $this->account?->phoneNumber;
        }
    }

    /**
     * Check if the current gym is the system tenant.
     */
    public function isSystemTenant(): bool
    {
        return $this->gymId === config('app.system_tenant_id', 'system') ||
            $this->gymId === 'system';
    }

    /**
     * Get the API base URL for v1.
     */
    public function getApiBaseUrlv1(): string
    {
        return 'https://content.twilio.com/v1';
    }

    /**
     * Get account.
     */
    public function getAccount(): ?WhatsAppAccount
    {
        return $this->account;
    }

    /**
     * Get account or throw exception if not found.
     */
    public function getAccountOrFail(): WhatsAppAccount
    {
        throw_unless($this->account, Exception::class, 'WhatsApp account not found for this gym');

        return $this->account;
    }

    /**
     * Get phone number.
     */
    public function getPhoneNumber(): ?WhatsAppPhoneNumber
    {
        return $this->phoneNumber;
    }

    /**
     * Get master Twilio client.
     */
    public function getMasterClient(): Client
    {
        if ($this->masterClient) {
            return $this->masterClient;
        }

        return $this->masterClient = new Client(
            $this->masterAccountSid,
            $this->masterAuthToken
        );
    }

    /**
     * Get Twilio client for current gym.
     */
    public function getClient(): Client
    {
        if ($this->subAccountClient) {
            return $this->subAccountClient;
        }

        if ($this->useSandbox || $this->isSystemTenant()) {
            return $this->masterClient ??= new Client(
                $this->masterAccountSid,
                $this->masterAuthToken
            );
        }

        throw_unless($this->account, Exception::class, 'No WhatsApp account found for this gym');

        return $this->subAccountClient = new Client(
            $this->account->twilio_account_sid,
            $this->account->twilio_auth_token
        );
    }

    /**
     * Get client for specific WhatsApp account.
     */
    public function getClientForAccount(WhatsAppAccount $account): Client
    {
        if ($this->useSandbox) {
            return $this->getMasterClient();
        }

        return new Client(
            $account->twilio_account_sid,
            $account->twilio_auth_token
        );
    }

    public function getSenderPhoneNumber(): ?string
    {
        return $this->phoneNumber?->phone_number;
    }

    public function hasActivePhoneNumber(): bool
    {
        return filled($this->phoneNumber?->phone_number);
    }
}
