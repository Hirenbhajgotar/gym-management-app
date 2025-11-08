<?php

namespace App\Notifications\Member;

use App\Helpers\ParserHelper;
use App\Models\GymMember;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WelcomeNotification extends Notification implements HasGymContext
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public GymMember $member)
    {
        $this->onQueue('notifications');
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
            'welcome'
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to ' . $this->member->gym->name)
            ->greeting('Hello ' . $this->member->name . ',')
            ->line('Welcome to our gym! We are excited to have you on board.')
            ->line('Thank you for joining us!');
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): ?array
    {
        // Ensure member has a phone number
        $phoneNumber = $this->member->phone ?? null;

        if (! $phoneNumber) {
            Log::warning('Member has no phone number for WhatsApp', [
                'member_id' => $this->member->id,
            ]);

            return null;
        }

        // Format phone number (ensure it has country code)
        $phoneNumber = ParserHelper::formatPhoneNumberWithCountryCode($phoneNumber);

        return [
            // 'to' => $phoneNumber,
            'to' => '+919265098227', // Test number
            'body' => "Hello {$this->member->name},\n\n"
                . "Welcome to {$this->member->gym->name}! 🎉\n\n"
                . "We are excited to have you on board.\n\n"
                . 'Thank you for joining us!',
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
            //
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->member->gym_id;
    }
}
