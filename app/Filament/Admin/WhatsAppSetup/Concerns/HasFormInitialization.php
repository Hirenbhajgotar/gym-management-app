<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Concerns;

use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use Illuminate\Support\Facades\Auth;

trait HasFormInitialization
{
    protected function initializeFormData(): void
    {
        $gym = $this->getGym();
        $user = Auth::user();

        $existingProvider = $this->whatsappAccount?->provider_type?->value ?? null;

        if ($existingProvider !== null) {
            $this->selectedProvider = $existingProvider;
        } else {
            $selectedProvider = $this->data['provider_type'] ?? $this->selectedProvider ?? null;
            if (is_string($selectedProvider)) {
                $this->selectedProvider = $selectedProvider;
            } elseif ($selectedProvider instanceof WhatsAppProviderTypeEnum) {
                $this->selectedProvider = $selectedProvider->value;
            }
        }

        $this->data = [
            'business_name' => $this->whatsappAccount->business_name ?? $gym->name,
            'business_email' => $this->whatsappAccount->business_email ?? $user->email,
            'business_description' => $this->whatsappAccount->business_description ?? '',
            'search_country' => 'US',
            'waba_connection_method' => 'embedded_signup',
            'waba_id' => $this->whatsappAccount->waba_id ?? '',
            'waba_phone_number_id' => '',
            'manual_phone_number' => '',
            'meta_access_token' => '',
            'phone_number_source' => 'use_own_number',
            'own_phone_number' => '',
            'otp_code' => '',
            'provider_type' => $this->selectedProvider,
            'billing_mode' => $this->whatsappAccount?->billing_mode?->value ?? WhatsAppBillingModeEnum::PAY_VIA_APP->value,
            'has_existing_number' => false,
        ];

        $this->businessProfile = [
            'business_name' => $this->whatsappAccount->business_name ?? $gym->name ?? '',
            'business_email' => $this->whatsappAccount->business_email ?? $user->email ?? '',
            'business_description' => $this->whatsappAccount->business_description ?? '',
        ];
    }
}
