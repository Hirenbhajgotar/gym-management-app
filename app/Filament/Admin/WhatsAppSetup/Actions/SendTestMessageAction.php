<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class SendTestMessageAction
{
    // Max 5 attempts per gym per 15 minutes
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function __construct(public $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:send_test_message:{$gymId}";
    }

    public function __invoke(array $data): void
    {
        $gymId = $this->page->getGymId();
        $rateLimiterKey = $this->rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = ceil($seconds / 60);
            throw new \Exception("Too many test message attempts. Please try again in {$minutes} minute(s).");
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        try {
            $result = $this->page->getProvider()->sendTemplateMessage($data['test_phone'], 'welcome_message', [
                '1' => $this->page->getGym()?->name ?? 'Gym', '2' => Auth::user()?->name ?? 'User', '3' => 'Premium', '4' => now()->addDays(30)->format('F j, Y'),
            ]);

            if ($result['success']) {
                Notification::make()->title('Test Message Sent!')->success()->send();
            } else {
                throw new \Exception($result['error'] ?? 'Failed');
            }
        } catch (\Exception $e) {
            Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
        }
    }
}
