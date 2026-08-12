<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Interfaces\AccountManagementInterface;
use App\Models\WhatsAppAccount;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class ProcessTwilioAccountAction
{
    // Max 5 attempts per gym per 10 minutes
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function __construct(public WhatsAppSetup $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "twilio:account_setup:{$gymId}";
    }

    public function __invoke(): void
    {
        $gymId = $this->page->getGymId();

        $rateLimiterKey = $this->rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = ceil($seconds / 60);
            throw new Exception("Too many setup attempts. Please try again in {$minutes} minute(s).");
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        $gym = $this->page->getGym();
        $user = Auth::user();
        $data = $this->page->data;

        $whatsappAccount = WhatsAppAccount::query()->updateOrCreate(['gym_id' => $gymId], [
            'business_name' => $data['business_name'] ?? $gym?->name,
            'business_email' => $data['business_email'] ?? $user?->email,
            'business_description' => $data['business_description'] ?? '',
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO->value,
            'billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP,
        ]);
        $this->page->whatsappAccount = $whatsappAccount->load(['phoneNumber'])->fresh();

        $provider = $this->page->getProvider();

        throw_unless($provider instanceof AccountManagementInterface, Exception::class, 'The selected provider does not support account management.');

        $businessName = $this->page->whatsappAccount->business_name;
        $businessEmail = $this->page->whatsappAccount->business_email;

        throw_if($businessName === null || $businessEmail === null, Exception::class, 'Business name and email are required.');

        $result = $provider->createSubAccount((int) $gymId, $businessName, $businessEmail);

        if (! $result['success']) {
            throw new Exception($result['error'] ?? 'Failed to create subaccount');
        }

        $this->page->whatsappAccount->refresh();
        $this->page->accountCreated = true;
        $this->page->selectedProvider = 'twilio';
        Notification::make()->title('Twilio Account Created')->success()->send();
    }
}
