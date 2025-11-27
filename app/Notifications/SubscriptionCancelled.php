<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCancelled extends Notification implements ShouldQueue
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
        $subject = $this->subscription->ends_at
            ? 'Your Subscription Has Been Cancelled'
            : 'Your Subscription Has Been Cancelled and Will End Immediately';

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hi {$notifiable->name},")
            ->line('We\'re sorry to see you go. Your subscription has been cancelled.')
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Billing Cycle:** {$this->subscription->billing_cycle}")
            ->when($this->subscription->ends_at, function ($mail) {
                return $mail->line("**Access Until:** {$this->subscription->ends_at->format('M j, Y')}")
                    ->line('You will continue to have access to all features until the end of your current billing period.');
            }, function ($mail) {
                return $mail->line('Your access has been terminated immediately.');
            })
            ->line('If you change your mind, you can reactivate your subscription at any time.')
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
            'ends_at' => $this->subscription->ends_at?->toDateString(),
            'cancelled_at' => $this->subscription->cancelled_at?->toDateString(),
            'message' => 'Your subscription has been cancelled.',
        ];
    }
}
