<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\RequestContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class RequestDemoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new notification instance.
     */
    public function __construct(public RequestContact $requestContact) {}

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
        $safeName = str_replace(["\r", "\n"], '', $this->requestContact->name);

        return (new MailMessage())
            ->subject('New Demo Request - ' . $safeName)
            ->greeting('Hello Admin,')
            ->line('You have received a new demo request.')
            ->line('')
            ->line('**Contact Details:**')
            ->line('• **Name:** ' . $this->requestContact->name)
            ->line('• **Email:** ' . $this->requestContact->email)
            ->line('• **Phone:** ' . $this->requestContact->phone)
            ->lineIf(
                (bool) $this->requestContact->company_name,
                '• **Company:** ' . $this->requestContact->company_name,
            )
            ->lineIf(
                (bool) $this->requestContact->gym_size,
                '• **Gym Size:** ' .
                    $this->requestContact->gym_size .
                    ' members',
            )
            ->line('')
            ->lineIf((bool) $this->requestContact->message, '**Message:**')
            ->lineIf(
                (bool) $this->requestContact->message,
                (string) $this->requestContact->message,
            )
            ->line('')
            ->line('**Request Details:**')
            ->line(
                '• **Submitted:** ' .
                    $this->requestContact->created_at?->format('M d, Y H:i A'),
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
            'phone' => (string) $this->requestContact->phone,
            'company_name' => $this->requestContact->company_name,
            'created_at' => $this->requestContact->created_at?->toISOString(),
        ];
    }

    /**
     * Handle a permanent job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('RequestDemoNotification email dispatch failed permanently', [
            'request_contact_id' => $this->requestContact->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
