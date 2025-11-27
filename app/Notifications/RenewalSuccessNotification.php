<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription
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
            ->subject('Subscription Renewed Successfully - ' . config('app.name'))
            ->greeting("Great news {$notifiable->name}!")
            ->line('Your gym subscription has been renewed successfully.')
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Amount Charged:** {$this->subscription->price} {$this->subscription->currency}")
            ->line("**Next Billing Date:** {$this->subscription->current_period_end->format('M j, Y')}")
            ->line('You can continue using all the features included in your plan.')
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
            'price' => $this->subscription->price,
            'currency' => $this->subscription->currency,
            'current_period_end' => $this->subscription->current_period_end?->toDateString(),
            'message' => 'Your subscription has been renewed successfully.',
        ];
    }
}
