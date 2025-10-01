<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Services\NotificationChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BirthdayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $member;

    public function __construct($member)
    {
        $this->member = $member;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // return ['mail'];
        return NotificationChannelResolver::getChannelsForGym(
            $this->member->gym,
            'birthday'
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Happy Birthday, ' . $this->member->name . '!')
            ->greeting('Hello ' . $this->member->name . ',')
            ->line('We at ' . $this->member->gym->name . ' wish you a very Happy Birthday!')
            ->line('May you have a fantastic year ahead filled with health and fitness.')
            ->line('Thank you for being a valued member!');
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): ?array
    {
        // Ensure member has a phone number
        $phoneNumber = $this->member->phone ?? null;

        if (!$phoneNumber) {
            Log::warning('Member has no phone number for WhatsApp', [
                'member_id' => $this->member->id
            ]);
            return null;
        }

        // Format phone number (ensure it has country code)
        $phoneNumber = ParserHelper::formatPhoneNumberWithCountryCode($phoneNumber);

        return [
            // 'to' => $phoneNumber,
            'to' => '+919265098227', // Test number
            'body' => "Happy Birthday {$this->member->name},\n\n"
                . "We at {$this->member->gym->name} wish you a very Happy Birthday! 🎉\n\n"
                . "May you have a fantastic year ahead filled with health and fitness.\n\n"
                . "Thank you for being a valued member!"
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
            'message' => 'Happy Birthday, ' . $this->member?->name . '! From ' . $this->member?->gym?->name,
        ];
    }
}
