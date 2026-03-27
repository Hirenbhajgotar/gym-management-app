<?php

namespace App\Http\Controllers;

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

        $consent = BiometricConsent::create([
            'gym_member_id' => $gymMember->id,
            'gym_id' => $gymMember->gym_id,
            'ip_address' => $request->ip(),
            'version' => config('biometrics.consent_version', '1.0'),
            'consented_at' => now(),
        ]);

        event(new BiometricConsentGiven($gymMember, $consent));

        return view('consent.biometric.success', [
            'gymMember' => $gymMember,
            'gym' => $gymMember->gym,
        ]);
    }
}
