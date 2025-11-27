<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCreated extends Notification implements ShouldQueue
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
            ->subject('New Subscription Created - Welcome to ' . config('app.name'))
            ->greeting("Congratulations {$notifiable->name}!")
            ->line('A new subscription has been created for your gym.')
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Billing Cycle:** {$this->subscription->billing_cycle}")
            ->line("**Price:** {$this->subscription->price} {$this->subscription->currency}")
            ->line("**Status:** {$this->subscription->status->value}")
            ->when($this->subscription->trial_ends_at, function ($mail) {
                return $mail->line("**Trial Ends:** {$this->subscription->trial_ends_at->format('M j, Y')}");
            })
            ->line('Your subscription is now active and you can start using all the features included in your plan.')
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
            'price' => $this->subscription->price,
            'currency' => $this->subscription->currency,
            'status' => $this->subscription->status->value,
            'trial_ends_at' => $this->subscription->trial_ends_at?->toDateString(),
            'message' => 'A new subscription has been created for your gym.',
        ];
    }
}
