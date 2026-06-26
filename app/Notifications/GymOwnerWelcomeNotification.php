<?php

namespace App\Notifications;

use Illuminate\Support\Facades\URL;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class GymOwnerWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public ?string $token = null,
        public ?Payment $payment = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Welcome to Gym Management System')
            ->greeting("Hello {$this->user->name}!")
            ->line('Welcome to our Gym Management System. We are excited to have you on board.');

        if ($this->token) {
            $resetUrl = URL::signedRoute('filament.admin.auth.password-reset.reset', [
                'token' => $this->token,
                'email' => $this->user->email,
            ]);

            $mail->line('Your account has been created by an administrator.')
                ->line('Please set your password to access your account.')
                ->action('Set Password', $resetUrl)
                ->line('This password set link will expire in 60 minutes.')
                ->line('If you did not request this, no further action is required.');
        } else {
            $mail->line('Your account is currently under review.')
                ->line('You will be able to login once your account is reviewed and active.');
        }

        $mail->line('If you have any questions, feel free to contact our support team.');

        // Attach Invoice if payment exists and invoice file is available
        if ($this->payment && $this->payment->invoice_path && Storage::disk('local')->exists($this->payment->invoice_path)) {
            $mail->attach(Storage::disk('local')->path($this->payment->invoice_path), [
                'as' => 'Invoice-' . $this->payment->invoice_number . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'has_payment' => (bool) $this->payment,
        ];
    }
}
