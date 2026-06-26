<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppAccount;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class OtpService
{
    private bool $useSandbox = false;

    public function __construct()
    {
        $this->useSandbox = config('services.twilio.use_sandbox', false);
    }

    private function getClient($tenantId = null): Client
    {
        if ($this->useSandbox) {
            return new Client(
                config('services.twilio.master_sid'),
                config('services.twilio.master_token'),
            );
        }

        $account = WhatsAppAccount::where('gym_id', $tenantId)->first();

        throw_unless($account, Exception::class, 'No WhatsApp account found for this gym');

        return new Client(
            $account->twilio_account_sid,
            $account->twilio_auth_token,
        );
    }

    /**
     * Check if OTP verification is enabled for a gym's QR attendance.
     */
    public function isOtpEnabled(Gym $gym): bool
    {
        return $gym->getSetting('qr_checkin_otp_enabled', false) === true;
    }

    /**
     * Check if WhatsApp is configured for the gym.
     */
    public function isWhatsAppConfigured(Gym $gym): bool
    {
        $account = $gym?->whatsAppAccount;

        return $account && $account?->isActive() && $account?->isVerified();
    }

    /**
     * Check if OTP can be sent (enabled + WhatsApp configured).
     */
    public function canSendOtp(Gym $gym): bool
    {
        return $this->isOtpEnabled($gym) && $this->isWhatsAppConfigured($gym);
    }

    /**
     * Get or create Twilio Verify service SID for the gym.
     */
    public function getVerifyServiceSid(Gym $gym): ?string
    {
        $client = $this->getClient($gym->id);

        $account = $gym->whatsAppAccount;

        if (! $account) {
            return null;
        }

        // Check if verify_service_sid is already stored in WhatsAppAccount model
        if ($account->verify_service_sid) {
            return $account->verify_service_sid;
        }

        // Try to fetch from Twilio - check if a Verify service exists for this account
        try {

            // Create service name based on gym
            $serviceName = $gym->name;

            // Create new Verify service if not found
            $newService = $client->verify->v2->services->create(
                $serviceName
            );

            // Save the new service SID to WhatsAppAccount
            $account->verify_service_sid = $newService->sid;
            $account->save();

            Log::info('Created new Verify service', [
                'gym_id' => $gym->id,
                'service_sid' => $newService->sid,
            ]);

            return $newService->sid;
        } catch (\Exception $e) {
            Log::error('Failed to get or create Verify service', [
                'gym_id' => $gym->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send verification code via Twilio Verify API.
     */
    public function sendVerification(GymMember $member, Gym $gym): array
    {
        $client = $this->getClient($gym->id);

        // Check if OTP is enabled
        if (! $this->isOtpEnabled($gym)) {
            return [
                'success' => false,
                'otp_disabled' => true,
                'message' => 'OTP verification is disabled for this gym.',
            ];
        }

        // Check if WhatsApp is configured
        if (! $this->isWhatsAppConfigured($gym)) {
            return [
                'success' => false,
                'whatsapp_not_configured' => true,
                'message' => 'WhatsApp not configured. OTP verification unavailable.',
            ];
        }

        // Get Verify service SID
        $serviceSid = $this->getVerifyServiceSid($gym);

        if (! $serviceSid) {
            return [
                'success' => false,
                'message' => 'Verify service not configured. Please contact gym admin.',
            ];
        }

        // Get member's phone
        $phone = $member?->phone;

        if (blank($phone)) {
            return [
                'success' => false,
                'message' => 'Member does not have a registered phone number.',
            ];
        }

        // Format phone for Verify API (E.164 format)
        $formattedPhone = $this->formatPhoneForVerify($phone);

        // Send verification via Twilio Verify API
        try {
            $verification = $client->verify->v2->services($serviceSid)
                ->verifications
                ->create($formattedPhone, 'whatsapp');

            if ($verification->status === 'pending') {
                return [
                    'success' => true,
                    'verification_sid' => $verification->sid,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.',
            ];
        } catch (\Exception $e) {
            Log::error('QR Attendance OTP exception', [
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while sending verification code.',
            ];
        }
    }

    /**
     * Verify the OTP code via Twilio Verify API.
     */
    public function verifyCode(string $phone, string $code, Gym $gym): array
    {
        $client = $this->getClient($gym->id);

        // Get Verify service SID
        $serviceSid = $this->getVerifyServiceSid($gym);

        if (! $serviceSid) {
            return [
                'success' => false,
                'message' => 'Verify service not configured.',
            ];
        }

        // Format phone
        $formattedPhone = $this->formatPhoneForVerify($phone);

        try {
            $verificationCheck = $client->verify->v2->services($serviceSid)
                ->verificationChecks
                ->create([
                    'code' => $code,
                    'to' => $formattedPhone,
                ]);

            if ($verificationCheck->status === 'approved' && $verificationCheck->valid) {
                Log::info('QR Attendance OTP verified successfully', [
                    'gym_id' => $gym->id,
                    'phone' => $formattedPhone,
                ]);

                return [
                    'success' => true,
                    'valid' => true,
                ];
            }

            Log::warning('QR Attendance OTP verification failed', [
                'gym_id' => $gym->id,
                'phone' => $formattedPhone,
                'status' => $verificationCheck->status,
                'valid' => $verificationCheck->valid,
            ]);

            return [
                'success' => false,
                'valid' => false,
                'message' => 'Invalid verification code. Please try again.',
            ];
        } catch (\Exception $e) {
            Log::error('QR Attendance OTP verification exception', [
                'gym_id' => $gym->id,
                'phone' => $formattedPhone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while verifying code.',
            ];
        }
    }

    /**
     * Format phone number for Twilio Verify API (E.164 format).
     */
    private function formatPhoneForVerify(string $phone): string
    {
        // Remove any non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Already has country code (10+ digits starting with country)
        if (strlen($cleaned) >= 11 && str_starts_with($cleaned, '91')) {
            return '+' . $cleaned;
        }

        // Has country code but not 91 (e.g., +1 for US)
        if (strlen($cleaned) >= 11 && ! str_starts_with($cleaned, '91')) {
            return '+' . $cleaned;
        }

        // Has exactly 10 digits - add India country code
        if (strlen($cleaned) == 10) {
            return '+91' . $cleaned;
        }

        // Less than 10 digits - assume India and pad
        return '+91' . str_pad($cleaned, 10, '0', STR_PAD_LEFT);
    }
}
