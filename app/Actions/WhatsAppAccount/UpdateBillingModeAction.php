<?php

declare(strict_types=1);

namespace App\Actions\WhatsAppAccount;

use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\WhatsAppAccount;
use Exception;

class UpdateBillingModeAction
{
    public function __invoke(WhatsAppAccount $account, WhatsAppBillingModeEnum $billingMode): bool
    {
        throw_unless($account, Exception::class, 'Account not found');

        throw_unless(
            $account->provider_type === WhatsAppProviderTypeEnum::WABA,
            Exception::class,
            'Self-managed (postpaid) billing is only available for Meta (WABA) accounts.'
        );

        return $account->update([
            'billing_mode' => $billingMode,
        ]);
    }
}
