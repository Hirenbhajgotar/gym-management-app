<?php

namespace App\Livewire\Frontend;

use App\Models\Attendances;
use App\Models\Gym;
use App\Models\GymMember;
use App\Services\OtpService;
use App\Services\QRAttendanceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.blank')]
#[Title('Check In | FluxFitness')]
class QRAttendance extends Component
{
    public ?Gym $gym = null;

    public string $memberId = '';

    public string $otp = '';

    public bool $isSubmitting = false;

    public bool $otpSent = false;

    public ?GymMember $member = null;

    public ?array $result = null;

    public bool $showResult = false;

    public bool $useOtpFlow = false;

    private ?OtpService $otpService = null;

    private ?QRAttendanceService $qrAttendanceService = null;

    protected function rules(): array
    {
        $rules = [
            'memberId' => ['required', 'string', 'min:5', 'max:20'],
        ];

        if ($this->otpSent && $this->useOtpFlow) {
            $rules['otp'] = ['required', 'string', 'min:6', 'max:6'];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'memberId' => 'phone number',
            'otp' => 'verification code',
        ];
    }

    public function __construct()
    {
        // parent::__construct();

        $this->otpService = app(OtpService::class);
        $this->qrAttendanceService = app(QRAttendanceService::class);
    }

    public function mount(int $gymId, string $token): void
    {
        $this->gym = Gym::find($gymId);

        if (! $this->gym || ! $this->gym->hasValidQrCodeToken($token)) {
            abort(404, 'Invalid QR code. Please scan a valid QR code from your gym.');
        }

        // Determine if OTP flow should be used
        $this->useOtpFlow = $this->otpService->canSendOtp($this->gym);
    }

    /**
     * Normalize phone number by removing country code prefix (+91, +1, etc.)
     * Also removes spaces, dashes, and parentheses.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters except +
        $normalized = preg_replace('/[^\d+]/', '', $phone);

        // If starts with +, extract country code and digits
        if (str_starts_with($normalized, '+')) {
            // Remove + and get only digits
            $digits = preg_replace('/\D/', '', $normalized);

            // Return just the digits without country code
            return $digits;
        }

        // If doesn't start with +, just return digits
        return preg_replace('/\D/', '', $normalized);
    }

    /**
     * Find member by phone number - handles both with and without country code.
     */
    private function findMemberByPhone(string $phone, int $gymId): ?GymMember
    {
        $normalizedInput = $this->normalizePhoneNumber($phone);

        if (empty($normalizedInput)) {
            return null;
        }

        // First try exact match
        $member = GymMember::where('gym_id', $gymId)
            ->where('phone', $normalizedInput)
            ->first();

        if ($member) {
            return $member;
        }

        // Try with common Indian country code (+91)
        $withCountryCode = '91' . $normalizedInput;
        $member = GymMember::where('gym_id', $gymId)
            ->where('phone', 'LIKE', '%' . $withCountryCode)
            ->first();

        if ($member) {
            return $member;
        }

        // Try matching the end of phone numbers (last 10 digits)
        // This handles cases where stored number has country code but input doesn't
        $member = GymMember::where('gym_id', $gymId)
            ->where(function ($query) use ($normalizedInput) {
                // Match if the stored phone ends with the input number
                $query->where('phone', 'LIKE', '%' . $normalizedInput)
                    // Or if input ends with the stored phone (input has country code)
                    ->orWhere('phone', 'LIKE', $normalizedInput . '%');
            })
            ->first();

        return $member;
    }

    public function sendOtp(): void
    {
        $this->validate(['memberId' => 'required|string|min:5|max:20']);

        $this->isSubmitting = true;

        try {
            // Find member by phone (handles both with and without country code)
            $this->member = $this->findMemberByPhone($this->memberId, $this->gym->id);

            if (! $this->member) {
                $this->result = [
                    'success' => false,
                    'message' => 'Member not found with this phone number.',
                ];
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Check if member can access gym
            if (! $this->member->canAccessGym()) {
                if ($this->member->isFrozen()) {
                    $this->result = [
                        'success' => false,
                        'message' => 'Your membership is currently frozen.',
                    ];
                } elseif (! $this->member->isActive()) {
                    $this->result = [
                        'success' => false,
                        'message' => 'Your membership has expired. Please renew to check in.',
                    ];
                } else {
                    $this->result = [
                        'success' => false,
                        'message' => 'You do not have access to this gym.',
                    ];
                }
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Check if already checked in today
            $existingAttendance = Attendances::where('attendanceable_id', $this->member->id)
                ->where('attendanceable_type', GymMember::class)
                ->where('gym_id', $this->gym->id)
                ->whereDate('check_in_time', now()->toDateString())
                ->first();

            if ($existingAttendance) {
                $this->result = [
                    'success' => true,
                    'message' => 'You are already checked in today.',
                    'already_checked_in' => true,
                ];
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Use OTP flow if enabled
            if ($this->useOtpFlow) {
                // Use OtpService to send verification via Twilio Verify API
                $otpResult = $this->otpService->sendVerification($this->member, $this->gym);

                if (! $otpResult['success']) {
                    // If OTP fails (not configured, etc), fall back to direct check-in
                    if (isset($otpResult['otp_disabled']) || isset($otpResult['whatsapp_not_configured'])) {
                        $this->checkInWithoutOtp();

                        return;
                    }

                    $this->result = [
                        'success' => false,
                        'message' => $otpResult['message'] ?? 'Failed to send verification code.',
                    ];
                    $this->showResult = true;
                    $this->isSubmitting = false;

                    return;
                }

                // Store verification SID for later verification
                Session::put('qr_checkin_verification', [
                    'sid' => $otpResult['verification_sid'] ?? null,
                    'member_id' => $this->member->id,
                    'expires' => now()->addMinutes(5)->timestamp,
                ]);

                $this->otpSent = true;
                $this->isSubmitting = false;

                Log::info('QR Attendance OTP sent via Twilio Verify', [
                    'gym_id' => $this->gym->id,
                    'member_id' => $this->member->id,
                ]);
            } else {
                // Direct check-in without OTP
                $this->checkInWithoutOtp();
            }
        } catch (\Exception $e) {
            Log::error('QR Attendance OTP Error', [
                'error' => $e->getMessage(),
                'gym_id' => $this->gym->id,
            ]);

            // Fall back to direct check-in on error
            $this->checkInWithoutOtp();
        }
    }

    public function verifyOtp(): void
    {
        $this->validate(['otp' => 'required|string|min:6|max:6']);

        $this->isSubmitting = true;

        try {
            $sessionData = Session::get('qr_checkin_verification');

            if (! $sessionData) {
                $this->result = [
                    'success' => false,
                    'message' => 'Verification session expired. Please try again.',
                ];
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Check expiry
            if (now()->timestamp > $sessionData['expires']) {
                Session::forget('qr_checkin_verification');
                $this->result = [
                    'success' => false,
                    'message' => 'Verification code expired. Please try again.',
                ];
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Verify via OtpService (Twilio Verify API)
            $verifyResult = $this->otpService->verifyCode($this->member->phone, $this->otp, $this->gym);

            if (! $verifyResult['success'] || ! ($verifyResult['valid'] ?? false)) {
                $this->result = [
                    'success' => false,
                    'message' => $verifyResult['message'] ?? 'Invalid verification code. Please try again.',
                ];
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Create attendance
            $this->result = $this->qrAttendanceService->checkIn([
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
                'identifier' => 'member_id',
            ]);

            // Clear session
            Session::forget('qr_checkin_verification');

            $this->showResult = true;

            Log::info('QR Attendance Check-in (OTP verified)', [
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
            ]);
        } catch (\Exception $e) {
            Log::error('QR Attendance Verify Error', [
                'error' => $e->getMessage(),
                'gym_id' => $this->gym->id,
            ]);

            $this->result = [
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ];
            $this->showResult = true;
        } finally {
            $this->isSubmitting = false;
        }
    }

    /**
     * Direct check-in without OTP (fallback when OTP disabled or not configured).
     */
    private function checkInWithoutOtp(): void
    {
        try {
            // Member should already be set from sendOtp validation
            if (! $this->member) {
                $this->member = $this->findMemberByPhone($this->memberId, $this->gym->id);
            }

            if (! $this->member) {
                $this->result = [
                    'success' => false,
                    'message' => 'Member not found with this phone number.',
                ];
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Check membership status (already done in sendOtp, but double-check)
            if (! $this->member->canAccessGym()) {
                if ($this->member->isFrozen()) {
                    $this->result = [
                        'success' => false,
                        'message' => 'Your membership is currently frozen.',
                    ];
                } elseif (! $this->member->isActive()) {
                    $this->result = [
                        'success' => false,
                        'message' => 'Your membership has expired. Please renew to check in.',
                    ];
                } else {
                    $this->result = [
                        'success' => false,
                        'message' => 'You do not have access to this gym.',
                    ];
                }
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Create attendance directly
            $this->result = $this->qrAttendanceService->checkIn([
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
                'identifier' => 'member_id',
            ]);

            $this->showResult = true;

            Log::info('QR Attendance Check-in (direct without OTP)', [
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
            ]);
        } catch (\Exception $e) {
            Log::error('QR Attendance Direct Check-in Error', [
                'error' => $e->getMessage(),
                'gym_id' => $this->gym->id,
            ]);

            $this->result = [
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ];
            $this->showResult = true;
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function resetForm(): void
    {
        $this->reset(['memberId', 'otp', 'result', 'showResult', 'otpSent', 'member']);
        Session::forget('qr_checkin_verification');
        Session::forget('qr_checkin_otp');
    }

    public function render()
    {
        return view('livewire.frontend.qr-attendance');
    }
}
