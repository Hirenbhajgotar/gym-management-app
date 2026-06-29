<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadFollowUpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public FollowUp $followUp)
    {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Follow-up Assigned: ' . $this->followUp->lead->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new follow-up has been assigned to you.')
            ->line('Lead: ' . $this->followUp->lead->name)
            ->line('Scheduled Date: ' . $this->followUp->scheduled_date->format('M d, Y H:i'))
            ->line('Type: ' . $this->followUp->type->getLabel())
            ->line('Notes: ' . $this->followUp->notes)
            ->action('View Follow-up', url("/admin/{$this->followUp?->gym?->slug}/leads/{$this->followUp->lead_id}"))
            ->line('Please review and prepare for this follow-up.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'follow_up_id' => $this->followUp->id,
            'lead_id' => $this->followUp->lead->id,
            'lead_name' => $this->followUp->lead->name,
            'scheduled_date' => $this->followUp->scheduled_date->toDateTimeString(),
            'type' => $this->followUp->type->value,
            'notes' => $this->followUp->notes,
            'message' => 'New follow-up assigned for ' . $this->followUp->lead->name,
        ];
    }
}
