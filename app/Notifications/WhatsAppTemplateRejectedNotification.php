<?php

namespace App\Notifications;

use App\Models\WhatsAppTemplate;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WhatsAppTemplateRejectedNotification extends Notification implements ShouldQueue
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
        $gymSlug = $this->template->gym?->slug ?? 'admin';
        $reason = $this->template->rejection_reason ?? 'No specific reason provided.';

        return (new MailMessage)
            ->subject('❌ WhatsApp Template Rejected — ' . $this->template->display_name)
            ->greeting("Hello {$notifiable->name},")
            ->line("Unfortunately, your WhatsApp template **\"{$this->template->display_name}\"** was rejected by Meta.")
            ->line('**Rejection Reason:**')
            ->line("> {$reason}")
            ->line('')
            ->line('**How to fix it:**')
            ->line('1. Review the rejection reason carefully')
            ->line('2. For **utility templates**: Remove any emojis, promotional language, or call-to-action selling')
            ->line("3. For **marketing templates**: Ensure content complies with Meta's commerce policy")
            ->line('4. Edit your template and resubmit for approval')
            ->action('Edit & Resubmit', route('filament.admin.resources.whats-app-templates.edit', ['tenant' => $gymSlug, 'record' => $this->template->id]))
            ->salutation('Don\'t worry — most templates get approved after a quick fix!');
    }

    public function toDatabase(object $notifiable): array
    {
        $gymSlug = $this->template->gym?->slug ?? 'admin';
        $reason = $this->template->rejection_reason ?? 'No specific reason provided.';

        return FilamentNotification::make()
            ->danger()
            ->icon('heroicon-o-x-circle')
            ->title('Template Rejected: ' . $this->template->display_name)
            ->body("Your WhatsApp template was rejected. Reason: \"{$reason}\". Click to edit and resubmit.")
            ->actions([
                Action::make('view')
                    ->label('View Template')
                    ->url(route('filament.admin.resources.whats-app-templates.edit', ['tenant' => $gymSlug, 'record' => $this->template->id])),
            ])
            ->getDatabaseMessage();
    }
}
