<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BirthdayNotification extends Notification
{
    use Queueable;

    protected $member;

    public function __construct($member)
    {
        $this->member = $member;
    }

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
            ->subject('Happy Birthday, ' . $this->member->name . '!')
            ->greeting('Hello ' . $this->member->name . ',')
            ->line('We at ' . $this->member->gym->name . ' wish you a very Happy Birthday!')
            ->line('May you have a fantastic year ahead filled with health and fitness.')
            ->action('Visit Us', url('/'))
            ->line('Thank you for being a valued member!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Happy Birthday, ' . $this->member?->name . '! From ' . $this->member?->gym?->name,
        ];
    }
}
