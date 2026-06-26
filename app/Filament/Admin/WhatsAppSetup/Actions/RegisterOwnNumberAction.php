<?php

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RegisterOwnNumberAction
{
    // Max 5 attempts per gym per 10 minutes
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function __construct(public $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:register_own_number:{$gymId}";
    }

    public function __invoke(): void
    {
        $this->page->form->validate([
            'own_phone_number' => [
                'required',
                'string',
                'phone',
            ],
        ]);

        $gymId = $this->page->getGymId();
        $rateLimiterKey = $this->rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = ceil($seconds / 60);
            throw ValidationException::withMessages(['own_phone_number' => "Too many registration attempts. Please try again in {$minutes} minute(s)."]);
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        try {
            $result = $this->page->getProvider()->registerOwnNumber($this->page->getGymId(), $this->page->data['own_phone_number']);

            if ($result['success']) {
                $this->page->senderSid = $result['sender_sid'];
                $this->page->ownNumberRegistered = true;
                Notification::make()->title('Verification Code Sent')->success()->send();
            } else {
                throw new \Exception($result['error'] ?? 'Failed to register phone number');
            }
        } catch (\Exception $e) {
            Log::error('Reg Num Error', ['e' => $e]);
            throw ValidationException::withMessages(['own_phone_number' => $e->getMessage()]);
        }
    }
}
