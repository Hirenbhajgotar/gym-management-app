<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use App\Events\BiometricConsentGiven;
use App\Http\Requests\BiometricConsentStoreRequest;
use App\Models\BiometricConsent;
use App\Models\GymMember;
use Illuminate\Http\Request;
use Illuminate\View\View;

// use Illuminate\Http\View;

class BiometricConsentController extends Controller
{
    public function show(Request $request): View
    {
        $gymMember = GymMember::with('gym')
            ->findOrFail($request->gym_member_id);

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

        // Idempotent — if already consented return success
        if ($gymMember->hasActiveBiometricConsent()) {
            return view('consent.biometric.success', [
                'gymMember' => $gymMember,
                'gym' => $gymMember->gym,
            ]);
        }

        // Try to create consent - handle race condition where another request creates first
        try {
            $consent = BiometricConsent::create([
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
}
