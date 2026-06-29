<?php

declare(strict_types=1);

namespace App\Notifications\Member;

use App\Enums\NotificationType;
use App\Helpers\ParserHelper;
use App\Models\GymMember;
use App\Notifications\BaseTenantNotification;
use App\Services\WhatsAppVariableResolver;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WelcomeNotification extends BaseTenantNotification
{
    use Queueable;

    public function __construct(
        public readonly GymMember $member
    ) {
        $this->gym = $this->member->gym;
        $this->onQueue('notifications');
    }

    protected function getNotificationType(): string
    {
        return NotificationType::WELCOME->value;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $memberName = $this->member->name ?? 'Member';

        $mail = (new MailMessage())
            ->from($this->gym->owner_email, $this->gym->name)
            ->subject("Welcome to {$this->gym->name}")
            ->greeting("Hello {$memberName},")
            ->line('Welcome to our gym! We are excited to have you on board.')
            ->line('Thank you for joining us!')
            ->salutation("Regards, {$this->gym->name}");

        // Attach invoice if exists on the latest payment
        $latestPayment = $this->member->latestPayment;
        if ($latestPayment && $latestPayment->invoice_path && Storage::disk('local')->exists($latestPayment->invoice_path)) {
            $mail->attach(Storage::disk('local')->path($latestPayment->invoice_path), [
                'as' => $latestPayment->invoice_number . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

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
                'template_variables' => resolve(WhatsAppVariableResolver::class)->resolve($template, ['member' => $this->member]),
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
        $memberName = $this->member->name ?? 'Member';

        return [
            'to' => config('services.twilio.sandbox_number', '+919265098227'),
            'body' => "Hello {$memberName},\n\nWelcome to {$this->gym->name}! 🎉\n\nWe are excited to have you on board.",
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
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'member_name' => $this->member->name,
            'gym_name' => $this->gym->name,
        ];
    }

    public function getGymId(mixed $notifiable): ?int
    {
        return $this->gym->id;
    }
}
