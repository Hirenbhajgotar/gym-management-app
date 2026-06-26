<?php

namespace App\Notifications;

use App\Services\WhatsAppVariableResolver;
use App\Enums\NotificationType;
use App\Helpers\ParserHelper;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Exception;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class FollowUpReminderNotification extends BaseTenantNotification
{
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

    protected function getNotificationType(): string
    {
        return NotificationType::FOLLOWUP_REMINDER->value;
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
                url("/admin/{$this->gym?->slug}/leads/{$this->followUp->lead_id}"),
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

            $template = $this->getWhatsAppTemplate($this->getNotificationType());
            if (! $template) {
                return null;
            }

            return [
                'to' => $phoneNumber,
                'from' => $config['from_number'],
                'template_sid' => $template->twilio_template_sid,
                'template_variables' => resolve(WhatsAppVariableResolver::class)->resolve($template, [
                    'custom' => [
                        '1' => $notifiable->name ?? 'Staff',
                        '2' => $this->lead->name ?? '',
                        '3' => $this->followUp->scheduled_date->format('M d, Y H:i'),
                        '4' => $this->followUp->notes ?? '',
                    ],
                ]),
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
            'to' => config('services.twilio.sandbox_number', '+919265098227'),
            'body' => "Follow-up Reminder: {$this->lead->name}\n" .
            'Scheduled Date: ' . $this->followUp->scheduled_date->format('M d, Y H:i') . "\n" .
            'Type: ' . $this->followUp->type->getLabel() . "\n" .
            'Notes: ' . $this->followUp->notes,
        ];
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
