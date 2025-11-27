<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Models\Gym;
use App\Models\GymWhatsAppBalance;
use App\Notifications\Contracts\HasGymContext;
use App\Services\WhatsAppTemplateManager;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LowWhatsAppBalanceNotification extends Notification implements ShouldQueue, HasGymContext
{
    use Queueable;

    public Gym $gym;

    public GymWhatsAppBalance $balance;

    public int $remainingMessages;

    /**
     * Create a new notification instance.
     */
    public function __construct(Gym $gym, GymWhatsAppBalance $balance, int $remainingMessages)
    {
        $this->gym = $gym;
        $this->balance = $balance;
        $this->remainingMessages = $remainingMessages;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', WhatsAppChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low WhatsApp Message Balance Alert - ' . $this->gym->name)
            ->greeting("Hello {$notifiable->name} ,")
            ->line('Your WhatsApp message balance for **' . $this->gym->name . '** is running low.')
            ->line("**Remaining Messages:** {$this->remainingMessages}")
            ->line("You have {$this->remainingMessages} WhatsApp message(s) remaining. Please purchase more messages to continue sending notifications to your members.")
            ->action('Purchase Messages', route('filament.admin.pages.settings', ['tenant' => $this->gym->slug, 'tab' => 'whatsapp']))
            ->line('Thank you for using our service!');
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): ?array
    {
        $useSandbox = config("services.twilio.use_sandbox", true);

        $this->gym->load('users');
        $owner = $this->gym?->owner();
        $phoneNumber = $owner?->phone;

        if (!$phoneNumber) {
            Log::warning("Gym owner has no phone number for WhatsApp", [
                "owner_id" => $owner?->id,
            ]);

            return null;
        }

        // Format phone number (ensure it has country code)
        $phoneNumber = ParserHelper::formatPhoneNumberWithCountryCode(
            $phoneNumber,
        );

        $message =
            "Low WhatsApp Balance Alert for " .
            $this->gym->name .
            ".\n" .
            "You have " .
            $this->remainingMessages .
            " message(s) remaining.\n" .
            "Please purchase more messages to continue sending notifications to your members.";

        if ($useSandbox) {
            // Use sandbox message for testing
            return [
                "to" => "+919265098227",
                "body" => $message,
            ];
        }

        try {
            $templateManager = new WhatsAppTemplateManager(
                $this->gym?->id,
            );
            $template = $templateManager->getTemplate(
                "low_whatsapp_message_balance_template",
            );

            if (!$template) {
                Log::error(
                    "low_whatsapp_message_balance_template template not found",
                    [
                        "gym_id" => $this->gym?->id,
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
                        "services.twilio.low_whatsapp_message_balance_template_sid",
                    ),
                "template_variables" => [
                    "1" => $this->gym?->name,
                    "2" => $this->remainingMessages,
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
            'title' => 'Low WhatsApp Message Balance',
            'message' => "Your gym {$this->gym->name} has only {$this->remainingMessages} WhatsApp message(s) remaining.",
            'gym_id' => $this->gym->id,
            'remaining_messages' => $this->remainingMessages,
            'action_url' => '/admin/settings',
            'action_text' => 'Purchase Messages',
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->gym->id;
    }
}
