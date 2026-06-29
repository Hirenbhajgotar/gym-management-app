<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public \Exception $exception
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
            ->subject('Subscription Renewal Error - ' . config('app.name'))
            ->greeting("Hi {$notifiable->name}!")
            ->line('There was an error processing your gym subscription renewal.')
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Error:** {$this->exception->getMessage()}")
            ->line('Our team has been notified and will resolve this issue shortly.')
            ->line('You may continue using your current subscription until this is resolved.')
            ->action('Contact Support', url('/support'))
            ->salutation('Best regards,<br>' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'error_message' => $this->exception->getMessage(),
            'message' => 'There was an error processing your subscription renewal.',
        ];
    }
}
