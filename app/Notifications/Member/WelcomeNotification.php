<?php

declare(strict_types=1);

namespace App\Notifications\Member;

use App\Helpers\ParserHelper;
use App\Models\Gym;
use App\Models\GymMember;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WelcomeNotification extends Notification implements HasGymContext
{
    use Queueable;

    private readonly Gym $gym;

    public function __construct(
        public readonly GymMember $member
    ) {
        $this->gym = $this->member->gym;
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return NotificationChannelResolver::getChannelsForGym(
            $this->gym,
            'welcome'
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $memberName = $this->member->name ?? 'Member';

        $mail = (new MailMessage)
            ->from($this->gym->email, $this->gym->name)
            ->subject("Welcome to {$this->gym->name}")
            ->greeting("Hello {$memberName},")
            ->line('Welcome to our gym! We are excited to have you on board.')
            ->line('Thank you for joining us!')
            ->salutation("Regards, {$this->gym->name}");

        // Attach invoice if exists on the latest payment
        $latestPayment = $this->member->latestPayment;
        if ($latestPayment && $latestPayment->invoice_path && \Illuminate\Support\Facades\Storage::exists($latestPayment->invoice_path)) {
            $mail->attach(\Illuminate\Support\Facades\Storage::path($latestPayment->invoice_path), [
                'as' => $latestPayment->invoice_number . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    public function toWhatsApp(object $notifiable): ?array
    {
        $phoneNumber = $this->member->phone;

        if (! $phoneNumber) {
            Log::warning('Member has no phone number for WhatsApp notification', [
                'member_id' => $this->member->id,
                'gym_id' => $this->gym->id,
            ]);

            return null;
        }

        $formattedPhone = ParserHelper::formatPhoneNumberWithCountryCode($phoneNumber);
        $memberName = $this->member->name ?? 'Member';

        return [
            'to' => $formattedPhone,
            'body' => <<<MESSAGE
            Hello {$memberName},

            Welcome to {$this->gym->name}! 🎉

            We are excited to have you on board.

            Thank you for joining us!
            MESSAGE,
        ];
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
