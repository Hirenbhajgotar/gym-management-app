<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserActionNotification extends Notification
{
    use Queueable;

    protected $action;

    protected $user;

    protected $details;

    public function __construct($action, $user, $details = null)
    {
        $this->action = $action; // e.g., 'created'
        $this->user = $user; // User instance (gym_owner)
        $this->details = $details; // Optional details
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ["mail"];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage()
            ->subject("New Gym Owner {$this->action} Notification")
            ->greeting("Hello!")
            ->line("A new gym owner has been {$this->action}:")
            ->line("Name: {$this->user->name}")
            ->line("Email: {$this->user->email}")
            ->when(
                $this->details,
                fn($mail) => $mail->line("Details: {$this->details}"),
            )
            ->action("View Users", url("/admin/users"));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            "action" => $this->action,
            "user_id" => $this->user->id,
            "user_name" => $this->user->name,
            "user_email" => $this->user->email,
            "details" => $this->details,
            "created_at" => now(),
        ];
    }
}
