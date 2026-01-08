<?php

namespace App\Notifications;

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
        public ?string $password = null,
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

        if ($this->password) {
            $mail->line('Your account has been created by an administrator.')
                ->line('Here are your login credentials:')
                ->line("Email: {$this->user->email}")
                ->line("Password: {$this->password}")
                ->line('Please login and change your password immediately.');
        } else {
            $mail->line('Thank you for registering your business with us.');
        }

        $mail->action('Login to Dashboard', url('/admin/login'))
            ->line('If you have any questions, feel free to contact our support team.');

        // Attach Invoice if payment exists and invoice file is available
        if ($this->payment && $this->payment->invoice_path && Storage::exists($this->payment->invoice_path)) {
            $mail->attach(Storage::path($this->payment->invoice_path), [
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
