<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpired extends Notification implements ShouldQueue
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
            ->subject('Your Subscription Has Expired')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your subscription has expired and your access has been limited.')
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Billing Cycle:** {$this->subscription->billing_cycle}")
            ->line('You can renew your subscription at any time to regain full access to all features.')
            ->line('During the grace period, you may still have limited access to view your data.')
            ->action('Renew Subscription', url('/subscription/renew'))
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
            'billing_cycle' => $this->subscription->billing_cycle,
            'status' => $this->subscription->status->value,
            'expired_at' => $this->subscription->current_period_end?->toDateString(),
            'message' => 'Your subscription has expired.',
        ];
    }
}