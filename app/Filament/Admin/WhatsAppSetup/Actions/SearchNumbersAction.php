<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use Exception;
use Illuminate\Support\Facades\RateLimiter;

class SearchNumbersAction
{
    // Max 10 attempts per gym per 5 minutes
    private const MAX_ATTEMPTS = 10;

    private const DECAY_SECONDS = 300;

    public function __construct(public $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:search_numbers:{$gymId}";
    }

    public function __invoke(): void
    {
        $gymId = $this->page->getGymId();
        $rateLimiterKey = $this->rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = ceil($seconds / 60);
            throw new Exception("Too many search attempts. Please try again in {$minutes} minute(s).");
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        $this->page->isSearching = true;
        $result = $this->page->getProvider()->getAvailableNumbers($this->page->data['search_country'] ?? 'US', 'local', 5);
        $this->page->isSearching = false;
        if ($result['success'] && filled($result['numbers'])) {
            $this->page->availableNumbers = $result['numbers'];
            $this->page->availableNumbersLoaded = true;
        } else {
            $this->page->availableNumbers = [];
            $this->page->availableNumbersLoaded = true;
        }
    }
}
