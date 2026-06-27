<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Helpers\ParserHelper;
use App\Models\GymMember;
use App\Services\WhatsAppVariableResolver;
use Exception;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class PromotionalNotification extends BaseTenantNotification
{
    public function __construct(
        public GymMember $member,
        public string $offerDetails,
        public string $offerExpiry,
    ) {
        $this->gym = $member->gym;
    }

    protected function getNotificationType(): string
    {
        return NotificationType::PROMOTIONAL->value;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Special Offer from ' . $this->gym->name . '!')
            ->greeting('Hello ' . $this->member->name . ',')
            ->line('We have a special offer just for you:')
            ->line($this->offerDetails)
            ->line('Hurry! This offer is valid until ' . $this->offerExpiry . '.')
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

        $phoneNumber = $this->getRecipientPhoneNumber();
        if (! $phoneNumber) {
            return null;
        }

        try {
            $config = $this->getGymWhatsAppConfig();
            if (! $config) {
                return null;
            }

            $template = $this->getWhatsAppTemplate($this->getNotificationType());
            if (! $template) {
                return null;
            }

            return [
                'to' => $phoneNumber,
                'from' => $config['from_number'],
                'template_sid' => $template->twilio_template_sid,
                'template_variables' => resolve(WhatsAppVariableResolver::class)->resolve($template, [
                    'member' => $this->member,
                    'custom' => [
                        '3' => $this->offerDetails,
                        '4' => $this->offerExpiry,
                    ],
                ]),
            ];
        } catch (Exception $e) {
            Log::error('Failed to prepare WhatsApp message', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function getSandboxWhatsAppPayload(): array
    {
        return [
            'to' => config('services.twilio.sandbox_number', '+919265098227'),
            'body' => "Sandbox Message: Promotional Offer for {$this->member->name}",
        ];
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
}
