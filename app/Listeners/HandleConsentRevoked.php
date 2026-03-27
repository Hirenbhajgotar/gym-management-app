<?php

namespace App\Listeners;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Events\BiometricConsentRevoked;

class HandleConsentRevoked
{
    public function __construct(
        private BiometricDataDeletionService $deletionService
    ) {}

    public function handle(BiometricConsentRevoked $event): void
    {
        // Delete all biometric templates for this member
        $this->deletionService->deleteForGymMember($event->gymMember);
    }
}
