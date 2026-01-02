<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Models\FollowUp;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsAppTemplateManager;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FollowUpReminderNotification extends Notification implements HasGymContext, ShouldQueue
{
    use Queueable;

    private readonly Gym $gym;

    private readonly Lead $lead;

    private readonly User $assignedStaff;

    /**
     * Create a new notification instance.
     */
    public function __construct(public FollowUp $followUp)
    {
        $this->onQueue('notifications');
        $this->gym = $followUp->gym;
        $this->lead = $followUp->lead;
        $this->assignedStaff = $followUp->user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // return ['mail', 'database'];
        return NotificationChannelResolver::getChannelsForGym(
            $this->gym,
            'followup_reminders',
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Follow-up Reminder: ' . $this->lead->name)
            ->greeting("Hello {$notifiable->name},")
            ->line('This is a reminder for your upcoming follow-up.')
            ->line('Lead: ' . $this->lead->name)
            ->line(
                'Scheduled Date: ' .
                    $this->followUp->scheduled_date->format('M d, Y H:i'),
            )
            ->line('Type: ' . $this->followUp->type->getLabel())
            ->line('Notes: ' . $this->followUp->notes)
            ->action(
                'View Follow-up',
                url('/admin/leads/' . $this->followUp->lead_id . '/edit'),
            )
            ->line('Please make sure to complete this follow-up on time.');
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

            [$fromNumber, $templateSid] = $config;

            return [
                'to' => $phoneNumber,
                'from' => $fromNumber,
                'template_sid' => $templateSid,
                'template_variables' => [
                    '1' => $this->lead->name,
                    '2' => $this->followUp->scheduled_date->format('M d, Y H:i'),
                    '3' => $this->followUp->type->getLabel(),
                    '4' => $this->followUp->notes,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Failed to prepare WhatsApp message', [
                'error' => $e->getMessage(),
                'lead_id' => $this->lead->id,
            ]);

            return null;
        }
    }

    private function getSandboxWhatsAppPayload(): array
    {
        // Sandbox mode uses a fixed test number
        return [
            'to' => '+919265098227', // Consider moving this to config if used frequently
            'body' => "Follow-up Reminder: {$this->lead->name}\n" .
            'Scheduled Date: ' . $this->followUp->scheduled_date->format('M d, Y H:i') . "\n" .
            'Type: ' . $this->followUp->type->getLabel() . "\n" .
            'Notes: ' . $this->followUp->notes,
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

        if (! $notificationSetting->whatsapp_business_number) {
            Log::error('Notification business number not found for gym', ['gym_id' => $this->gym->id]);

            return null;
        }

        $template = $this->getWhatsAppTemplate();
        if (! $template) {
            return null;
        }

        $templateSid = $template->template_sid ?? config('services.twilio.lead_followup_reminder_template_sid');

        return [$notificationSetting->whatsapp_business_number, $templateSid];
    }

    private function getWhatsAppTemplate(): ?object
    {
        $templateManager = new WhatsAppTemplateManager($this->gym->id);
        $template = $templateManager->getTemplate('birthday_lead_followup_reminder_templategreeting');

        if (! $template) {
            Log::error('lead_followup_reminder_template template not found', ['gym_id' => $this->gym->id]);

            return null;
        }

        return $template;
    }

    private function getRecipientPhoneNumber(): ?string
    {
        // Ensure lead has a phone number
        $phoneNumber = $this->assignedStaff?->phone;
        if (! $phoneNumber) {
            Log::warning('Assigned staff has no phone number for WhatsApp', [
                'assigned_staff_id' => $this->assignedStaff?->id,
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
            'follow_up_id' => $this->followUp->id,
            'lead_id' => $this->followUp->lead_id,
            'lead_name' => $this->lead->name,
            'scheduled_date' => $this->followUp->scheduled_date->toDateTimeString(),
            'type' => $this->followUp->type->value,
            'notes' => $this->followUp->notes,
            'message' => 'Follow-up reminder for ' . $this->lead->name,
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->followUp->gym_id;
    }
}
