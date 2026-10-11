<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\BiometricConsentGiven;
use App\Http\Requests\BiometricConsentStoreRequest;
use App\Models\BiometricConsent;
use App\Models\GymMember;
use App\Services\Biometric\BiometricConsentEligibility;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\View\View;

// use Illuminate\Http\View;

class BiometricConsentController extends Controller
{
    public function show(Request $request): View
    {
        $gymMember = GymMember::with('gym')
            ->findOrFail($request->gym_member_id);
        assert($gymMember instanceof GymMember);

        $this->guard($gymMember);

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
        $this->guard($gymMember);

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
            // Check for duplicate key error (MySQL error code 1062 or SQLSTATE 23000)
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 1062) {
                // Another request created the consent first - reload and return success
                $gymMember->refresh();

                return view('consent.biometric.success', [
                    'gymMember' => $gymMember,
                    'gym' => $gymMember->gym,
                ]);
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
    private function guard(GymMember $gymMember): void
    {
        abort_unless(BiometricConsentEligibility::honoursExistingRequest($gymMember), 410);

        abort_if($gymMember->hasRevokedBiometricConsent(), 410);
    }
}
