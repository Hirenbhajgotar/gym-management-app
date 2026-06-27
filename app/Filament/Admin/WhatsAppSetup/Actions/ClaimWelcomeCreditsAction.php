<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use App\Models\WhatsAppMessageCredit;
use App\Services\WhatsAppBillingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;

class ClaimWelcomeCreditsAction
{
    private const MAX_ATTEMPTS = 1;

    private const DECAY_SECONDS = 86400;

    public function __construct(public $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:claim_credits:{$gymId}";
    }

    public function __invoke(): void
    {
        $gymId = $this->page->getGymId();
        $rateLimiterKey = $this->rateLimiterKey($gymId);

        throw_if(RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS), \Exception::class, 'Welcome credits have already been claimed for this gym.');

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        resolve(WhatsAppBillingService::class)->addWelcomeCredits($this->page->getGymId());
        $this->page->creditBalance = WhatsAppMessageCredit::query()->where('gym_id', $this->page->getGymId())->first();
        $this->page->welcomeCreditsClaimed = true;
        if ($this->page->whatsappAccount) {
            $this->page->whatsappAccount->update(['status' => 'active']);
            $this->page->whatsappAccount->refresh();
        }
        $this->page->viewState = 'active';
        Notification::make()->title('Welcome Credits Claimed!')->success()->send();
    }
}
