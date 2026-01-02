<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PendingLeadNotification extends Notification implements HasGymContext, ShouldQueue
{
    use Queueable;

    private readonly Gym $gym;

    private readonly User $owner;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Lead $lead)
    {
        $this->gym = $lead->gym;
        $this->owner = $this->gym->owner();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return NotificationChannelResolver::getChannelsForGym(
            $this->gym,
            'lead_reminders_notifications',
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
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
                url('/admin/leads/' . $this->lead->id . '/edit'),
            )
            ->line('Please follow up with this lead as soon as possible.');
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsapp(object $notifiable): ?array
    {
        if (config('services.twilio.use_sandbox')) {
            return $this->getSandboxWhatsAppPayload();
        }

        // Ensure member has a phone number
        $phoneNumber = $this->getRecipientPhoneNumber();
        if (! $phoneNumber) {
            return null;
        }

        try {
            $config = $this->getGymWhatsAppConfig();
            if (! $config) {
                return null;
            }

            [$templateSid] = $config;

            // Use contentSid for production
            return [
                'to' => $phoneNumber,
                'template_sid' => $templateSid,
                'template_variables' => [
                    '1' => $this->lead->name,
                    '2' => $this->lead->email,
                    '3' => $this->lead->phone,
                    '4' => $this->lead->status->getLabel(),
                    '5' => $this->lead->created_at->format('M d, Y'),
                ],
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

        // Sandbox mode uses a fixed test number
        return [
            'to' => '+919265098227', // Consider moving this to config if used frequently
            'body' => $message,
        ];
    }

    /**
     * @return array{string, string}|null Returns [fromNumber, templateSid] or null on failure
     */
    private function getGymWhatsAppConfig(): ?array
    {
        $notificationSetting = $this->gym?->notificationSetting;

        if (! $notificationSetting) {
            Log::error('Notification setting not found for gym', ['gym_id' => $this->gym->id]);

            return null;
        }

        if (! $notificationSetting->isAccountActive() || ! $notificationSetting->isAccountVerified()) {
            Log::error('Notification account is not active or verified', ['gym_id' => $this->gym->id]);

            return null;
        }

        $templateSid = config('services.twilio.pending_lead_reminder_template_sid');

        return [$templateSid];
    }

    private function getRecipientPhoneNumber(): ?string
    {
        // Ensure lead has a phone number
        $phoneNumber = $this->owner?->phone;
        if (! $phoneNumber) {
            Log::warning('Owner has no phone number for WhatsApp', [
                'owner_id' => $this->owner?->id,
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
