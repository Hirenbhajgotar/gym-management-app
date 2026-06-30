<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpensePendingApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Expense $expense
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gym = $this->expense->gym;
        $submitter = $this->expense->user;

        return (new MailMessage())
            ->subject("Expense Pending Approval: {$this->expense->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line('A new expense has been submitted and requires your approval.')
            ->line('**Expense Details:**')
            ->line("• Title: {$this->expense->title}")
            ->line('• Amount: ' . number_format($this->expense->amount, 2) . " {$this->expense->currency}")
            ->line('• Category: ' . ($this->expense->category?->name ?? 'Uncategorized'))
            ->line('• Date: ' . $this->expense->expense_date->format('d M Y'))
            ->line("• Submitted By: {$submitter->name}")
            ->action('Review Expense', url("/admin/{$gym->slug}/expenses/{$this->expense->id}"))
            ->line('Please review and take appropriate action.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'expense_id' => $this->expense->id,
            'title' => $this->expense->title,
            'amount' => $this->expense->amount,
            'currency' => $this->expense->currency,
            'submitted_by' => $this->expense->user->name,
            'category' => $this->expense->category?->name,
            'message' => "New expense '{$this->expense->title}' pending approval",
        ];
    }
}
