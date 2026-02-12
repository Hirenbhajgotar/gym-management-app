<?php

namespace App\Notifications;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpenseStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Expense $expense,
        public string $action // 'approved', 'rejected', 'paid'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gym = $this->expense->gym;
        $actionBy = $this->expense->approvedBy;

        $mail = (new MailMessage)
            ->greeting("Hello {$notifiable->name}!");

        return match ($this->action) {
            'approved' => $mail
                ->subject("Expense Approved: {$this->expense->title}")
                ->line("Your expense has been approved!")
                ->line("**Expense Details:**")
                ->line("• Title: {$this->expense->title}")
                ->line("• Amount: " . number_format($this->expense->amount, 2) . " {$this->expense->currency}")
                ->line("• Approved By: " . ($actionBy?->name ?? 'System'))
                ->action('View Expense', url("/admin/{$gym->slug}/expenses/{$this->expense->id}"))
                ->line('The expense will now be processed for payment.'),

            'rejected' => $mail
                ->subject("Expense Rejected: {$this->expense->title}")
                ->line("Your expense has been rejected.")
                ->line("**Expense Details:**")
                ->line("• Title: {$this->expense->title}")
                ->line("• Amount: " . number_format($this->expense->amount, 2) . " {$this->expense->currency}")
                ->line("• Rejected By: " . ($actionBy?->name ?? 'System'))
                ->when($this->expense->rejection_reason, function ($mail) {
                    return $mail->line("• Reason: {$this->expense->rejection_reason}");
                })
                ->action('View Expense', url("/admin/{$gym->slug}/expenses/{$this->expense->id}"))
                ->line('Please review the feedback and resubmit if needed.'),

            'paid' => $mail
                ->subject("Expense Paid: {$this->expense->title}")
                ->line("Your expense has been marked as paid!")
                ->line("**Expense Details:**")
                ->line("• Title: {$this->expense->title}")
                ->line("• Amount: " . number_format($this->expense->amount, 2) . " {$this->expense->currency}")
                ->line("• Paid At: " . $this->expense->paid_at->format('d M Y H:i'))
                ->action('View Expense', url("/admin/{$gym->slug}/expenses/{$this->expense->id}")),

            default => $mail->line("Expense status updated to: {$this->expense->status->getLabel()}"),
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'expense_id' => $this->expense->id,
            'title' => $this->expense->title,
            'amount' => $this->expense->amount,
            'currency' => $this->expense->currency,
            'action' => $this->action,
            'action_by' => $this->expense->approvedBy?->name,
            'message' => match ($this->action) {
                'approved' => "Your expense '{$this->expense->title}' has been approved",
                'rejected' => "Your expense '{$this->expense->title}' has been rejected",
                'paid' => "Your expense '{$this->expense->title}' has been paid",
                default => "Expense status updated",
            },
        ];
    }
}
