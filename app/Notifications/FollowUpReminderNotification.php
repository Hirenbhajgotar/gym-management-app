<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Models\FollowUp;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsAppTemplateManager;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FollowUpReminderNotification extends Notification implements ShouldQueue, HasGymContext
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public FollowUp $followUp)
    {
        $this->onQueue("notifications");
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
            $this->followUp->gym,
            "followup_reminders",
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Follow-up Reminder: " . $this->followUp->lead->name)
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a reminder for your upcoming follow-up.")
            ->line("Lead: " . $this->followUp->lead->name)
            ->line(
                "Scheduled Date: " .
                    $this->followUp->scheduled_date->format("M d, Y H:i"),
            )
            ->line("Type: " . $this->followUp->type->getLabel())
            ->line("Notes: " . $this->followUp->notes)
            ->action(
                "View Follow-up",
                url("/admin/leads/" . $this->followUp->lead->id . "/edit"),
            )
            ->line("Please make sure to complete this follow-up on time.");
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsapp(object $notifiable): ?array
    {
        $use_sandbox = config("services.twilio.use_sandbox", true);
        $phoneNumber = $this->followUp?->gym?->owner()?->phone;

        if (!$phoneNumber) {
            Log::warning("Member has no phone number for WhatsApp", [
                "owner_id" => $this->followUp?->gym?->owner()?->id,
            ]);

            return null;
        }

        // Format phone number (ensure it has country code)
        $phoneNumber = ParserHelper::formatPhoneNumberWithCountryCode(
            $phoneNumber,
        );

        $message =
            "Follow-up Reminder: " .
            $this->followUp->lead->name .
            "\n" .
            "Scheduled Date: " .
            $this->followUp->scheduled_date->format("M d, Y H:i") .
            "\n" .
            "Type: " .
            $this->followUp->type->getLabel() .
            "\n" .
            "Notes: " .
            $this->followUp->notes;

        if ($use_sandbox) {
            // Use sendbox message for testing
            return [
                "to" => "+919265098227",
                "body" => $message,
            ];
        }

        try {
            $templateManager = new WhatsAppTemplateManager(
                $this->followUp?->gym?->id,
            );
            $template = $templateManager->getTemplate(
                "lead_followup_reminder_template",
            );

            if (!$template) {
                Log::error(
                    "lead_followup_reminder_template template not found",
                    [
                        "gym_id" => $this->followUp?->gym?->id,
                    ],
                );

                return null;
            }

            // Use contentSid for production
            return [
                "to" => $phoneNumber,
                "template_sid" =>
                $template?->template_sid ??
                    config(
                        "services.twilio.lead_followup_reminder_template_sid",
                    ),
                "template_variables" => [
                    "1" => $this->followUp->lead->name,
                    "2" => $this->followUp->scheduled_date->format(
                        "M d, Y H:i",
                    ),
                    "3" => $this->followUp->type->getLabel(),
                    "4" => $this->followUp->notes,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Failed to prepare WhatsApp message", [
                "error" => $e->getMessage(),
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
            "follow_up_id" => $this->followUp->id,
            "lead_id" => $this->followUp->lead->id,
            "lead_name" => $this->followUp->lead->name,
            "scheduled_date" => $this->followUp->scheduled_date->toDateTimeString(),
            "type" => $this->followUp->type->value,
            "notes" => $this->followUp->notes,
            "message" =>
            "Follow-up reminder for " . $this->followUp->lead->name,
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->followUp->gym_id;
    }
}
