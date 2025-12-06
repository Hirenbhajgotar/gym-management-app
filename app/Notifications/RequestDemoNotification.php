<?php

namespace App\Notifications;

use App\Models\RequestContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestDemoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public RequestContact $requestContact;

    /**
     * Create a new notification instance.
     */
    public function __construct(RequestContact $requestContact)
    {
        $this->requestContact = $requestContact;
    }

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
        $appName = config('app.name', 'Fluxfitness');

        return (new MailMessage)
            ->subject('New Demo Request - ' . $this->requestContact->name)
            ->greeting('Hello Admin,')
            ->line('You have received a new demo request.')
            ->line('')
            ->line('**Contact Details:**')
            ->line('• **Name:** ' . $this->requestContact->name)
            ->line('• **Email:** ' . $this->requestContact->email)
            ->line('• **Phone:** ' . $this->requestContact->phone)
            ->lineIf(
                $this->requestContact->company_name,
                '• **Company:** ' . $this->requestContact->company_name,
            )
            ->lineIf(
                $this->requestContact->gym_size,
                '• **Gym Size:** ' .
                    $this->requestContact->gym_size .
                    ' members',
            )
            ->line('')
            ->lineIf($this->requestContact->message, '**Message:**')
            ->lineIf(
                $this->requestContact->message,
                $this->requestContact->message,
            )
            ->line('')
            ->line('**Request Details:**')
            ->line(
                '• **Submitted:** ' .
                    $this->requestContact->created_at->format('M d, Y H:i A'),
            )
            ->line(
                '• **IP Address:** ' .
                    ($this->requestContact->ip_address ?? 'N/A'),
            )
            ->line('')
            ->line('Please respond to this request as soon as possible.')
            ->salutation("Best regards,\n{$appName} System");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'request_contact_id' => $this->requestContact->id,
            'name' => $this->requestContact->name,
            'email' => $this->requestContact->email,
            'phone' => $this->requestContact->phone,
            'company_name' => $this->requestContact->company_name,
            'created_at' => $this->requestContact->created_at->toISOString(),
        ];
    }
}
