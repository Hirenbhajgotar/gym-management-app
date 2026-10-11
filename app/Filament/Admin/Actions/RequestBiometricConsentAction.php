<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Jobs\SendBiometricConsentNotificationJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use App\Services\Biometric\BiometricConsentDispatchReport;
use App\Services\Biometric\BiometricConsentEligibility;
use App\Services\NotificationChannelResolver;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Shared implementation of the biometric consent mass-request action.
 *
 * The header ("all eligible members") and table ("selected members") entry
 * points previously carried token-identical copies of the form, the five
 * user-facing strings, and the dispatch loop — but two different eligibility
 * definitions and no authorization on either. Both now build from here, so the
 * form, the eligibility rule, the permission and the wording cannot drift apart.
 *
 * Authorization reuses the enrollBiometric ability: requesting consent is a
 * strict prerequisite of enrolling, so the permitted set is a subset of the
 * enrolment set. Reusing it means the trainer exclusion written into
 * GymMemberPolicy::enrollBiometric() automatically applies here too, instead of
 * a second role rule being invented and later diverging.
 */
final class RequestBiometricConsentAction
{
    public const HEADER_ACTION = 'sendBiometricConsentToAll';

    public const BULK_ACTION = 'sendBiometricConsent';

    public const ACTION_FAN_OUT = 'fan_out';

    public const ACTION_BULK = 'bulk_dispatch';

    public const ACTION_RESEND_EMAIL = 'resend_consent_email';

    public const ACTION_COPY_LINK = 'copy_consent_link';

    /**
     * Ceiling on bulk consent broadcasts per operator per gym.
     *
     * The action fans out member PII plus an unauthenticated bearer consent URL,
     * so an operator who fat-fingers it, or whose session is hijacked, must not be
     * able to turn one click into a tenant-wide disclosure. Twenty per hour is far
     * above any real gym's cadence — a 2,000-member gym exhausts the whole gym in
     * one send — while still bounding a scripted loop.
     */
    private const MAX_ATTEMPTS_PER_HOUR = 20;

    private const DECAY_SECONDS = 3600;

    public static function canAuthorize(?GymMember $member = null): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $member !== null
            ? $user->can('enrollBiometric', $member)
            : $user->can('enrollBiometric', GymMember::class);
    }

    /**
     * @return array<int, Toggle>
     */
    public static function formSchema(): array
    {
        return [
            Toggle::make('send_email')
                ->label('Send via Email')
                ->default(true)
                ->helperText('Deliver biometric consent link via email.'),

            Toggle::make('send_whatsapp')
                ->label('Send via WhatsApp')
                ->default(false)
                // Shown but disabled rather than hidden: an admin who cannot see
                // the toggle has no way to learn that WhatsApp consent requests are
                // unavailable, or which setting is holding them back.
                ->disabled(static fn (): bool => self::whatsappGateFailure() !== null)
                ->helperText(static function (): string {
                    $failure = self::whatsappGateFailure();

                    return $failure ?? 'Deliver via the gym\'s approved WhatsApp template.';
                }),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, bool>
     */
    public static function channelFlags(array $data): array
    {
        return [
            'sendEmail' => (bool) ($data['send_email'] ?? false),
            'sendWhatsApp' => (bool) ($data['send_whatsapp'] ?? false),
        ];
    }

    /**
     * @param  int|null  $eligible  a count the caller has already taken this render.
     *                              Filament re-evaluates the modal description on
     *                              every re-render, and an eligibility COUNT(*) over
     *                              the whole gym is not something to run once per
     *                              wire round trip for the same membership set.
     */
    public static function headerDescription(int $gymId, ?int $eligible = null): string
    {
        $eligible ??= BiometricConsentEligibility::constrain(GymMember::query(), $gymId)->count();

        if ($eligible === 0) {
            return 'There are currently no active members without biometric consent.';
        }

        return "This will send biometric consent notifications to {$eligible} eligible active member(s) who do not currently have active biometric consent.";
    }

    /**
     * @param  Collection<int, GymMember>  $records
     */
    public static function bulkDescription(Collection $records): string
    {
        $eligible = $records->filter(BiometricConsentEligibility::isEligible(...))->count();
        $total = $records->count();

        if ($eligible === 0) {
            return "All {$total} selected member(s) are already consented, archived, or no longer active.";
        }

        return "This will send biometric consent notifications to {$eligible} of {$total} selected member(s) who can be asked for consent.";
    }

    /**
     * Dispatch consent requests for an explicit set of members, applying the
     * shared eligibility predicate and a per-record authorization re-check so a
     * selection spanning another gym can never leak a request.
     *
     * @param  Collection<int, GymMember>  $members
     */
    public static function dispatch(Collection $members, bool $sendEmail, bool $sendWhatsApp): BiometricConsentDispatchReport
    {
        $dispatched = 0;
        $ineligible = 0;
        $unauthorized = 0;

        foreach ($members as $member) {
            if (! self::canAuthorize($member)) {
                $unauthorized++;

                continue;
            }

            if (! BiometricConsentEligibility::isEligible($member)) {
                $ineligible++;

                continue;
            }

            dispatch(new SendBiometricConsentNotificationJob(
                gymMember: $member,
                sendEmail: $sendEmail,
                sendWhatsApp: $sendWhatsApp,
            ));

            $dispatched++;
        }

        $report = new BiometricConsentDispatchReport(
            selected: $members->count(),
            dispatched: $dispatched,
            ineligible: $ineligible,
            unauthorized: $unauthorized,
        );

        self::audit(self::ACTION_BULK, $sendEmail, $sendWhatsApp, $report);

        return $report;
    }

    /**
     * Counts are only reported for the bulk path, where the caller's selection
     * bounds the work and dispatch() returns what it actually queued. The header
     * path hands the selection to a fan-out job instead, so its reachability is
     * decided in the worker and a per-member number would be a guess.
     */
    public static function notifyQueued(BiometricConsentDispatchReport $report): void
    {
        $skipped = [];

        if ($report->ineligible > 0) {
            $skipped[] = "{$report->ineligible} were not eligible (already consented, archived, or no longer active)";
        }

        if ($report->unauthorized > 0) {
            $skipped[] = "{$report->unauthorized} were refused because they belong to another gym";
        }

        $body = $report->dispatched === 0
            ? 'No selected member was eligible to receive a consent request.'
            : "Requests queued for {$report->dispatched} member(s)."
                . ($skipped !== [] ? ' ' . implode('; ', $skipped) . '.' : '');

        Notification::make()
            ->title($report->dispatched === 0 ? 'No eligible members' : 'Biometric Consent Requests Queued')
            ->body($body)
            ->success()
            ->send();
    }

    public static function notifyFanOutQueued(int $eligible): void
    {
        Notification::make()
            ->title('Biometric Consent Requests Queued')
            ->body($eligible === 0
                ? 'No active members currently require biometric consent.'
                : "Requests are being queued for up to {$eligible} eligible member(s). Members without a reachable email address or an opted-in WhatsApp number are skipped when the request runs.")
            ->success()
            ->send();
    }

    public static function notifyNoChannelSelected(): void
    {
        Notification::make()
            ->title('No delivery channel selected')
            ->body('Please enable at least one channel (Email or WhatsApp).')
            ->danger()
            ->send();
    }

    /**
     * Refuses a broadcast that has already been run MAX_ATTEMPTS_PER_HOUR times
     * by this operator for this gym inside the decay window.
     */
    public static function throttle(int $gymId): bool
    {
        $key = self::rateLimiterKey($gymId);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS_PER_HOUR)) {
            Notification::make()
                ->title('Too many consent broadcasts')
                ->body(sprintf(
                    'You have sent biometric consent requests %d times in the last hour. Try again later.',
                    self::MAX_ATTEMPTS_PER_HOUR,
                ))
                ->danger()
                ->send();

            return false;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return true;
    }

    private static function rateLimiterKey(int $gymId): string
    {
        $userId = Auth::id() ?? 'guest';

        return "biometric_consent:broadcast:{$userId}:{$gymId}";
    }

    public static function newBroadcastId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Audit trail for every action that puts member PII or an unauthenticated
     * bearer consent URL in front of an operator.
     *
     * The two row actions that export the link itself used to write no record at
     * all, which made the credential-exporting actions the least audited of the
     * four. Identifiers only: no member name, email, phone or consent URL may
     * reach the log. The broadcast id ties the HTTP-thread record to the
     * fan-out job's own completion record, which is written in a different
     * process and would otherwise be unjoinable.
     */
    public static function audit(
        string $action,
        bool $sendEmail,
        bool $sendWhatsApp,
        BiometricConsentDispatchReport $report,
        ?string $broadcastId = null,
    ): void {
        self::writeAudit($action, $sendEmail, $sendWhatsApp, $report, $broadcastId ?? self::newBroadcastId());
    }

    /**
     * Audit record for the row actions, which touch exactly one member.
     *
     * copy_consent_link is the action that puts the bearer consent URL itself in
     * front of an operator, so it previously wrote no record at all while the
     * smaller bulk broadcast did.
     */
    public static function auditRowAction(string $action, GymMember $member): void
    {
        Log::info('Biometric consent action executed', [
            'action' => $action,
            'broadcast_id' => self::newBroadcastId(),
            'actor_id' => Auth::id(),
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'send_email' => $action !== self::ACTION_COPY_LINK,
            'send_whatsapp' => false,
        ]);
    }

    private static function writeAudit(
        string $action,
        bool $sendEmail,
        bool $sendWhatsApp,
        BiometricConsentDispatchReport $report,
        string $broadcastId,
    ): void {
        Log::info('Biometric consent action executed', [
            'action' => $action,
            'broadcast_id' => $broadcastId,
            'actor_id' => Auth::id(),
            'gym_id' => Filament::getTenant()?->getKey(),
            'selected' => $report->selected,
            'dispatched' => $report->dispatched,
            'ineligible' => $report->ineligible,
            'unauthorized' => $report->unauthorized,
            'send_email' => $sendEmail,
            'send_whatsapp' => $sendWhatsApp,
        ]);
    }

    private static function whatsappGateFailure(): ?string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Gym
            ? NotificationChannelResolver::biometricConsentWhatsAppGateFailure($tenant)
            : 'No gym is selected.';
    }
}
