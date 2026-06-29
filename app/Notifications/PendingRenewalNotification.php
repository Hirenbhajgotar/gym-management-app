<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingRenewalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public array $orderData,
    ) {}

    /**
     * Get the notification's delivery channels.
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
        $plan = $this->subscription->plan;
        $amount = number_format($this->orderData['amount'] / 100, 2);

        return (new MailMessage)
            ->subject(
                "Action Required: Complete Your {$plan->name} Subscription Renewal",
            )
            ->greeting("Hi {$notifiable->name},")
            ->line(
                "Your {$plan->name} subscription is expiring soon and we've prepared a renewal payment for you.",
            )
            ->line("**Amount:** ₹{$amount}")
            ->line(
                '**Billing Cycle:** ' .
                    ucfirst($this->subscription->billing_cycle),
            )
            ->action(
                'Complete Payment',
                route('subscription.payment.complete', [
                    'order_id' => $this->orderData['order_id'],
                ]),
            )
            ->line(
                'If you do not complete this payment, your subscription will be suspended and you may lose access to premium features.',
            )
            ->line('Thank you for being a valued member!')
            ->salutation('Best regards, ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'amount' => $this->orderData['amount'],
            'order_id' => $this->orderData['order_id'],
            'type' => 'pending_renewal',
            'message' => "Your {$this->subscription->plan->name} subscription renewal payment is pending.",
        ];
    }
}
