<?php

namespace App\Livewire\Frontend;

use App\Enums\ErrorCategory;
use App\Enums\ErrorPriority;
use App\Models\Gym;
use App\Models\GymMember;
use App\Services\ErrorReportService;
use App\Services\OtpService;
use App\Services\QRAttendanceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
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

    private ?ErrorReportService $errorReportService = null;

    protected function rules(): array
    {
        $rules = [
            'phoneNumber' => ['required', 'string', 'phone:INTERNATIONAL'],
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
            'phone' => 'phone number',
            'otp' => 'verification code',
        ];
    }

    protected function messages()
    {
        return [
            'phoneNumber.required' => 'The :attribute is missing.',
            'phoneNumber.phone' => 'The :attribute must be a valid phone number.',
            'otp.required' => 'The :attribute is missing.',
            'otp.min' => 'The :attribute is too short.',
            'otp.max' => 'The :attribute is too long.',
        ];
    }

    public function boot(OtpService $otpService, QRAttendanceService $qrAttendanceService, ErrorReportService $errorReportService): void
    {
        $this->otpService = $otpService;
        $this->qrAttendanceService = $qrAttendanceService;
        $this->errorReportService = $errorReportService;
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

    public function sendOtp(): void
    {
        $this->validateOnly('phoneNumber');

        $this->isSubmitting = true;

        try {
            // Find member by phone (handles both with and without country code)
            $this->member = $this->qrAttendanceService->findMember($this->gym->id, $this->phoneNumber, 'phone');

            if (! $this->member) {
                $this->result = [
                    'success' => false,
                    'message' => 'Member not found with this phone number.',
                ];
                $this->showResult = true;
                $this->isSubmitting = false;

                return;
            }

            // Use OTP flow if enabled
            if ($this->useOtpFlow) {
                // Check if member can access gym
                if (! $this->member->canAccessGym()) {
                    $reason = $this->qrAttendanceService->getAccessDeniedReason($this->member);
                    $this->result = [
                        'success' => false,
                        'message' => $reason,
                    ];

                    $this->showResult = true;
                    $this->isSubmitting = false;

                    return;
                }

                // Check if already checked in today
                $existingAttendance = $this->qrAttendanceService->getTodayAttendance($this->member);

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
                    'expires' => now()->addMinutes((int) config('services.otp.expiry_minutes', 5))->timestamp,
                ]);

                $this->otpSent = true;
                $this->isSubmitting = false;
            } else {
                // Direct check-in without OTP
                $this->checkInWithoutOtp();
            }
        } catch (\Exception $e) {
            $this->errorReportService->manuallyReportException(
                userDescription: $e->getMessage(),
                priority: ErrorPriority::HIGH->value,
                errorCategory: ErrorCategory::FORM_SUBMISSION->value
            );

            $this->result = [
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ];
            $this->showResult = true;
        }
    }

    public function verifyOtp(): void
    {
        $this->validateOnly('otp');

        try {
            $sessionData = Session::get('qr_checkin_verification');

            if (! $sessionData) {
                $this->result = [
                    'success' => false,
                    'message' => 'Verification session expired. Please try again.',
                ];
                $this->showResult = true;

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

                return;
            }

            $this->member = GymMember::find($sessionData['member_id']);

            if (! $this->member) {
                $this->result = [
                    'success' => false,
                    'message' => 'Member not found. Please try again.',
                ];
                $this->showResult = true;

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
            $this->errorReportService->manuallyReportException(
                userDescription: $e->getMessage(),
                priority: ErrorPriority::HIGH->value,
                errorCategory: ErrorCategory::FORM_SUBMISSION->value,
            );

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
                $this->member = $this->qrAttendanceService->findMember($this->gym->id, $this->phoneNumber, 'phone');
            }

            if (! $this->member) {
                $this->result = [
                    'success' => false,
                    'message' => 'Member not found with this phone number.',
                ];
                $this->showResult = true;

                return;
            }

            // Create attendance directly
            $result = $this->qrAttendanceService->checkIn([
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
                'identifier' => 'member_id',
            ]);

            if (! $result['success']) {
                $this->result = [
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to check in.',
                ];

                $this->showResult = true;

                return;
            }

            $this->result = $result;
            $this->showResult = true;
        } catch (\Exception $e) {
            $this->result = [
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ];
            $this->showResult = true;

            $this->errorReportService->manuallyReportException(
                userDescription: $e->getMessage(),
                priority: ErrorPriority::HIGH->value,
                errorCategory: ErrorCategory::FORM_SUBMISSION->value,
            );
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function resetForm(): void
    {
        $this->reset(['phoneNumber', 'otp', 'result', 'showResult', 'otpSent', 'member']);
        Session::forget('qr_checkin_verification');
    }

    public function render(): View
    {
        return view('livewire.frontend.qr-attendance');
    }
}
