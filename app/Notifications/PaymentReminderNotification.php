<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Helpers\ParserHelper;
use App\Models\GymMember;
use App\Services\WhatsAppVariableResolver;
use Exception;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class PaymentReminderNotification extends BaseTenantNotification
{
    public function __construct(
        public GymMember $member,
    ) {
        $this->gym = $member->gym;
    }

    protected function getNotificationType(): string
    {
        return NotificationType::PAYMENT_REMINDER->value;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->member->latestPayment ? $this->member->latestPayment->formatted_amount : 'your dues';

        $mail = (new MailMessage())
            ->subject('Payment Reminder - ' . $this->gym->name)
            ->greeting('Hello ' . $this->member->name . ',')
            ->line('This is a gentle reminder regarding your upcoming or pending payment of ' . $amount . '.')
            ->line('Please clear your dues to continue enjoying uninterrupted services at ' . $this->gym->name . '.')
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
                'meta_template_id' => $template->meta_template_id,
                'template_variables' => resolve(WhatsAppVariableResolver::class)->resolve($template, [
                    'member' => $this->member,
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
            'body' => "Sandbox Message: Payment Reminder for {$this->member->name}",
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
