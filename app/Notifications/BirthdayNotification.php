<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Models\Gym;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsAppTemplateManager;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BirthdayNotification extends Notification implements HasGymContext, ShouldQueue
{
    use Queueable;

    private readonly Gym $gym;

    public function __construct(
        public $member,
    ) {
        $this->gym = $member->gym;
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
            $this->gym,
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
                    $this->gym->name .
                    ' wish you a very Happy Birthday!',
            )
            ->line(
                'May you have a fantastic year ahead filled with health and fitness.',
            )
            ->line('Thank you for being a valued member!')
            ->salutation('Regards, ' . $this->gym->name);

        if ($this->gym->ownerEmail) {
            $mail->from($this->gym->ownerEmail, $this->gym->name);
        }

        return $mail;
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): ?array
    {
        if (config('services.twilio.use_sandbox')) {
            return $this->getSandboxWhatsAppPayload();
        }

        // Ensure member has a phone number
        $phoneNumber = $this->getRecipientPhoneNumber();
        if (! $phoneNumber) {
            return null;
        }

        // PRODUCTION MODE: Use approved templates
        try {
            $config = $this->getGymWhatsAppConfig();
            if (! $config) {
                return null;
            }

            [$fromNumber, $templateSid] = $config;

            return [
                'to' => $phoneNumber,
                'from' => $fromNumber,
                'template_sid' => $templateSid,
                'template_variables' => [
                    '1' => $this->member->name,
                    '2' => $this->gym->name,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Failed to prepare WhatsApp message', [
                'error' => $e->getMessage(),
                'member_id' => $this->member->id,
            ]);

            return null;
        }
    }

    private function getSandboxWhatsAppPayload(): array
    {
        // Sandbox mode uses a fixed test number
        return [
            'to' => '+919265098227', // Consider moving this to config if used frequently
            'body' => "Happy Birthday {$this->member->name},\n\n" .
                    "We at {$this->gym->name} wish you a very Happy Birthday! 🎉\n\n" .
                    "May you have a fantastic year ahead filled with health and fitness.\n\n" .
                    'Thank you for being a valued member!',
        ];
    }

    /**
     * @return array{string, string}|null Returns [fromNumber, templateSid] or null on failure
     */
    private function getGymWhatsAppConfig(): ?array
    {
        $notificationSetting = $this->gym?->notificationSetting;

        if (! $notificationSetting) {
            Log::error('Notification setting not found for gym', ['gym_id' => $this->member->gym_id]);

            return null;
        }

        if (! $notificationSetting->isAccountActive() || ! $notificationSetting->isAccountVerified()) {
            Log::error('Notification account is not active or verified', ['gym_id' => $this->member->gym_id]);

            return null;
        }

        if (! $notificationSetting->whatsapp_business_number) {
            Log::error('Notification business number not found for gym', ['gym_id' => $this->member->gym_id]);

            return null;
        }

        $template = $this->getWhatsAppTemplate();
        if (! $template) {
            return null;
        }

        $templateSid = $template->template_sid ?? config('services.twilio.birthday_greeting_template_sid');

        return [$notificationSetting->whatsapp_business_number, $templateSid];
    }

    private function getWhatsAppTemplate(): ?object
    {
        $templateManager = new WhatsAppTemplateManager($this->member->gym_id);
        $template = $templateManager->getTemplate('birthday_greeting');

        if (! $template) {
            Log::error('birthday_greeting template not found', [
                'gym_id' => $this->member->gym_id,
                'member_id' => $this->member->id,
            ]);

            return null;
        }

        return $template;
    }

    private function getRecipientPhoneNumber(): ?string
    {
        $phoneNumber = $this->member->phone ?? null;

        if (! $phoneNumber) {
            Log::warning('Member has no phone number for WhatsApp', [
                'member_id' => $this->member->id,
            ]);

            return null;
        }

        return ParserHelper::formatPhoneNumberWithCountryCode($phoneNumber);
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
                $this->gym?->name,
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->member->gym_id;
    }
}
