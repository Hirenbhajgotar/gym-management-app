<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helpers\PhoneNumber;
use App\Helpers\WhatsAppSanitizer;
use App\Mail\BiometricConsentMail;
use App\Models\Gym;
use App\Models\GymMember;
use App\Providers\WhatsAppProviderFactory;
use App\Services\Biometric\BiometricConsentEligibility;
use App\Services\Biometric\BiometricConsentTemplateResolver;
use App\Services\Biometric\BiometricConsentUrlFactory;
use App\Services\NotificationChannelResolver;
use App\Services\WhatsAppVariableResolver;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendBiometricConsentNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 60;

    private const CHANNEL_EMAIL = 'email';

    private const CHANNEL_WHATSAPP = 'whatsapp';

    public function __construct(
        public readonly GymMember $gymMember,
        public readonly bool $sendEmail = true,
        public readonly bool $sendWhatsApp = false,
    ) {
        $this->onQueue(config()->string('queue.notifications_queue', 'notifications'));
    }

    /**
     * One outstanding consent request per member per day, so re-clicking the
     * mass-send action does not stack duplicates for the same person.
     *
     * This is a queue lock, not a delivery record: ShouldBeUnique holds it for
     * the whole queued-plus-running lifetime and releases it on completion.
     * Whether a channel actually delivered is tracked separately by the ledger,
     * because a lock that is taken before the work cannot tell a duplicate from
     * a retry.
     */
    public function uniqueId(): string
    {
        return 'biometric_consent_request_' . $this->gymMember->id . '_' . today()->toDateString();
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(WhatsAppVariableResolver $variableResolver): void
    {
        $member = $this->gymMember;

        if ($member->hasActiveBiometricConsent()) {
            $this->skip('member already has active consent');

            return;
        }

        if (! BiometricConsentEligibility::isEligible($member)) {
            $this->skip('member is archived or no longer has an active membership');

            return;
        }

        $gym = $member->gym;

        if (! $gym instanceof Gym) {
            $this->skip('member has no associated gym');

            return;
        }

        $consentUrl = BiometricConsentUrlFactory::make($member);

        if ($this->sendEmail) {
            $this->deliverEmail($member, $consentUrl);
        }

        if ($this->sendWhatsApp) {
            $this->deliverWhatsApp($gym, $member, $consentUrl, $variableResolver);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Biometric consent notification failed after retries', [
            'member_id' => $this->gymMember->id,
            'gym_id' => $this->gymMember->gym_id,
            'error' => WhatsAppSanitizer::sanitizeError(
                (string) $exception?->getMessage(),
                $this->maskablePii(),
            ),
        ]);
    }

    protected function deliverEmail(GymMember $member, string $consentUrl): void
    {
        if ($this->channelAlreadyDelivered(self::CHANNEL_EMAIL)) {
            $this->skip('consent email already delivered today');

            return;
        }

        if (blank($member->email)) {
            Log::info('Biometric consent email skipped: member has no email address', [
                'member_id' => $member->id,
            ]);

            return;
        }

        try {
            Mail::to($member->email)->send(new BiometricConsentMail(
                gymMember: $member,
                consentUrl: $consentUrl,
            ));
        } catch (Throwable $e) {
            Log::error('Failed to send biometric consent email', [
                'member_id' => $member->id,
                'error' => WhatsAppSanitizer::sanitizeError($e->getMessage(), $this->maskablePii()),
            ]);

            throw $e;
        }

        // Recorded only after the transport accepted the message, so a thrown
        // delivery stays retryable instead of being silently swallowed by a
        // slot this job already consumed.
        $this->markChannelDelivered(self::CHANNEL_EMAIL);

        Log::info('Biometric consent email dispatched', [
            'member_id' => $member->id,
            'gym_id' => $member->gym_id,
        ]);
    }

    protected function deliverWhatsApp(
        Gym $gym,
        GymMember $member,
        string $consentUrl,
        WhatsAppVariableResolver $variableResolver,
    ): void {
        if ($this->channelAlreadyDelivered(self::CHANNEL_WHATSAPP)) {
            $this->skip('consent WhatsApp message already delivered today');

            return;
        }

        $isSandbox = (bool) config('services.twilio.use_sandbox', false);

        // Sandbox must never reach a real member: the consent URL is a bearer
        // credential, and sandbox mode exists precisely so delivery is exercised
        // against a fixed test number.
        $recipient = $isSandbox
            ? config()->string('services.twilio.sandbox_number', '+15557626186')
            : $this->dialableNumber($member);

        if ($recipient === null) {
            return;
        }

        // Re-check the whole dual gate at delivery time. A gym can switch
        // WhatsApp off between the modal rendering and the worker running this.
        if (! $isSandbox && ! NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym)) {
            Log::warning('Biometric consent WhatsApp skipped: gym no longer passes the dual gate', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
            ]);

            return;
        }

        $template = BiometricConsentTemplateResolver::resolve($gym->id);

        try {
            $provider = WhatsAppProviderFactory::create($gym->id);

            if ($template && ! $isSandbox) {
                /** @var array<string, string> $variables */
                $variables = $variableResolver->resolve($template, [
                    'member' => $member,
                    'consent_url' => $consentUrl,
                ]);

                // $template->name is only the lookup key. Each provider reads its
                // own vendor identifier off the resolved model, so the resolved
                // row has to actually carry one — the gate refuses templates
                // that do not.
                $result = $provider->sendTemplateMessage($recipient, $template->name, $variables);
            } else {
                $result = $provider->sendMessage($recipient, $this->fallbackBody($gym, $member, $consentUrl));
            }

            $this->assertDelivered($result);
        } catch (Throwable $e) {
            Log::error('Failed to send biometric consent WhatsApp message', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'error' => WhatsAppSanitizer::sanitizeError($e->getMessage(), $this->maskablePii()),
            ]);

            throw $e;
        }

        $this->markChannelDelivered(self::CHANNEL_WHATSAPP);

        Log::info('Biometric consent WhatsApp message dispatched', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'message_id' => $result['message_id'] ?? null,
        ]);
    }

    /**
     * Providers report an unresolvable template, an invalid variable set, and a
     * rejected send as a non-throwing failure array. Treating those as success
     * is what allowed a broken template identifier to ship while every test
     * stayed green and the admin was told requests were queued.
     *
     * @param  array<string, mixed>  $result
     */
    protected function assertDelivered(array $result): void
    {
        if (($result['success'] ?? false) === true) {
            return;
        }

        $error = is_string($result['error'] ?? null) ? $result['error'] : 'unknown provider error';

        throw new RuntimeException("WhatsApp consent request was not delivered: {$error}");
    }

    protected function fallbackBody(Gym $gym, GymMember $member, string $consentUrl): string
    {
        $validity = BiometricConsentUrlFactory::validityLabel();

        return "Hello {$member->name},\n\n{$gym->name} requires your consent for biometric check-in."
            . " Please review and provide your consent here:\n{$consentUrl}\n\n"
            . "This link is valid for {$validity}.";
    }

    /**
     * E.164 recipient, or null when the member is unreachable. A stored value
     * that cannot be dialled is rejected here because
     * formatPhoneNumberWithCountryCode() only strips characters, so "abc"
     * would otherwise become "+91" and be handed to the provider.
     */
    protected function dialableNumber(GymMember $member): ?string
    {
        if (! BiometricConsentEligibility::hasUsableWhatsapp($member)) {
            Log::info('Biometric consent WhatsApp skipped: member cannot receive WhatsApp', [
                'member_id' => $member->id,
                'is_archived' => $member->is_archived,
                'allow_whatsapp_marketing' => $member->allow_whatsapp_marketing,
            ]);

            return null;
        }

        $dialable = PhoneNumber::toDialableE164($member->phone);

        if ($dialable === null) {
            Log::warning('Biometric consent WhatsApp skipped: stored phone is not a dialable number', [
                'member_id' => $member->id,
                'phone' => WhatsAppSanitizer::maskPhoneNumber($member->phone),
            ]);
        }

        return $dialable;
    }

    /**
     * Whether this channel already delivered today.
     *
     * The ledger is per channel, not per job: a retry after a partial failure
     * must skip the channel that already went out and re-attempt only the one
     * that did not. A single per-job key cannot express that, and taking it up
     * front — the previous behaviour — also meant the very first throw consumed
     * it, so every retry hit the "already dispatched" path, logged a routine
     * skip and reported success. $tries, $backoff and failed() were unreachable.
     *
     * Concurrency needs no separate marker: ShouldBeUnique holds its lock for the
     * whole queued-plus-running lifetime, so two deliveries of the same channel
     * cannot overlap.
     */
    protected function channelAlreadyDelivered(string $channel): bool
    {
        return Cache::has($this->deliveryLedgerKey($channel));
    }

    protected function markChannelDelivered(string $channel): void
    {
        Cache::put($this->deliveryLedgerKey($channel), true, now()->addDay());
    }

    protected function deliveryLedgerKey(string $channel): string
    {
        return "biometric_consent_delivered_{$channel}_{$this->gymMember->id}_" . today()->toDateString();
    }

    protected function skip(string $reason): void
    {
        Log::info("Biometric consent notification skipped: {$reason}", [
            'member_id' => $this->gymMember->id,
            'gym_id' => $this->gymMember->gym_id,
        ]);
    }

    /**
     * Member values scrubbed out of error text before it reaches the log, so a
     * provider exception cannot carry the signed consent URL or the member's
     * identity into storage.
     *
     * @return array<int, string>
     */
    protected function maskablePii(): array
    {
        return array_values(array_filter(
            [
                $this->gymMember->name,
                (string) $this->gymMember->email,
            ],
            static fn (string $val): bool => $val !== ''
        ));
    }
}
