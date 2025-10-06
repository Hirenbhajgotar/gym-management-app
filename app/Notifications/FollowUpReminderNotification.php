<?php

namespace App\Notifications;

use App\Models\FollowUp;
use App\Services\NotificationChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpReminderNotification extends Notification implements ShouldQueue
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
        // return ['mail', 'database'];
        return NotificationChannelResolver::getChannelsForGym(
            $this->followUp->gym,
            'followup_reminders'
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Follow-up Reminder: ' . $this->followUp->lead->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('This is a reminder for your upcoming follow-up.')
            ->line('Lead: ' . $this->followUp->lead->name)
            ->line('Scheduled Date: ' . $this->followUp->scheduled_date->format('M d, Y H:i'))
            ->line('Type: ' . $this->followUp->type->getLabel())
            ->line('Notes: ' . $this->followUp->notes)
            ->action('View Follow-up', url('/admin/leads/' . $this->followUp->lead->id . '/edit'))
            ->line('Please make sure to complete this follow-up on time.');
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
            'message' => 'Follow-up reminder for ' . $this->followUp->lead->name,
        ];
    }
}
