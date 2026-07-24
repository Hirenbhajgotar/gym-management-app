<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Interfaces\WabaSetupInterface;
use App\Jobs\ImportWhatsAppTemplatesJob;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ConnectExistingWabaAction
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function __construct(public WhatsAppSetup $page) {}

    private function rateLimiterKey(int|string $gymId): string
    {
        return "whatsapp:connect_waba:{$gymId}";
    }

    public function __invoke(): void
    {
        Validator::make($this->page->data ?? [], [
            'manual_phone_number' => ['required', 'phone:INTERNATIONAL'],
            'waba_id' => ['required', 'regex:/^\d{15,18}$/'],
            'waba_phone_number_id' => ['required', 'regex:/^\d{15,18}$/'],
            'meta_access_token' => ['required', 'string', 'min:10'],
        ])->validate();

        $gymId = $this->page->getGymId();
        $rateLimiterKey = $this->rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = ceil($seconds / 60);
            throw new Exception("Too many connection attempts. Please try again in {$minutes} minute(s).");
        }

        RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

        try {
            $provider = $this->page->getProvider();
            throw_unless($provider instanceof WabaSetupInterface, Exception::class, 'WhatsApp provider does not support manual WABA connection.');

            $data = $this->page->data ?? [];
            $user = Auth::user();
            $userName = $user?->name;
            $userEmail = $user?->email;
            $businessData = [
                'business_name' => $data['business_name'] ?? $userName ?? '',
                'business_email' => $data['business_email'] ?? $userEmail ?? '',
                'business_description' => $data['business_description'] ?? '',
            ];
            $result = $provider->connectManualWaba((int) $gymId, $data['waba_id'] ?? '', $data['manual_phone_number'] ?? '', $data['meta_access_token'] ?? '', $data['waba_phone_number_id'] ?? '', $businessData);
            if ($result['success']) {
                // Import existing Meta templates as DRAFT records
                $job = new ImportWhatsAppTemplatesJob((int) $gymId);
                $job->onQueue('whatsapp-templates');
                dispatch($job);

                $this->page->refresh();
                Notification::make()->title('WABA Connected')->success()->send();
            } else {
                throw new Exception($result['error'] ?? 'Failed');
            }
        } catch (Exception $e) {
            Log::error('WABA Connect Error', ['e' => $e]);
            Notification::make()->title('Connection Failed')->body($e->getMessage())->danger()->send();
        }
    }
}
