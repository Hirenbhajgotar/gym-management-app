<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PurchaseNumberAction
{
    // Max 3 attempts per gym per 15 minutes
    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 900;

    public function __construct(public $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:purchase_number:{$gymId}";
    }

    public function __invoke(): void
    {
        if (! $this->page->selectedNumber) {
            Notification::make()
                ->title('Select Number')
                ->body('Please select a phone number first.')
                ->warning()
                ->send();

            return;
        }

        $gymId = $this->page->getGymId();
        $rateLimiterKey = $this->rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = ceil($seconds / 60);
            throw new Exception("Too many purchase attempts. Please try again in {$minutes} minute(s).");
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        try {
            $result = $this->page->getProvider()->purchaseNumber(
                $this->page->getGymId(),
                $this->page->selectedNumber
            );

            if ($result['success']) {
                $this->page->phoneNumber = $this->page->whatsappAccount?->phoneNumber;
                $this->page->availableNumbers = [];
                $this->page->availableNumbersLoaded = false;
                $this->page->selectedNumber = null;
                $this->page->numberPurchased = true;

                Notification::make()
                    ->title('Number Purchased')
                    ->body('Your WhatsApp phone number has been purchased.')
                    ->success()
                    ->send();
            } else {
                throw new Exception($result['error'] ?? 'Failed to purchase number');
            }
        } catch (Exception $e) {
            Log::error('Failed to purchase number', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Purchase Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
