<?php

namespace App\Notifications;

use App\Models\WhatsAppTemplate;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WhatsAppTemplateApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WhatsAppTemplate $template
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ WhatsApp Template Approved — ' . $this->template->display_name)
            ->greeting("Hello {$notifiable->name},")
            ->line("Great news! Your WhatsApp template **\"{$this->template->display_name}\"** has been approved by Meta.")
            ->line('You can now use this template to send messages to your members.')
            ->line('**Template Details:**')
            ->line("- **Name:** {$this->template->display_name}")
            ->line('- **Category:** ' . ucfirst($this->template->category))
            ->line('- **Status:** ✅ Approved')
            ->action('Go to Templates', route('filament.admin.resources.whats-app-templates.edit', ['tenant' => $this->template->gym->slug, 'record' => $this->template->id]))
            ->salutation('Happy messaging! 🎉');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->success()
            ->icon('heroicon-o-check-circle')
            ->title('Template Approved: ' . $this->template->display_name)
            ->body("Your WhatsApp template \"{$this->template->display_name}\" has been approved by Meta. You can now use it for broadcasts.")
            ->getDatabaseMessage();
    }
}
