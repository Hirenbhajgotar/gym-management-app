<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
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
            ->subject('Payment Failed - Subscription Suspended - ' . config('app.name'))
            ->greeting("Hi {$notifiable->name}!")
            ->line("We were unable to process the payment for your gym subscription renewal.")
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Amount Due:** {$this->subscription->price} {$this->subscription->currency}")
            ->line('Your subscription has been temporarily suspended. Please update your payment method to restore access.')
            ->line('If payment is not received within 7 days, your subscription may be cancelled.')
            ->action('Update Payment Method', url('/billing'))
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
            'message' => 'Payment failed. Subscription suspended. Please update payment method.',
        ];
    }
}