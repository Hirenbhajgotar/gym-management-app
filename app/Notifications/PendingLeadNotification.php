<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Helpers\ParserHelper;
use App\Models\Lead;
use App\Models\User;
use App\Services\WhatsAppVariableResolver;
use Exception;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PendingLeadNotification extends BaseTenantNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Lead $lead)
    {
        $this->gym = $lead->gym;
    }

    protected function getNotificationType(): string
    {
        return NotificationType::LEAD_REMINDER->value;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Pending Lead Reminder: {$this->lead->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line(
                'This is a reminder about a pending lead that requires attention.',
            )
            ->line('Lead: ' . $this->lead->name)
            ->line('Email: ' . $this->lead->email)
            ->line('Phone: ' . $this->lead->phone)
            ->line('Status: ' . $this->lead->status->getLabel())
            ->line('Created: ' . $this->lead->created_at->format('M d, Y'))
            ->action(
                'View Lead',
                url("/admin/{$this->gym->slug}/leads/{$this->lead->id}"),
            )
            ->line('Please follow up with this lead as soon as possible.');
    }

    /**
     * Get the WhatsApp representation of the notification.
     *
     * @param  User  $notifiable
     */
    public function toWhatsapp(object $notifiable): ?array
    {
        if (config('services.twilio.use_sandbox')) {
            return $this->getSandboxWhatsAppPayload();
        }

        // Ensure member has a phone number
        $phoneNumber = $this->getRecipientPhoneNumber($notifiable);
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

            // Use contentSid for production
            return [
                'to' => $phoneNumber,
                'from' => $config['from_number'],
                'template_sid' => $template->twilio_template_sid,
                'template_variables' => resolve(WhatsAppVariableResolver::class)->resolve($template, [
                    'custom' => [
                        '1' => $notifiable->name ?? 'Staff',
                        '2' => $this->lead->name ?? '',
                        '3' => $this->lead->phone ?? '',
                        '4' => Str::limit($this->lead->notes ?? $this->lead->status->getLabel() ?? '', 100),
                    ],
                ]),
            ];
        } catch (Exception $e) {
            Log::error('Failed to prepare WhatsApp message', [
                'error' => $e->getMessage(),
                'lead_id' => $this->lead->id,
                'gym_id' => $this->gym->id,
                'notifiable_id' => $notifiable->id ?? null,
            ]);

            return null;
        }
    }

    private function getSandboxWhatsAppPayload(): array
    {
        $message =
            'Pending Lead Reminder: ' .
            $this->lead->name .
            "\n" .
            'Email: ' .
            $this->lead->email .
            "\n" .
            'Phone: ' .
            $this->lead->phone .
            "\n" .
            'Status: ' .
            $this->lead->status->getLabel() .
            "\n" .
            'Created: ' .
            $this->lead->created_at->format('M d, Y');

        $to = config()->string('services.twilio.sandbox_number');

        if (blank($to)) {
            Log::error('Twilio sandbox number is not configured in .env');
            throw new Exception('Twilio sandbox number is not configured');
        }

        return [
            'to' => $to,
            'body' => $message,
        ];
    }

    private function getRecipientPhoneNumber(object $notifiable): ?string
    {
        /** @var User $notifiable */
        $phoneNumber = $notifiable->phone ?? null;
        if (! $phoneNumber) {
            Log::warning('Owner has no phone number for WhatsApp', [
                'owner_id' => $notifiable->id ?? null,
            ]);

            return null;
        }

        // Format phone number (ensure it has country code)
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
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name,
            'lead_email' => $this->lead->email,
            'lead_phone' => $this->lead->phone,
            'lead_status' => $this->lead->status->value,
            'created_at' => $this->lead->created_at->toDateTimeString(),
            'message' => 'Pending lead reminder for ' . $this->lead->name,
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->lead->gym_id;
    }
}
