<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use Filament\Notifications\Notification;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class ProcessTwilioAccountAction
{
    // Max 5 attempts per gym per 10 minutes
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function __construct(public $page) {}

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
            throw new \Exception("Too many setup attempts. Please try again in {$minutes} minute(s).");
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        $gym = $this->page->getGym();
        $user = Auth::user();
        $data = $this->page->data;

        $existing = WhatsAppAccount::with('phoneNumber')->where('gym_id', $gymId)->first();
        if ($existing) {
            $existing->update([
                'business_name' => $data['business_name'] ?? $gym?->name,
                'business_email' => $data['business_email'] ?? $user?->email,
                'business_description' => $data['business_description'] ?? '',
            ]);
            $this->page->whatsappAccount = $existing->fresh();
        } else {
            $whatsappAccount = WhatsAppAccount::query()->create([
                'gym_id' => $gymId,
                'business_name' => $data['business_name'] ?? $gym?->name,
                'business_email' => $data['business_email'] ?? $user?->email,
                'business_description' => $data['business_description'] ?? '',
                'provider_type' => WhatsAppProviderTypeEnum::TWILIO->value,
                'status' => WhatsAppAccountStatusEnum::PENDING,
            ]);
            $this->page->whatsappAccount = $whatsappAccount->load(['phoneNumber']);
        }

        $result = $this->page->getProvider()->createSubAccount($gymId, $this->page->whatsappAccount->business_name, $this->page->whatsappAccount->business_email);

        if (! $result['success']) {
            throw new \Exception($result['error'] ?? 'Failed to create subaccount');
        }

        $this->page->whatsappAccount->refresh();
        $this->page->accountCreated = true;
        $this->page->selectedProvider = 'twilio';
        Notification::make()->title('Twilio Account Created')->success()->send();
    }
}
