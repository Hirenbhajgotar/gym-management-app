<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Interfaces\WabaSetupInterface;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class RegisterWabaPhoneAction
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function __construct(public WhatsAppSetup $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:register_waba_phone:{$gymId}";
    }

    public function __invoke(?string $pin = '123456'): void
    {
        $gymId = $this->page->getGymId();
        $rateLimiterKey = $this->rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = (int) ceil($seconds / 60);
            Notification::make()
                ->title('Too Many Registration Attempts')
                ->body("Please wait {$minutes} minute(s) before trying again.")
                ->warning()
                ->send();

            return;
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        try {
            $provider = $this->page->getProvider();
            throw_unless($provider instanceof WabaSetupInterface, Exception::class, 'Current provider does not support WABA phone registration.');

            $wabaId = $this->page->whatsappAccount?->waba_id;
            $token = $this->page->whatsappAccount?->meta_access_token;
            $registrationPin = filled($pin) ? $pin : '123456';

            /** @var array{success: bool, synced_count?: int, synced?: array, error?: string} $result */
            $result = $provider->syncWabaPhoneNumbers((int) $gymId, $wabaId, $token, $registrationPin);

            if ($result['success']) {
                $count = $result['synced_count'] ?? 0;
                Notification::make()
                    ->title('Phone Number Registered & Synced')
                    ->body("Successfully synced and registered {$count} phone number(s) on Meta Cloud API.")
                    ->success()
                    ->send();

                $this->page->redirect(route('filament.admin.pages.whats-app-setup', ['tenant' => $this->page->getTenantSlug()]));

                return;
            }

            $errorMessage = is_string($result['error'] ?? null) ? $result['error'] : 'Failed to register/sync phone number with Meta.';
            throw new Exception($errorMessage);
        } catch (Exception $e) {
            Log::error('Register WABA Phone Action Error', ['error' => $e->getMessage()]);
            Notification::make()
                ->title('Registration Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
