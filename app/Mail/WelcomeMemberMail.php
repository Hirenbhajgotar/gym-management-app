<?php

namespace App\Mail;

use App\Models\GymMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMemberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected GymMember $member
    ) {}

    public function envelope(): Envelope
    {
        $gymName = $this->member->gym?->name ?? 'the Gym';

        return new Envelope(
            subject: "Welcome to {$gymName}!"
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome-member',
            with: [
                'memberName' => $this->member->name,
                'planName' => $this->member->membershipPlan->name,
                'startDate' => $this->member->membership_start->format('Y-m-d'),
                'endDate' => $this->member->membership_end->format('Y-m-d'),
                'gymName' => $this->member->gym?->name ?? 'the Gym',
            ],
        );
    }
}
