<?php

namespace App\Livewire\Frontend;

use App\Helpers\ParserHelper;
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

    public string $phoneNumber = '';

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
            'phoneNumber' => ['required', 'string', 'min:5', 'max:20'],
        ];

        if ($this->otpSent && $this->useOtpFlow) {
            $rules['otp'] = ['required', 'string', 'min:6', 'max:6'];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'phoneNumber' => 'phone number',
            'otp' => 'verification code',
        ];
    }

    public function boot(OtpService $otpService, QRAttendanceService $qrAttendanceService): void
    {
        $this->otpService = $otpService;
        $this->qrAttendanceService = $qrAttendanceService;
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
     * Find member by phone number - handles both with and without country code.
     */
    private function findMemberByPhone(string $phone, int $gymId): ?GymMember
    {
        $normalizedInput = ParserHelper::normalizePhoneNumber($phone);

        if (empty($normalizedInput)) {
            return null;
        }

        // First try exact match or common variations in a single query
        return GymMember::where('gym_id', $gymId)
            ->where(function ($query) use ($normalizedInput) {
                $query->where('phone', $normalizedInput)
                    ->orWhere('phone', 'LIKE', '%91' . $normalizedInput)
                    ->orWhere('phone', 'LIKE', '%' . $normalizedInput)
                    ->orWhere('phone', 'LIKE', $normalizedInput . '%');
            })
            ->first();
    }

    public function sendOtp(): void
    {
        $this->validate(['phoneNumber' => 'required|string|min:5|max:20']);

        $this->isSubmitting = true;

        try {
            // Find member by phone (handles both with and without country code)
            $this->member = $this->findMemberByPhone($this->phoneNumber, $this->gym->id);

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
                $this->member = $this->findMemberByPhone($this->phoneNumber, $this->gym->id);
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
        $this->reset(['phoneNumber', 'otp', 'result', 'showResult', 'otpSent', 'member']);
        Session::forget('qr_checkin_verification');
        Session::forget('qr_checkin_otp');
    }

    public function render()
    {
        return view('livewire.frontend.qr-attendance');
    }
}
