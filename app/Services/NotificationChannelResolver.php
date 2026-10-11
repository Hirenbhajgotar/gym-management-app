<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\GymSetting;
use App\Notifications\Contracts\HasMemberRecipient;
use App\Notifications\WhatsAppChannel;
use App\Services\Biometric\BiometricConsentTemplateResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;

class NotificationChannelResolver
{
    /**
     * Determine which notification channels to use for a gym.
     *
     * Gate layering: this resolver applies the opt-in gate early (so a
     * member who cannot receive WhatsApp is excluded from the channel
     * list), and WhatsAppChannel::send() re-applies the same gate
     * authoritatively. The rule of record lives on
     * GymMember::canReceiveWhatsappNotifications().
     */
    public static function getChannelsForGym(Gym $gym, NotificationType|string $notificationType = 'general', mixed $notifiable = null): array
    {
        $settings = self::getSetting($gym->id);
        $notificationTypeString = $notificationType instanceof NotificationType
            ? $notificationType->value
            : $notificationType;

        $emailEnabled = self::toBool($settings->get('email_notifications')?->value, true);

        // Check if WhatsApp notifications are globally enabled for the gym
        $whatsappEnabled = self::toBool($settings->get('whatsapp_notifications')?->value);

        $smsEnabled = self::toBool($settings->get('sms_notifications')?->value);

        // Check if specific notification type is enabled
        $notificationTypeEnabled = self::toBool($settings->get($notificationTypeString)?->value);

        // If the specific notification type is disabled, return empty array for notifications completely
        if (! $notificationTypeEnabled && $notificationTypeString !== 'general') {
            return [];
        }

        $channels = [];

        // Add channels based on settings
        if ($whatsappEnabled && self::isWhatsAppConfigured($gym)) {
            $canReceive = true;
            if ($notifiable instanceof GymMember) {
                $canReceive = $notifiable->canReceiveWhatsappNotifications();
            }

            if ($canReceive) {
                $channels[] = WhatsAppChannel::class;
            }
        }

        if ($smsEnabled) {
            $channels[] = 'sms';
        }

        // Always include email as primary channel
        if ($emailEnabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Resolve the GymMember that a notification is targeting.
     *
     * Single source of truth for "which member", so the opt-in gate cannot
     * drift across the resolver, via(), and channel send() layers.
     */
    public static function resolveTargetMember(mixed $notifiable, Notification $notification): ?GymMember
    {
        if ($notifiable instanceof GymMember) {
            return $notifiable;
        }

        if ($notification instanceof HasMemberRecipient) {
            return $notification->getMember();
        }

        return null;
    }

    /**
     * Check if WhatsApp is properly configured for the gym
     */
    public static function isWhatsAppConfigured(Gym $gym): bool
    {
        $gym->loadMissing('whatsAppAccount');
        $whatsAppAccount = $gym->whatsAppAccount;

        if (! $whatsAppAccount) {
            return false;
        }

        return $whatsAppAccount->isActive()
            && $whatsAppAccount->hasActivePhoneNumber();
    }

    /**
     * Check if a specific notification type is enabled for a gym
     */
    public static function isNotificationEnabled(Gym $gym, NotificationType|string $type): bool
    {
        $typeString = $type instanceof NotificationType ? $type->value : $type;

        return self::getSettingFlag($gym->id, $typeString);
    }

    /**
     * Dual-gate check to determine if biometric consent requests can be sent via WhatsApp for a gym:
     * 1. WhatsApp notifications enabled in tenant settings AND WhatsApp account is active with an active phone number.
     * 2. Biometric consent notifications are not individually switched off.
     * 3. An approved, active, dispatchable biometric consent template resolves for the tenant.
     *
     * The template half delegates to BiometricConsentTemplateResolver so this gate
     * can never report "sendable" for a template the provider's own lookup would
     * fail to resolve. Only the two setting keys actually consulted are loaded:
     * this gate runs inside a form visibility closure that Filament re-evaluates
     * on every modal render.
     *
     * @see self::biometricConsentWhatsAppGateFailure() for the per-condition reason
     */
    public static function canSendBiometricConsentViaWhatsApp(Gym $gym): bool
    {
        return self::biometricConsentWhatsAppGateFailure($gym) === null;
    }

    /**
     * The single condition currently holding the WhatsApp channel closed, or null
     * when the channel is available.
     *
     * The consent modal renders this instead of silently hiding its WhatsApp
     * toggle, so an admin can tell "not configured" apart from "not permitted".
     * Expressed as the negation of canSendBiometricConsentViaWhatsApp() so the
     * check and its explanation can never disagree.
     */
    public static function biometricConsentWhatsAppGateFailure(Gym $gym): ?string
    {
        if (! self::getSettingFlag($gym->id, 'whatsapp_notifications')) {
            return 'WhatsApp notifications are switched off for this gym.';
        }

        if (! self::isWhatsAppConfigured($gym)) {
            return 'No active WhatsApp account with an active phone number is connected to this gym.';
        }

        if (! self::getSettingFlag($gym->id, NotificationType::BIOMETRIC_CONSENT->value)) {
            return 'Biometric consent notifications are switched off under Settings > Notifications > WhatsApp Settings > Specific Notification Types.';
        }

        if (! BiometricConsentTemplateResolver::exists($gym->id)) {
            // unavailableReason() is total over the predicate exists() just read,
            // so there is no null arm to cover here: every branch that can be
            // reached here returns a string. A second fallback message here was
            // byte-identical to the resolver's own first one and unreachable.
            return (string) BiometricConsentTemplateResolver::unavailableReason($gym->id);
        }

        return null;
    }

    /**
     * Read a single boolean setting without hydrating the whole gym_settings
     * table.
     */
    private static function getSettingFlag(int $gymId, string $key): bool
    {
        return self::toBool(
            GymSetting::query()
                ->where('gym_id', $gymId)
                ->where('key', $key)
                ->first()
                ?->value
        );
    }

    /**
     * The one interpretation of a gym_settings value.
     *
     * The row is hydrated so GymSetting::value() stays the reading of the stored
     * value, and the raw value is then coerced explicitly: when
     * gym_settings.type is null the cast returns 'true'/'false' verbatim, and a
     * plain (bool) cast would read the string 'false' as true. Both this class's
     * bulk read and its single-row read go through here so a stored row cannot
     * mean one thing in getChannelsForGym() and another three methods below.
     */
    private static function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return Collection<string, GymSetting>
     */
    private static function getSetting(int $gymId): Collection
    {
        return GymSetting::query()->where('gym_id', $gymId)->get()->keyBy('key');
    }
}
