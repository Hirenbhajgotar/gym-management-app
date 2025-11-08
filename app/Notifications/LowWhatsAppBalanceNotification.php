<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Notifications\Contracts\HasGymContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowWhatsAppBalanceNotification extends Notification implements ShouldQueue, HasGymContext
{
    use Queueable;

    public Gym $gym;

    public int $remainingMessages;

    /**
     * Create a new notification instance.
     */
    public function __construct(Gym $gym, int $remainingMessages)
    {
        $this->gym = $gym;
        $this->remainingMessages = $remainingMessages;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', WhatsAppChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low WhatsApp Message Balance Alert - ' . $this->gym->name)
            ->greeting("Hello {$notifiable->name} ,")
            ->line('Your WhatsApp message balance for **' . $this->gym->name . '** is running low.')
            ->line("**Remaining Messages:** {$this->remainingMessages}")
            ->line("You have {$this->remainingMessages} WhatsApp message(s) remaining. Please purchase more messages to continue sending notifications to your members.")
            ->action('Purchase Messages', route('filament.admin.pages.settings', ['tenant' => $this->gym->slug, 'tab' => 'whatsapp']))
            ->line('Thank you for using our service!');
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): array
    {
        return [
            'body' => "Low WhatsApp Balance Alert\n\nYour gym {$this->gym->name} has only {$this->remainingMessages} message(s) remaining.\n\nPlease purchase more messages to continue sending notifications.",
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Low WhatsApp Message Balance',
            'message' => "Your gym {$this->gym->name} has only {$this->remainingMessages} WhatsApp message(s) remaining.",
            'gym_id' => $this->gym->id,
            'remaining_messages' => $this->remainingMessages,
            'action_url' => '/admin/settings',
            'action_text' => 'Purchase Messages',
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->gym->id;
    }
}
