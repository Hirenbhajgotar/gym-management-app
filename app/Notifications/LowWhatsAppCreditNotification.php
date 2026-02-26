<?php

namespace App\Notifications;

use App\Models\WhatsAppMessageCredit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowWhatsAppCreditNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WhatsAppMessageCredit $credit
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $balance = number_format($this->credit->getBalanceInDollars(), 2);

        return (new MailMessage)
            ->subject('Your Gym\'s WhatsApp Message Credits Are Low - Action Required')
            ->greeting('Hello ' . ($notifiable->name ?? 'Gym Owner') . ',')
            ->line('Your WhatsApp message credit balance is running low.')
            ->line("You currently have **{$this->credit->remaining_credits}** messages remaining (estimated value: **\${$balance}**).")
            ->line('To ensure uninterrupted automated notifications and seamless communication with your members, please consider topping up your balance soon.')
            ->action('Purchase Credits', url('/admin/whatsapp-setup'))
            ->line('Thank you for choosing our platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
