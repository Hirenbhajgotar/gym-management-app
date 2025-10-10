<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspended extends Notification implements ShouldQueue
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
            ->subject('Your Subscription Has Been Suspended')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your subscription has been suspended due to a payment issue or policy violation.')
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Billing Cycle:** {$this->subscription->billing_cycle}")
            ->line('Your access to premium features has been temporarily disabled.')
            ->line('Please contact support to resolve this issue and reactivate your subscription.')
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
            'billing_cycle' => $this->subscription->billing_cycle,
            'status' => $this->subscription->status->value,
            'suspended_at' => now()->toDateString(),
            'message' => 'Your subscription has been suspended.',
        ];
    }
}