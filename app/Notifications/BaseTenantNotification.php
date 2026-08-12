<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Gym;
use App\Models\WhatsAppTemplate;
use App\Notifications\Contracts\HasGymContext;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsAppTemplateManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

abstract class BaseTenantNotification extends Notification implements HasGymContext, ShouldQueue
{
    use Queueable, SerializesModels;

    protected Gym $gym;

    /**
     * Determine if this is a system message sent by super-admin.
     *
     * When true, the WhatsApp provider will use Twilio instead of owner's WABA.
     * Override in specific notifications for system-level messages.
     */
    public function isSystemMessage(): bool
    {
        return false;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return NotificationChannelResolver::getChannelsForGym(
            $this->gym,
            $this->getNotificationType()
        );
    }

    /**
     * Get the notification type for this notification.
     */
    abstract protected function getNotificationType(): string;

    /**
     * Get gym ID for WhatsApp context.
     */
    public function getGymId(mixed $notifiable): ?int
    {
        return $this->gym->id;
    }

    /**
     * Get WhatsApp configuration for the gym.
     */
    protected function getGymWhatsAppConfig(): ?array
    {
        $this->gym->loadMissing('whatsAppAccount');
        $whatsAppAccount = $this->gym->whatsAppAccount;

        if (! $whatsAppAccount) {
            return null;
        }

        if (! $whatsAppAccount->isActive() || ! $whatsAppAccount->hasActivePhoneNumber()) {
            return null;
        }

        return [
            'from_number' => $whatsAppAccount->phoneNumber?->phone_number ?? null,
            'provider' => $whatsAppAccount->getProvider(),
        ];
    }

    /**
     * Check if sandbox mode is enabled.
     */
    protected function isSandboxMode(): bool
    {
        return config('services.twilio.use_sandbox', false);
    }

    /**
     * Fetch the WhatsApp template via manager.
     */
    protected function getWhatsAppTemplate(string $name): ?WhatsAppTemplate
    {
        $templateManager = resolve(WhatsAppTemplateManager::class, ['tenantId' => (string) $this->gym->id]);

        return $templateManager->getTemplate($name);
    }
}
