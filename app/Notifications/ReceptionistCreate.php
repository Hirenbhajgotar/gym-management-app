<?php

namespace App\Notifications;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceptionistCreate extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public User $user,
        public string $password
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Team!')
            ->greeting('Hello ' . $this->user->name . '!')
            ->line('You have been added as a staff member. Please use the following credentials to log in:')
            ->line('Email: ' . $this->user->email)
            ->line('Password: ' . $this->password)
            ->line('Please login and change your password for security.')
            ->action('Login Now', url('/staff-member/login'))
            ->line('Thank you for joining our team!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
