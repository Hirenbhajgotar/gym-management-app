<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\WhatsAppAccount;
use App\Providers\WhatsAppProviderFactory;
use App\Services\Meta\MetaWabaService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWabaStatus implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $whatsAppAccountId
    ) {}

    public function handle(): void
    {
        $account = WhatsAppAccount::query()->find($this->whatsAppAccountId);

        if (! $account || ! $account->waba_id || ! $account->meta_access_token) {
            Log::warning('SyncWabaStatus skipped: Invalid account data', [
                'account_id' => $this->whatsAppAccountId,
            ]);

            return;
        }

        // Only WABA provider supports WABA status sync
        if ($account->provider_type !== WhatsAppProviderTypeEnum::WABA) {
            return;
        }

        // Use provider factory to get the MetaWhatsAppProvider
        $provider = WhatsAppProviderFactory::create(
            gymId: $account->gym_id,
            providerType: $account->provider_type?->value
        );

        $wabaDetails = $provider->getWabaDetails(
            $account->waba_id,
            $account->meta_access_token
        );

        if (! $wabaDetails['success']) {
            Log::error('Failed to sync WABA status', [
                'account_id' => $this->whatsAppAccountId,
                'waba_id' => $account->waba_id,
                'error' => $wabaDetails['error'] ?? 'Unknown error',
            ]);

            // Throwing exception here triggers the $tries and $backoff retry mechanism
            throw new Exception($wabaDetails['error'] ?? 'Meta API fetch failed');
        }

        $data = $wabaDetails['data'] ?? [];
        $reviewStatus = MetaWabaService::extractAccountReviewStatus($data);
        $verificationStatus = $data['business_verification_status'] ?? null;

        if ($reviewStatus === null && $verificationStatus === null) {
            Log::warning('Meta API response missing both status fields, skipping update', [
                'account_id' => $this->whatsAppAccountId,
                'waba_id' => $account->waba_id,
                'review_status' => $reviewStatus,
                'verification_status' => $verificationStatus,
                'has_name' => isset($data['name']),
            ]);

            return;
        }

        $account->update([
            'waba_name' => $data['name'] ?? $account->waba_name,
            'status' => WhatsAppAccountStatusEnum::fromMetaReviewStatus($reviewStatus),
            'verification_status' => WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus($verificationStatus),
            'waba_status_synced_at' => now(),
        ]);

        if (method_exists($provider, 'syncWabaPhoneNumbers')) {
            $provider->syncWabaPhoneNumbers($account->gym_id, $account->waba_id, $account->meta_access_token);
        }

        Log::info('WABA status synced successfully', [
            'account_id' => $this->whatsAppAccountId,
            'waba_id' => $account->waba_id,
            'status' => $reviewStatus,
            'verification_status' => $verificationStatus,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SyncWabaStatus job failed', [
            'whatsapp_account_id' => $this->whatsAppAccountId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
