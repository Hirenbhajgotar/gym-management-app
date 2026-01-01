<?php

namespace App\Notifications;

use App\Helpers\ParserHelper;
use App\Models\Gym;
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

class MembershipExpiryNotification extends Notification implements HasGymContext, ShouldQueue
{
    use Queueable;

    private readonly Gym $gym;

    public function __construct(
        public GymMember $member,
        public Carbon $expiresAt,
        public int $daysLeft,
    ) {
        // negative = days overdue
        $this->gym = $this->member->gym;
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
            'membership_expiry_notifications',
        );
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->daysLeft > 0
            ? "Your Gym membership expires soon - let's keep the momentum!"
            : "Your Gym membership has expired - but the grind doesn't have to stop";

        return (new MailMessage)
            ->from($this->member->gym->owner_email, $this->member->gym->name)
            ->subject($subject)
            ->greeting("Hey {$this->member->name},")
            ->line(new HtmlString($this->emailBody()))
            ->salutation(new HtmlString('Stay strong,<br>' . $this->member->gym->name));
    }

    private function emailBody(): string
    {
        if ($this->daysLeft <= 0) {
            return 'Your membership expired today - but your locker is still waiting for you. Renew your membership.';
        }

        return match (true) {
            $this->daysLeft === 1 => 'Tomorrow your access badge turns into a pumpkin. Renew now so you can keep crushing PRs without missing a beat.',
            $this->daysLeft <= 7 => "Only {$this->daysLeft} days left on your current membership expiration date. That's just enough time to fit in one more leg-day masterpiece.",
            default => "Your membership expires on {$this->expiresAt->format('D, M j')} - lock in your next chapter today and we'll freeze your current price forever.",
        };
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

            [$fromNumber, $templateSid] = $config;

            return [
                'to' => $phoneNumber,
                'from' => $fromNumber,
                'template_sid' => $templateSid,
                'template_variables' => [
                    '1' => $this->member->name,
                    '2' => $this->expiresAt->format('D, M j'),
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
            'body' => "Hey {$this->member->name},\n\n" .
                "Your membership expires on {$this->expiresAt->format('D, M j')}, but your locker is still waiting for you. Renew your membership.",
        ];
    }

    private function getRecipientPhoneNumber(): ?string
    {
        $phoneNumber = $this->member->phone;

        if (! $phoneNumber) {
            Log::warning('Member has no phone number for WhatsApp', [
                'member_id' => $this->member->id,
            ]);

            return null;
        }

        return ParserHelper::formatPhoneNumberWithCountryCode($phoneNumber);
    }

    /**
     * @return array{string, string}|null Returns [fromNumber, templateSid] or null on failure
     */
    private function getGymWhatsAppConfig(): ?array
    {
        $notificationSetting = $this->gym->notificationSetting;

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

        $templateSid = $template->template_sid ?? config('services.twilio.membership_expiry_template_sid');

        return [$notificationSetting->whatsapp_business_number, $templateSid];
    }

    private function getWhatsAppTemplate(): ?object
    {
        $templateManager = new WhatsAppTemplateManager($this->member->gym_id);
        $template = $templateManager->getTemplate('membership_expiry_template');

        if (! $template) {
            Log::error('membership_expiry_template template not found', [
                'gym_id' => $this->member->gym_id,
                'member_id' => $this->member->id,
            ]);

            return null;
        }

        return $template;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'member_id' => $this->member->id,
            'days_left' => $this->daysLeft,
            'expires_at' => $this->expiresAt->toDateString(),
            'message' => $this->daysLeft > 0
                ? 'Your membership expires soon - renew to stay active.'
                : 'Your membership has expired - renew now to re-activate.',
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->member->gym_id;
    }
}
