<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;

/**
 * Abstract base class for Twilio WhatsApp services.
 *
 * Provides shared functionality for all Twilio WhatsApp-related services
 * including gym context and account resolution.
 */
abstract class TwilioService
{
    protected int $gymId;

    protected ?WhatsAppAccount $whatsAppAccount = null;

    protected ?WhatsAppPhoneNumber $whatsAppPhoneNumber = null;

    protected bool $useSandbox;

    protected string $sharedWabaId;

    public function __construct(int $gymId)
    {
        $this->gymId = $gymId;
        $this->useSandbox = config('services.twilio.use_sandbox', true);
        $this->sharedWabaId = config('services.twilio.shared_waba_id', '');

        $this->loadGymContext();
    }

    /**
     * Load gym context (account and phone number).
     */
    protected function loadGymContext(): void
    {
        $this->whatsAppAccount = WhatsAppAccount::query()->where('gym_id', $this->gymId)->first();
        $this->whatsAppPhoneNumber = $this->whatsAppAccount?->phoneNumber;
    }

    /**
     * Get the gym ID.
     */
    public function getGymId(): int
    {
        return $this->gymId;
    }

    /**
     * Get the WhatsApp account.
     */
    public function getWhatsAppAccount(): ?WhatsAppAccount
    {
        return $this->whatsAppAccount;
    }

    /**
     * Get the phone number.
     */
    public function getPhoneNumber(): ?string
    {
        return $this->whatsAppPhoneNumber?->phone_number;
    }

    /**
     * Check if using sandbox mode.
     */
    public function isSandbox(): bool
    {
        return $this->useSandbox;
    }

    /**
     * Get config value.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return config($key, $default);
    }
}
