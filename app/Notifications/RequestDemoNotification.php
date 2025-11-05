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
        return ['mail', WhatsAppChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'FitnessFlow');

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
                $this->requestContact->job_title,
                '• **Job Title:** ' . $this->requestContact->job_title,
            )
            ->lineIf(
                $this->requestContact->gym_size,
                '• **Gym Size:** ' .
                    $this->requestContact->gym_size .
                    ' members',
            )
            ->lineIf(
                $this->requestContact->preferred_contact_method,
                '• **Preferred Contact:** ' .
                    ucfirst($this->requestContact->preferred_contact_method),
            )
            ->lineIf(
                $this->requestContact->best_time_to_contact,
                '• **Best Time to Contact:** ' .
                    $this->requestContact->best_time_to_contact,
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
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): array
    {
        $message = "🔔 *New Demo Request*\n\n";
        $message .= "*Contact Details:*\n";
        $message .= "• Name: {$this->requestContact->name}\n";
        $message .= "• Email: {$this->requestContact->email}\n";
        $message .= "• Phone: {$this->requestContact->phone}\n";

        if ($this->requestContact->company_name) {
            $message .= "• Company: {$this->requestContact->company_name}\n";
        }

        if ($this->requestContact->gym_size) {
            $message .= "• Gym Size: {$this->requestContact->gym_size} members\n";
        }

        if ($this->requestContact->preferred_contact_method) {
            $message .=
                '• Preferred Contact: ' .
                ucfirst($this->requestContact->preferred_contact_method) .
                "\n";
        }

        if ($this->requestContact->message) {
            $message .= "\n*Message:*\n{$this->requestContact->message}\n";
        }

        $message .=
            "\n⏰ Submitted: " .
            $this->requestContact->created_at->format('M d, Y H:i A');
        $message .= "\n\n_Please respond to this request as soon as possible._";

        return [
            'body' => $message,
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
            'request_contact_id' => $this->requestContact->id,
            'name' => $this->requestContact->name,
            'email' => $this->requestContact->email,
            'phone' => $this->requestContact->phone,
            'company_name' => $this->requestContact->company_name,
            'created_at' => $this->requestContact->created_at->toISOString(),
        ];
    }
}
