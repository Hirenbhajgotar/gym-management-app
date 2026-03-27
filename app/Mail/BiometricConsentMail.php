<?php

namespace App\Mail;

use App\Models\GymMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BiometricConsentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly GymMember $gymMember,
        public readonly string $consentUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Biometric Data Consent — ' . $this->gymMember->gym->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.biometric-consent',
            with: [
                'gymMember' => $this->gymMember,
                'gym' => $this->gymMember->gym,
                'consentUrl' => $this->consentUrl,
                'expiresIn' => '7 days',
            ],
        );
    }
}
