<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberActionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $action;

    protected $member;

    protected $details;

    public function __construct($action, $member, $details = null)
    {
        $this->action = $action; // e.g., 'created', 'deleted', 'payment_created'
        $this->member = $member; // GymMember instance
        $this->details = $details; // Optional details (e.g., payment amount)

        try {
            // Validate member data
            if ($member && ! is_object($member)) {
                throw new \InvalidArgumentException('Member must be a valid model instance');
            }
            // Log notification creation
            \Illuminate\Support\Facades\Log::info('Creating member notification', [
                'action' => $action,
                'member_id' => $member?->id ?? 'null',
                'has_details' => ! is_null($details),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error creating member notification: ' . $e->getMessage(), [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $memberName = $this->member ? $this->member->name : 'Unknown';
        $memberEmail = $this->member ? $this->member->email : 'Not provided';
        $gymName = $this->member?->gym?->name ?? 'Unknown Gym';

        $subject = match ($this->action) {
            'created' => "New Member Registration at {$gymName}",
            'deleted' => "Member Removed from {$gymName}",
            'payment_created' => "New Payment Received at {$gymName}",
            default => "Gym Member {$this->action} Notification"
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}!")
            ->line($subject);

        $message->line("Member: {$memberName} ({$memberEmail})");

        $message->line("Action: {$this->action}")
            ->when($this->details, fn ($mail) => $mail->line("Details: {$this->details}"));

        $message->action(
            'View Members',
            url("/admin/{$this->member?->gym?->slug}/gym-members")
        )->line('Thank you for using our app!');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'action' => $this->action,
            'member_id' => $this->member ? $this->member->id : null,
            'member_name' => $this->member ? $this->member->name : 'Unknown',
            'member_email' => $this->member ? $this->member->email : 'Not provided',
            'details' => $this->details,
            'created_at' => now(),
        ];
    }
}
