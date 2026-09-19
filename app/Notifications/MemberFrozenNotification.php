<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GymMember;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberFrozenNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public GymMember $member,
        public bool $isFrozen,
        public ?string $reason = null,
        public ?Carbon $frozenUntil = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->isFrozen) {
            return $this->freezeMail();
        }

        return $this->unfreezeMail();
    }

    protected function freezeMail(): MailMessage
    {
        $mail = (new MailMessage())
            ->subject('Your Gym Membership Has Been Frozen')
            ->greeting('Hello ' . $this->member->name . '!');

        if ($this->frozenUntil) {
            $mail->line('Your gym membership has been temporarily frozen.')
                ->line('You will be able to access the gym again from: **' . $this->frozenUntil->format('F j, Y') . '**');
        } else {
            $mail->line('Your gym membership has been temporarily frozen.')
                ->line('You will be notified when your membership is unfrozen.');
        }

        if ($this->reason) {
            $mail->line('Reason: ' . $this->reason);
        }

        $mail->line('During this period, you will not be able to check in at the gym.')
            ->line('If you have any questions, please contact us.');

        return $mail;
    }

    protected function unfreezeMail(): MailMessage
    {
        return (new MailMessage())
            ->subject('Your Gym Membership Has Been Unfrozen')
            ->greeting('Hello ' . $this->member->name . '!')
            ->line('Great news! Your gym membership has been unfrozen.')
            ->line('You now have full access to the gym facilities.')
            ->line('We look forward to seeing you at the gym!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'member_id' => $this->member->id,
            'member_name' => $this->member->name,
            'is_frozen' => $this->isFrozen,
            'reason' => $this->reason,
            'frozen_until' => $this->frozenUntil?->toDateTimeString(),
        ];
    }
}
