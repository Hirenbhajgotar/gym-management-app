<?php

namespace App\Jobs;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Models\WhatsAppAccount;
use App\Services\EmbeddedSignupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncWabaStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $whatsAppAccountId
    ) {}

    public function handle(EmbeddedSignupService $embeddedSignupService): void
    {
        $account = WhatsAppAccount::find($this->whatsAppAccountId);

        if (! $account) {
            Log::error("WhatsApp account not found: {$this->whatsAppAccountId}");

            return;
        }

        if (! $account->waba_id) {
            Log::info("No WABA ID for account: {$this->whatsAppAccountId}");

            return;
        }

        if (! $account->meta_access_token) {
            Log::info("No Meta access token for account: {$this->whatsAppAccountId}");

            return;
        }

        $wabaDetails = $embeddedSignupService->getWabaDetails(
            $account->waba_id,
            $account->metaAccessToken
        );

        if (! $wabaDetails['success']) {
            Log::error('Failed to sync WABA status', [
                'account_id' => $this->whatsAppAccountId,
                'waba_id' => $account->waba_id,
                'error' => $wabaDetails['error'] ?? 'Unknown error',
            ]);

            return;
        }

        $data = $wabaDetails['data'];
        $reviewStatus = $data['account_review_status'] ?? null;
        $verificationStatus = $data['business_verification_status'] ?? null;

        $accountReviewStatus = match ($reviewStatus) {
            'APPROVED' => WhatsAppAccountStatusEnum::ACTIVE,
            'REJECTED' => WhatsAppAccountStatusEnum::BANNED,
            'PENDING' => WhatsAppAccountStatusEnum::PENDING,
            'DISABLED' => WhatsAppAccountStatusEnum::RESTRICTED,
            default => WhatsAppAccountStatusEnum::PENDING,
        };

        $accountVerificationStatus = match ($verificationStatus) {
            'VERIFIED' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'NOT_VERIFIED' => WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            'IN_REVIEW' => WhatsAppAccountVerificationStatusEnum::IN_REVIEW,
            'REJECTED' => WhatsAppAccountVerificationStatusEnum::REJECTED,
            default => WhatsAppAccountVerificationStatusEnum::PENDING,
        };

        $account->update([
            'waba_name' => $data['name'] ?? $account->waba_name,
            'status' => $accountReviewStatus,
            'verification_status' => $accountVerificationStatus,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('SyncWabaStatus job failed', [
            'whatsapp_account_id' => $this->whatsAppAccountId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
