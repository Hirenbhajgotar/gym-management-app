<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanExpiryNotification extends Notification implements ShouldQueue
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
        return ['mail', 'filament'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Subscription is Ending Soon - ' . config('app.name'))
            ->greeting("Hi {$notifiable->name}!")
            ->line("Your gym subscription is ending soon.")
            ->line("**Plan:** {$this->subscription->plan->name}")
            ->line("**Billing Cycle:** {$this->subscription->billing_cycle}")
            ->line("**Expires:** {$this->subscription->current_period_end->format('M j, Y')}")
            ->line('To continue using all the features, please renew your subscription.')
            ->action('Renew Subscription', url('/subscription/renew'))
            ->salutation('Best regards,<br>' . config('app.name') . ' Team');
    }

    /**
     * Get the Filament notification representation.
     */
    public function toFilament(object $notifiable): array
    {
        return [
            'title' => 'Subscription Ending Soon',
            'body' => "Your {$this->subscription->plan->name} subscription ends on {$this->subscription->current_period_end->format('M j, Y')}",
            'icon' => 'heroicon-o-exclamation-triangle',
            'color' => 'warning',
            'actions' => [
                [
                    'name' => 'renew_subscription',
                    'label' => 'Renew Now',
                    'url' => url('/subscription/renew'),
                    'button' => true,
                ],
            ],
        ];
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
            'expires_at' => $this->subscription->current_period_end?->toDateString(),
            'message' => 'Your subscription is ending soon.',
        ];
    }
}
