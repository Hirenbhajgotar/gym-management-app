<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class VerifyOwnNumberAction
{
    // Max 5 attempts per gym per 10 minutes
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function __construct(public $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:verify_own_number:{$gymId}";
    }

    public function __invoke(): void
    {
        $otp = $this->page->data['otp_code'] ?? '';
        if (blank($otp) || strlen($otp) !== 6) {
            throw ValidationException::withMessages(['otp_code' => 'Enter valid 6-digit OTP.']);
        }

        $gymId = $this->page->getGymId();
        $rateLimiterKey = $this->rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = ceil($seconds / 60);
            throw ValidationException::withMessages(['otp_code' => "Too many verification attempts. Please try again in {$minutes} minute(s)."]);
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        try {
            $result = $this->page->getProvider()->verifyOwnNumber($this->page->getGymId(), $this->page->senderSid, $otp);

            if ($result['success']) {
                $this->page->phoneNumber = $this->page->whatsappAccount->phoneNumber;
                $this->page->numberPurchased = true;
                Notification::make()->title('Number Verified')->success()->send();
            } else {
                throw ValidationException::withMessages(['otp_code' => $result['message'] ?? 'Verification failed.']);
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }
}
