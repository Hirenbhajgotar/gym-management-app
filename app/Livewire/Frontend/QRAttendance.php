<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Enums\ErrorCategory;
use App\Enums\ErrorPriority;
use App\Models\Gym;
use App\Models\GymMember;
use App\Services\ErrorReportService;
use App\Services\OtpService;
use App\Services\QRAttendanceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.blank')]
class QRAttendance extends Component
{
    public ?Gym $gym = null;

    public string $phoneNumber = '';

    public string $otp = '';

    public bool $otpSent = false;

    public ?GymMember $member = null;

    public ?array $result = null;

    public bool $showResult = false;

    public bool $useOtpFlow = false;

    public bool $isCheckout = false;

    private ?OtpService $otpService = null;

    private ?QRAttendanceService $qrAttendanceService = null;

    private ?ErrorReportService $errorReportService = null;

    public ?string $checkInVerificationSessionId = null;

    public ?string $checkOutVerificationSessionId = null;

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

    protected function messages(): array
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
        $this->checkInVerificationSessionId = config('session.checkin_verification_id', 'qr_checkin_verification');
        $this->checkOutVerificationSessionId = config('session.checkout_verification_id', 'qr_checkout_verification');

        $this->gym = Gym::query()->find($gymId);

        abort_unless($this->gym, 404, 'Gym not found.');

        // Check if this is a check-out QR code
        if ($this->gym->hasValidCheckoutQrCodeToken($token)) {
            $this->isCheckout = true;
        } elseif ($this->gym->hasValidCheckInQrCodeToken($token)) {
            $this->isCheckout = false;
        } else {
            abort(404, 'Invalid QR code. Please scan a valid QR code from your gym.');
        }

        // Determine if OTP flow should be used
        $this->useOtpFlow = $this->otpService->canSendOtp($this->gym);
    }

    public function sendOtp(): void
    {
        $this->validateOnly('phoneNumber');

        $key = 'otp:' . $this->phoneNumber;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->result = ['success' => false, 'message' => 'Too many attempts. Try again later.'];
            $this->showResult = true;

            return;
        }
        RateLimiter::hit($key, 300); // 5 requests per 5 minutes

        try {
            // Find member by phone (handles both with and without country code)
            $this->member = $this->qrAttendanceService->findMember($this->gym->id, $this->phoneNumber, 'phone');

            if (! $this->member) {
                $this->result = [
                    'success' => false,
                    'message' => 'Member not found with this phone number.',
                ];
                $this->showResult = true;

                return;
            }

            // Use OTP flow if enabled
            if ($this->useOtpFlow) {
                // For checkout, check if member has checked in today
                if ($this->isCheckout) {
                    $existingAttendance = $this->qrAttendanceService->getTodayAttendance($this->member);

                    if (! $existingAttendance) {
                        $this->result = [
                            'success' => false,
                            'message' => 'No check-in found for today. Please check in first before checking out.',
                        ];
                        $this->showResult = true;

                        return;
                    }

                    if ($existingAttendance->check_out_time) {
                        $this->result = [
                            'success' => true,
                            'message' => 'You have already checked out today.',
                            'already_checked_out' => true,
                        ];
                        $this->showResult = true;

                        return;
                    }
                } else {
                    // Check if member can access gym (for check-in)
                    if (! $this->member->canAccessGym()) {
                        $reason = $this->qrAttendanceService->getAccessDeniedReason($this->member);
                        $this->result = [
                            'success' => false,
                            'message' => $reason,
                        ];

                        $this->showResult = true;

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

                        return;
                    }
                }

                // Use OtpService to send verification via Twilio Verify API
                $otpResult = $this->otpService->sendVerification($this->member, $this->gym);

                if (! $otpResult['success']) {
                    // If OTP fails (not configured, etc), fall back to direct check-in/check-out
                    if (isset($otpResult['otp_disabled']) || isset($otpResult['whatsapp_not_configured'])) {
                        if ($this->isCheckout) {
                            $this->checkOutWithoutOtp();
                        } else {
                            $this->checkInWithoutOtp();
                        }

                        return;
                    }

                    $this->result = [
                        'success' => false,
                        'message' => $otpResult['message'] ?? 'Failed to send verification code.',
                    ];
                    $this->showResult = true;

                    return;
                }

                // Store verification SID for later verification
                $sessionKey = $this->isCheckout ? $this->checkOutVerificationSessionId : $this->checkInVerificationSessionId;
                Session::put($sessionKey, [
                    'sid' => $otpResult['verification_sid'] ?? null,
                    'member_id' => $this->member->id,
                    'gym_id' => $this->gym->id,
                    'expires' => now()->addMinutes((int) config('services.otp.expiry_minutes', 5))->timestamp,
                ]);

                $this->otpSent = true;
            } else {
                // Direct check-in/check-out without OTP
                if ($this->isCheckout) {
                    $this->checkOutWithoutOtp();
                } else {
                    $this->checkInWithoutOtp();
                }
            }
        } catch (\Exception $e) {
            Log::error('QR Attendance (sendOtp) exception', [
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
                'error' => $e->getMessage(),
            ]);

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
            $sessionKey = $this->isCheckout ? $this->checkOutVerificationSessionId : $this->checkInVerificationSessionId;
            $sessionData = Session::get($sessionKey);

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
                Session::forget($sessionKey);
                $this->result = [
                    'success' => false,
                    'message' => 'Verification code expired. Please try again.',
                ];
                $this->showResult = true;

                return;
            }

            $this->member = GymMember::query()->find($sessionData['member_id']);

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

            // Create attendance or check-out based on mode
            if ($this->isCheckout) {
                $this->result = $this->qrAttendanceService->checkOut([
                    'gym_id' => $this->gym->id,
                    'member_id' => $this->member->id,
                    'identifier' => 'member_id',
                ]);
            } else {
                $this->result = $this->qrAttendanceService->checkIn([
                    'gym_id' => $this->gym->id,
                    'member_id' => $this->member->id,
                    'identifier' => 'member_id',
                ]);
            }

            // Clear session
            Session::forget($sessionKey);

            $this->showResult = true;

            $logMessage = $this->isCheckout ? 'QR Attendance Check-out (OTP verified)' : 'QR Attendance Check-in (OTP verified)';
            Log::info($logMessage, [
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
            ]);
        } catch (\Exception $e) {
            Log::error('QR Attendance (verifyOtp) exception', [
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
                'error' => $e->getMessage(),
            ]);

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

        }
    }

    /**
     * Direct check-in without OTP (fallback when OTP disabled or not configured).
     */
    private function checkInWithoutOtp(): void
    {
        try {
            // Create attendance directly
            $result = $this->qrAttendanceService->checkIn([
                'gym_id' => $this->gym?->id,
                'member_id' => $this->member?->id,
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
            Log::error('QR Attendance Check-in (without OTP) exception', [
                'gym_id' => $this->gym?->id,
                'member_id' => $this->member?->id,
                'error' => $e->getMessage(),
            ]);

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
        }
    }

    /**
     * Direct check-out without OTP (fallback when OTP disabled or not configured).
     */
    private function checkOutWithoutOtp(): void
    {
        try {
            // Create check-out directly
            $result = $this->qrAttendanceService->checkOut([
                'gym_id' => $this->gym?->id,
                'member_id' => $this->member?->id,
                'identifier' => 'member_id',
            ]);

            if (! $result['success']) {
                $this->result = [
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to check out.',
                ];

                $this->showResult = true;

                return;
            }

            $this->result = $result;
            $this->showResult = true;
        } catch (\Exception $e) {
            Log::error('QR Attendance Check-out (without OTP) exception', [
                'gym_id' => $this->gym?->id,
                'member_id' => $this->member?->id,
                'error' => $e->getMessage(),
            ]);

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
        }
    }

    public function resetForm(): void
    {
        $this->reset(['phoneNumber', 'otp', 'result', 'showResult', 'otpSent', 'member']);
        Session::forget($this->checkInVerificationSessionId);
        Session::forget($this->checkOutVerificationSessionId);
    }

    public function render(): View
    {
        $title = $this->isCheckout ? 'Check Out' : 'Check In';

        return view('livewire.frontend.qr-attendance')
            ->layout('components.layouts.blank')
            ->title($title . ' | ' . config('app.name'));
    }
}
