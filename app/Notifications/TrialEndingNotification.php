<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingNotification extends Notification implements ShouldQueue
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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Trial is Ending Soon - ' . config('app.name'))
            ->greeting("Hi {$notifiable->name}!")
            ->line("Your trial period for your gym subscription is ending soon.")
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Trial Ends:** {$this->subscription->trial_ends_at->format('M j, Y')}")
            ->line('To continue using all the features, please ensure your payment method is up to date.')
            // ->line('Your subscription will automatically renew at the end of your trial period.')
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
            'trial_ends_at' => $this->subscription->trial_ends_at?->toDateString(),
            'message' => 'Your trial period is ending soon.',
        ];
    }
}
