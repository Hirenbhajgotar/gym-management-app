<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\BiometricConsentGiven;
use App\Http\Requests\BiometricConsentStoreRequest;
use App\Models\BiometricConsent;
use App\Models\GymMember;
use App\Services\Biometric\BiometricConsentEligibility;
use App\Services\Biometric\BiometricConsentUrlFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BiometricConsentController extends Controller
{
    public function show(Request $request): View
    {
        $gymMember = GymMember::with('gym')
            ->findOrFail($request->gym_member_id);
        assert($gymMember instanceof GymMember);

        $this->guard($gymMember, $request);

        // Already consented — show already-consented view
        if ($gymMember->hasActiveBiometricConsent()) {
            return view('consent.biometric.already-consented', [
                'gymMember' => $gymMember,
                'gym' => $gymMember->gym,
            ]);
        }

        return view('consent.biometric.show', [
            'gymMember' => $gymMember,
            'gym' => $gymMember->gym,
            'consentVersion' => config('biometrics.consent_version', '1.0'),
        ]);
    }

    public function store(BiometricConsentStoreRequest $request): View
    {
        $gymMember = GymMember::with('gym')
            ->findOrFail($request->gym_member_id);
        assert($gymMember instanceof GymMember);

        // Same guard as show(): the POST carries the same bearer link, so a lapsed
        // membership must not be able to write the row either.
        $this->guard($gymMember, $request);

        // Idempotent — if already consented return success
        if ($gymMember->hasActiveBiometricConsent()) {
            return view('consent.biometric.success', [
                'gymMember' => $gymMember,
                'gym' => $gymMember->gym,
            ]);
        }

        // Try to create consent - handle race condition where another request creates first
        try {
            $consent = BiometricConsent::query()->create([
                'gym_member_id' => $gymMember->id,
                'gym_id' => $gymMember->gym_id,
                'ip_address' => $request->ip(),
                'version' => config('biometrics.consent_version', '1.0'),
                'consented_at' => now(),
            ]);

            // Only fire event when create actually succeeds
            event(new BiometricConsentGiven($gymMember, $consent));
        } catch (QueryException $e) {
            // Check specifically for duplicate key error (MySQL error code 1062)
            $isDuplicate = ($e->errorInfo[1] ?? null) === 1062
                || (int) $e->getCode() === 1062
                || str_contains($e->getMessage(), 'Duplicate entry');

            if ($isDuplicate) {
                // Another request created the consent first - reload and return success
                $gymMember->refresh();

                if ($gymMember->hasActiveBiometricConsent()) {
                    return view('consent.biometric.success', [
                        'gymMember' => $gymMember,
                        'gym' => $gymMember->gym,
                    ]);
                }

                // If a legacy table index collided with a previous revoked row, update the existing row to active
                $revokedConsent = $gymMember->biometricConsents()
                    ->where('gym_id', $gymMember->gym_id)
                    ->whereNotNull('revoked_at')
                    ->latest('revoked_at')
                    ->first();

                if ($revokedConsent !== null) {
                    Log::warning(sprintf(
                        'BiometricConsentController: Legacy unique constraint encountered for gym_member_id=%d. Updated existing revoked consent row. Please run migration 2026_11_17_000000_update_biometric_consents_active_unique_index.',
                        $gymMember->id
                    ));

                    $revokedConsent->update([
                        'revoked_at' => null,
                        'consented_at' => now(),
                        'ip_address' => $request->ip(),
                        'version' => config('biometrics.consent_version', '1.0'),
                    ]);

                    event(new BiometricConsentGiven($gymMember, $revokedConsent));

                    return view('consent.biometric.success', [
                        'gymMember' => $gymMember,
                        'gym' => $gymMember->gym,
                    ]);
                }
            }

            // Re-throw non-duplicate errors
            throw $e;
        }

        return view('consent.biometric.success', [
            'gymMember' => $gymMember,
            'gym' => $gymMember->gym,
        ]);
    }

    /**
     * The conditions under which a signed consent link is still honoured, for
     * both the GET and the POST — they carry the same credential, so they are
     * held to the same rule in one place.
     *
     * A signed link stays valid for its whole TTL, which can outlive the
     * membership it was minted for and can outlive a revocation. The first guard
     * therefore has to exist at all: without it an archived or lapsed member
     * could still read the consent page and grant consent for a gym they no
     * longer belong to.
     *
     * The second is what makes a revocation mean something. Replaying the link
     * after a withdrawal used to write a fresh consent row, because the only
     * thing standing between "revoked" and "consented" was the absence of an
     * active row, and the partial unique index only guards `revoked_at IS NULL`.
     * Refusing the link closes that: a member who withdraws stays withdrawn, and
     * re-consent has to be re-requested through the gym, which mints a fresh link
     * down the audited path rather than reusing one that is already in an inbox.
     */
    private function guard(GymMember $gymMember, Request $request): void
    {
        abort_unless(
            BiometricConsentEligibility::honoursExistingRequest($gymMember),
            410,
            'This consent request is no longer valid for this membership.'
        );

        if ($gymMember->hasRevokedBiometricConsent()) {
            $latestRevokedAt = $gymMember->biometricConsents()
                ->where('gym_id', $gymMember->gym_id)
                ->whereNotNull('revoked_at')
                ->latest('revoked_at')
                ->value('revoked_at');

            if ($latestRevokedAt !== null) {
                $latestRevokedAtCarbon = Carbon::parse($latestRevokedAt);
                $mintedAt = $request->query('minted_at');

                if ($mintedAt !== null && is_numeric($mintedAt)) {
                    abort_if(
                        Carbon::createFromTimestamp((int) $mintedAt)->lte($latestRevokedAtCarbon),
                        410,
                        'This biometric consent request was revoked. Please request a new link from gym reception.'
                    );
                } else {
                    $expires = $request->query('expires');
                    $maxLifetimeAfterRevocation = (clone $latestRevokedAtCarbon)->addDays(BiometricConsentUrlFactory::MAX_EXPIRY_DAYS);

                    if ($expires === null || ! is_numeric($expires) || Carbon::createFromTimestamp((int) $expires)->lte($maxLifetimeAfterRevocation)) {
                        abort(
                            410,
                            'This biometric consent request was revoked. Please request a new link from gym reception.'
                        );
                    }
                }
            }
        }
    }
}
