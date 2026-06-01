<?php

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class ProcessWabaSetupAction
{
    // Max 5 attempts per gym per 10 minutes
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function __construct(public $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:waba_setup:{$gymId}";
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
        $accountData = [
            'gym_id' => $gymId, 'business_name' => $data['business_name'] ?? $gym?->name,
            'business_email' => $data['business_email'] ?? $user?->email, 'business_description' => $data['business_description'] ?? '',
            'provider_type' => WhatsAppProviderTypeEnum::WABA->value, 'status' => WhatsAppAccountStatusEnum::PENDING,
        ];

        $existing = WhatsAppAccount::with('phoneNumber')->where('gym_id', $gymId)->first();

        if ($existing) {
            $existing->update($accountData);
            $this->page->whatsappAccount = $existing->fresh();
        } else {
            $whatsappAccount = WhatsAppAccount::create($accountData);
            $this->page->whatsappAccount = $whatsappAccount->load(['phoneNumber']);
        }
        $this->page->accountCreated = true;
        \Filament\Notifications\Notification::make()->title('WABA Configured')->success()->send();
    }
}
