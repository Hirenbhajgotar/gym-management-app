<?php

namespace App\Jobs;

use App\Mail\BiometricConsentMail;
use App\Models\GymMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendBiometricConsentEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly GymMember $gymMember
    ) {}

    public function handle(): void
    {
        // Skip if already consented — idempotent
        if ($this->gymMember->hasActiveBiometricConsent()) {
            return;
        }

        // Skip if no email
        if (blank($this->gymMember->email)) {
            return;
        }

        $consentUrl = URL::temporarySignedRoute(
            'biometric.consent.show',
            now()->addDays(7),
            [
                'gym_member_id' => $this->gymMember->id,
                'gym_id' => $this->gymMember->gym_id,
            ]
        );

        Mail::to($this->gymMember->email)
            ->send(new BiometricConsentMail(
                gymMember: $this->gymMember,
                consentUrl: $consentUrl,
            ));
    }
}
