<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Models\GymMember;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsAppTemplateManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class MembershipExpiryNotification extends Notification implements ShouldQueue, HasGymContext
{
    use Queueable;

    public function __construct(
        public GymMember $member,
        public Carbon $expiresAt,
        public int $daysLeft,
    ) {
        // negative = days overdue
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return NotificationChannelResolver::getChannelsForGym(
            $this->member->gym,
            "membership_expiry_notifications",
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject =
            $this->daysLeft > 0
            ? 'Your Gym membership expires soon - let\'s keep the momentum!'
            : 'Your Gym membership has expired - but the grind doesn\'t have to stop';

        return new MailMessage()
            ->subject($subject)
            ->greeting("Hey {$this->member->name},")
            ->line(new HtmlString($this->emailBody()))
            // ->action('Renew in two clicks', route('membership.renew'))
            // ->line('Questions? Hit reply - a real human reads every message.')
            ->salutation(
                new HtmlString("Stay strong,<br>" . config("app.name")),
            );
    }

    private function emailBody(): string
    {
        if ($this->daysLeft > 0) {
            return match (true) {
                $this->daysLeft === 1
                => "Tomorrow your access badge turns into a pumpkin. Renew now so you can keep crushing PRs without missing a beat.",
                $this->daysLeft <= 7
                => "Only {$this->daysLeft} days left on your current membership expiration date. That's just enough time to fit in one more leg-day masterpiece.",
                default
                => "Your membership expires on {$this->expiresAt->format(
                    "D, M j",
                )} - lock in your next chapter today and we'll freeze your current price forever.",
            };
        }

        return "Your membership expired today - but your locker is still waiting for you. Renew your membership.";
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): ?array
    {
        // Check if we're using sandbox or production
        $useSandbox = config("services.twilio.use_sandbox");

        if ($useSandbox) {
            return [
                // 'to' => $phoneNumber,
                "to" => "+919265098227", // Test number
                "body" =>
                "Hey {$this->member->name},\n\n" .
                    "Your membership expires on {$this->expiresAt->format(
                        "D, M j",
                    )}, but your locker is still waiting for you. Renew your membership.",
            ];
        }

        // Ensure member has a phone number
        $phoneNumber = $this->member->phone ?? null;

        if (!$phoneNumber) {
            Log::warning("Member has no phone number for WhatsApp", [
                "member_id" => $this->member->id,
            ]);

            return null;
        }

        // Format phone number (ensure it has country code)
        $phoneNumber = ParserHelper::formatPhoneNumberWithCountryCode(
            $phoneNumber,
        );

        try {
            $templateManager = new WhatsAppTemplateManager(
                $this->member->gym_id,
            );
            $template = $templateManager->getTemplate(
                "membership_expiry_template",
            );

            if (!$template) {
                Log::error("membership_expiry_template template not found", [
                    "gym_id" => $this->member->gym_id,
                    "member_id" => $this->member->id,
                ]);

                return null;
            }

            return [
                "to" => $phoneNumber,
                "template_sid" =>
                $template?->template_sid ??
                    config("services.twilio.membership_expiry_template_sid"),
                "template_variables" => [
                    "1" => $this->member->name,
                    "2" => $this->expiresAt->format("D, M j"),
                ],
            ];
        } catch (Exception $e) {
            Log::error("Failed to prepare WhatsApp message", [
                "error" => $e->getMessage(),
                "member_id" => $this->member->id,
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
            "member_id" => $this->member->id,
            "days_left" => $this->daysLeft,
            "expires_at" => $this->expiresAt->toDateString(),
            "message" =>
            $this->daysLeft > 0
                ? "Your membership expires soon - renew to stay active."
                : "Your membership has expired - renew now to re-activate.",
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->member->gym_id;
    }
}
