<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\WhatsAppTemplate;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a WhatsApp template status changes to
 * PENDING, DISABLED, or other non-terminal states via webhook.
 */
class WhatsAppTemplateStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WhatsAppTemplate $template,
        public WhatsAppTemplateStatusEnum $newStatus,
        public string $oldStatus
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gymSlug = $this->template->gym?->slug ?? 'admin';
        $statusLabel = $this->newStatus->value;

        $subject = match ($this->newStatus) {
            WhatsAppTemplateStatusEnum::PENDING => '⏳ WhatsApp Template Pending Review — ' . $this->template->display_name,
            WhatsAppTemplateStatusEnum::DISABLED => '🚫 WhatsApp Template Disabled — ' . $this->template->display_name,
            default => '📋 WhatsApp Template Status Updated — ' . $this->template->display_name,
        };

        $body = match ($this->newStatus) {
            WhatsAppTemplateStatusEnum::PENDING => "Your WhatsApp template **\"{$this->template->display_name}\"** is now pending review by Meta. You will be notified once it is approved or rejected.",
            WhatsAppTemplateStatusEnum::DISABLED => "Your WhatsApp template **\"{$this->template->display_name}\"** has been disabled by Meta. You may need to review and resubmit it.",
            default => "Your WhatsApp template **\"{$this->template->display_name}\"** status has changed from **{$this->oldStatus}** to **{$statusLabel}**.",
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($body)
            ->line('**Template Details:**')
            ->line("- **Name:** {$this->template->display_name}")
            ->line('- **Category:** ' . ucfirst((string) $this->template->category))
            ->line("- **Status:** {$statusLabel}")
            ->action('View Template', route('filament.admin.resources.whats-app-templates.edit', ['tenant' => $gymSlug, 'record' => $this->template->id]))
            ->salutation('Best regards,');
    }

    public function toDatabase(object $notifiable): array
    {
        $gymSlug = $this->template->gym?->slug ?? 'admin';
        $notification = match ($this->newStatus) {
            WhatsAppTemplateStatusEnum::PENDING => FilamentNotification::make()
                ->info()
                ->icon('heroicon-o-clock')
                ->title('Template Pending: ' . $this->template->display_name)
                ->body("Your WhatsApp template is now pending review by Meta. Status changed from {$this->oldStatus} to pending."),

            WhatsAppTemplateStatusEnum::DISABLED => FilamentNotification::make()
                ->danger()
                ->icon('heroicon-o-no-symbol')
                ->title('Template Disabled: ' . $this->template->display_name)
                ->body("Your WhatsApp template has been disabled by Meta. Status changed from {$this->oldStatus} to disabled."),

            default => FilamentNotification::make()
                ->warning()
                ->icon('heroicon-o-exclamation-circle')
                ->title('Template Status Updated: ' . $this->template->display_name)
                ->body("Your WhatsApp template status changed from {$this->oldStatus} to {$this->newStatus->value}."),
        };

        return $notification
            ->actions([
                Action::make('view')
                    ->label('View Template')
                    ->url(route('filament.admin.resources.whats-app-templates.edit', ['tenant' => $gymSlug, 'record' => $this->template->id])),
            ])
            ->getDatabaseMessage();
    }
}
