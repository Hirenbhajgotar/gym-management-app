<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsAppTemplateManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BirthdayNotification extends Notification implements HasGymContext, ShouldQueue
{
    use Queueable;

    public function __construct(
        public $member,
        public ?string $senderEmail = null,
        public ?string $senderPhone = null,
    ) {}

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
            'birthday_notifications',
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Happy Birthday, ' . $this->member->name . '!')
            ->greeting('Hello ' . $this->member->name . ',')
            ->line(
                'We at ' .
                    $this->member->gym->name .
                    ' wish you a very Happy Birthday!',
            )
            ->line(
                'May you have a fantastic year ahead filled with health and fitness.',
            )
            ->line('Thank you for being a valued member!')
            ->salutation('Regards, ' . $this->member->gym->name);

        if ($this->senderEmail) {
            $mail->from($this->senderEmail, $this->member->gym->name);
        }

        return $mail;
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
        $phoneNumber = ParserHelper::formatPhoneNumberWithCountryCode(
            $phoneNumber,
        );

        // Check if we're using sandbox or production
        $useSandbox = config('services.twilio.use_sandbox');

        if ($useSandbox) {
            return [
                // 'to' => $phoneNumber,
                'to' => '+919265098227', // Test number
                'body' => "Happy Birthday {$this->member->name},\n\n" .
                    "We at {$this->member->gym->name} wish you a very Happy Birthday! 🎉\n\n" .
                    "May you have a fantastic year ahead filled with health and fitness.\n\n" .
                    'Thank you for being a valued member!',
            ];
        }

        // PRODUCTION MODE: Use approved templates
        try {
            $templateManager = new WhatsAppTemplateManager(
                $this->member->gym_id,
            );
            $template = $templateManager->getTemplate('birthday_greeting');

            if (! $template) {
                Log::error('Birthday template not found', [
                    'gym_id' => $this->member->gym_id,
                    'member_id' => $this->member->id,
                ]);

                return null;
            }

            return [
                'to' => $phoneNumber,
                'from' => $this->senderPhone,
                'template_sid' => $template?->template_sid ??
                    config('services.twilio.birthday_greeting_template_sid'),
                'template_variables' => [
                    '1' => $this->member->name,
                    '2' => $this->member->gym->name,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to prepare WhatsApp message', [
                'error' => $e->getMessage(),
                'member_id' => $this->member->id,
            ]);

            return null;
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Happy Birthday, ' .
                $this->member?->name .
                '! From ' .
                $this->member?->gym?->name,
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->member->gym_id;
    }
}
