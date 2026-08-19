<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Helpers\DateHelper;
use App\Helpers\ParserHelper;
use App\Models\City;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Services\AvatarService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use RuntimeException;

class GymMemberImport extends BaseImporter implements ShouldQueue, WithColumnFormatting
{
    protected static ?string $model = GymMember::class;

    protected array $dateColumns = [
        'dob',
        'membership_start',
        'membership_end',
    ];

    public function __construct(public int $tenantId)
    {
        parent::__construct();
    }

    /**
     * Transform data before validation so the unique phone rule
     * checks against the formatted (stored) phone value.
     */
    protected function transformForValidation(array &$data): void
    {
        if (isset($data['phone'])) {
            $phone = is_scalar($data['phone']) ? (string) $data['phone'] : '';
            $data['phone'] = ParserHelper::formatPhoneNumberWithCountryCode($phone);
        }
    }

    public function columnFormats(): array
    {
        return [
            'dob' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'membership_start' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'membership_end' => NumberFormat::FORMAT_DATE_YYYYMMDD,
        ];
    }

    public function processRow(array $row): void
    {
        $lock = Cache::lock("gym_member_import:{$this->tenantId}", 60);
        $lockAcquired = $lock->block(30);

        throw_unless($lockAcquired, RuntimeException::class, 'Could not acquire import lock. Another import is in progress for this gym.');

        try {
            DB::transaction(function () use ($row) {
                /** @var array<string, mixed> $row */
                $this->processRowInTransaction($row);
            }, 3);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function processRowInTransaction(array $row): void
    {
        $membershipPlan = $this->resolveMembershipPlan($row);
        $trainer = $this->resolveTrainer($row);

        if (isset($row['dob'])) {
            $row['age'] = DateHelper::calculateAge($row['dob']);
        }

        $profileImagePath = null;
        if (isset($row['name'])) {
            $profileImagePath = resolve(AvatarService::class)->generate($row['name']);
        }

        $membershipStart = null;
        $membershipEnd = null;

        if ($membershipPlan) {
            $dates = $this->calculateMembershipDates($row, $membershipPlan);
            $membershipStart = $dates['start'];
            $membershipEnd = $dates['end'];
        }

        $calculatedEnd = $membershipEnd ?? $row['membership_end'] ?? null;
        $isArchived = false;
        $archivedAt = null;
        $allowMarketing = true;

        if (isset($row['allow_whatsapp_marketing'])) {
            // $allowMarketing = (bool) $row['allow_whatsapp_marketing'];
            $parsed = filter_var(
                $row['allow_whatsapp_marketing'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            // Privacy-safe default: unparseable consent values are treated as opt-OUT.
            $allowMarketing = $parsed === null ? false : $parsed;
        }

        if ($calculatedEnd instanceof DateTimeInterface) {
            $endDate = Date::parse($calculatedEnd);
        } elseif (is_string($calculatedEnd) || is_numeric($calculatedEnd)) {
            $endDate = Date::parse((string) $calculatedEnd);
        } else {
            $endDate = null;
        }

        if ($endDate) {
            if ($endDate->lt(now()->subYears(2))) {
                $isArchived = true;
                $archivedAt = now();
                $allowMarketing = false;
            } elseif ($endDate->lt(now()->subYear()) && ! isset($row['allow_whatsapp_marketing'])) {
                $allowMarketing = false;
            }
        }

        $memberData = [
            'gym_id' => $this->tenantId,
            'name' => $row['name'],
            'profile_photo' => $profileImagePath,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'],
            'weight' => $row['weight'] ?? null,
            'height' => $row['height'] ?? null,
            'age' => $row['age'] ?? null,
            'dob' => $row['dob'] ?? null,
            'membership_start' => $membershipStart ?? $row['membership_start'] ?? now(),
            'gym_membership_plan_id' => $membershipPlan->id ?? null,
            'trainer_id' => $trainer?->id ?? null,
            'user_id' => Gym::query()->find($this->tenantId)?->owner()->id ?? null,
            'is_archived' => $isArchived,
            'archived_at' => $archivedAt,
            'allow_whatsapp_marketing' => $allowMarketing,
        ];

        $existingMember = GymMember::query()
            ->where('gym_id', $this->tenantId)
            ->where('email', $memberData['email'])
            ->where('phone', $memberData['phone'])
            ->lockForUpdate()
            ->first();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'phone' => 'A member with this phone number and email already exists.',
            ]);
        }

        $gymMember = GymMember::query()->create($memberData);
        $isNewMember = true;

        if ($membershipPlan) {
            $this->handleMemberPayment($gymMember, $membershipPlan, $row, $membershipStart, $membershipEnd, $isNewMember);
        }

        if (isset($row['address'])) {
            $this->handleAddress($gymMember, $row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveMembershipPlan(array $row): ?MembershipPlan
    {
        if (! isset($row['membership_plan'])) {
            return null;
        }

        $membershipPlan = MembershipPlan::query()->where('name', $row['membership_plan'])->first();

        if (! $membershipPlan) {
            throw ValidationException::withMessages(['membership_plan' => 'Membership plan not found.']);
        }

        return $membershipPlan;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveTrainer(array $row): ?Trainer
    {
        if (! isset($row['trainer_email'])) {
            return null;
        }

        return Trainer::query()->where('email', $row['trainer_email'])->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{start: Carbon, end: Carbon}
     */
    protected function calculateMembershipDates(array $row, MembershipPlan $plan): array
    {
        $hasStart = isset($row['membership_start']);
        $hasEnd = isset($row['membership_end']);

        if ($hasStart && ! $hasEnd) {
            $start = $row['membership_start']
                ? Date::parse(is_string($row['membership_start']) ? $row['membership_start'] : throw ValidationException::withMessages(['membership_start' => 'Invalid membership start date format']))
                : now();

            $end = $start->copy()->addMonths($plan->duration ?? 0);

            return ['start' => $start, 'end' => $end];
        }

        if ($hasEnd && ! $hasStart) {
            $end = Date::parse(
                is_string($row['membership_end']) ? $row['membership_end'] : throw ValidationException::withMessages(['membership_end' => 'Invalid membership end date format'])
            );

            $start = $end->copy()->subMonths($plan->duration ?? 0);

            return ['start' => $start, 'end' => $end];
        }

        if ($hasEnd && $hasStart) {
            $start = $row['membership_start']
                ? Date::parse(is_string($row['membership_start']) ? $row['membership_start'] : throw ValidationException::withMessages(['membership_start' => 'Invalid membership start date format']))
                : now();

            $end = $row['membership_end']
                ? Date::parse(is_string($row['membership_end']) ? $row['membership_end'] : throw ValidationException::withMessages(['membership_end' => 'Invalid membership end date format']))
                : $start->copy()->addMonths($plan->duration ?? 0);

            return ['start' => $start, 'end' => $end];
        }

        $start = now();
        $end = $start->copy()->addMonths($plan->duration ?? 0);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: float, 1: float, 2: PaymentStatus}
     */
    protected function calculatePaymentAmounts(array $row, float $planPrice): array
    {
        // If pending_payment is explicitly provided, use it to calculate amounts
        // pending_payment = remaining amount to be paid
        // total_paid = planPrice - pending_payment
        if (isset($row['pending_payment']) && is_numeric($row['pending_payment'])) {
            $pendingPayment = max(0.0, (float) $row['pending_payment']);
            $totalPaid = max(0.0, $planPrice - $pendingPayment);
            $currentPayment = 0.0; // No additional payment in this import unless specified

            // Auto-infer payment_status from pending_payment if not explicitly set
            $paymentStatus = isset($row['payment_status']) && is_string($row['payment_status'])
                ? PaymentStatus::from($row['payment_status'])
                : match (true) {
                    $pendingPayment <= 0 => PaymentStatus::COMPLETED,
                    $pendingPayment >= $planPrice => PaymentStatus::PENDING,
                    default => PaymentStatus::PARTIAL,
                };

            return [$totalPaid, $currentPayment, $paymentStatus];
        }

        // Fall back to explicit total_paid and payment_amount_paid
        $totalPaid = isset($row['total_paid']) && is_numeric($row['total_paid']) ? (float) $row['total_paid'] : 0.0;
        $currentPayment = isset($row['payment_amount_paid']) && is_numeric($row['payment_amount_paid']) ? (float) $row['payment_amount_paid'] : 0.0;
        $paymentStatus = isset($row['payment_status']) && is_string($row['payment_status'])
            ? PaymentStatus::from($row['payment_status'])
            : PaymentStatus::PENDING;

        return [$totalPaid, $currentPayment, $paymentStatus];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function handleMemberPayment(
        GymMember $gymMember,
        MembershipPlan $membershipPlan,
        array $row,
        ?Carbon $membershipStart,
        ?Carbon $membershipEnd,
        bool $isNewMember
    ): void {
        $planPrice = (float) $membershipPlan->price;
        // $paymentStatus = $row['payment_status'] ?? PaymentStatus::PENDING;

        // $paymentStatus now comes out of calculatePaymentAmounts() so the
        // pending_payment-based auto-inference actually takes effect, and
        // the value is guaranteed to be a PaymentStatus enum (not a raw string).
        [$totalPaid, $currentPayment, $paymentStatus] = $this->calculatePaymentAmounts($row, $planPrice);

        $existingPayment = null;

        if (! $isNewMember) {
            $existingPayment = $gymMember->payments()
                ->where('membership_plan_id', $membershipPlan->id)
                ->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::PARTIAL, PaymentStatus::COMPLETED])
                ->lockForUpdate()
                ->latest()
                ->first();
        }

        /**
         * @var Currency $currency
         */
        $currency = $membershipPlan->currency ?? Currency::INR;

        if ($existingPayment) {
            $this->updateExistingPayment($existingPayment, $paymentStatus, $totalPaid, $currentPayment, $membershipStart, $membershipEnd);
        } else {
            $payment = $this->createNewPayment($gymMember, $membershipPlan, $paymentStatus, $totalPaid, $currentPayment, $planPrice, $currency, $membershipStart, $membershipEnd);

            $gymMember->update([
                'payment_status' => $payment->status?->value,
                'latest_payment_at' => $payment->payment_date,
            ]);
        }
    }

    protected function createNewPayment(
        GymMember $gymMember,
        MembershipPlan $membershipPlan,
        PaymentStatus $paymentStatus,
        float $totalPaid,
        float $currentPayment,
        float $planPrice,
        Currency $currency,
        ?Carbon $membershipStart,
        ?Carbon $membershipEnd
    ): MemberPayment {
        $payment = $gymMember->payments()->create([
            'gym_id' => $this->tenantId,
            'membership_plan_id' => $membershipPlan->id,
            'amount' => $planPrice,
            'currency' => $currency,
            'payment_method' => PaymentMethod::CASH,
            'status' => $paymentStatus,
            'notes' => 'Imported via Excel',
            'payment_date' => now(),
            'membership_start' => $membershipStart,
            'membership_end' => $membershipEnd,
        ]);

        $this->createPaymentTransactions($payment, $paymentStatus, $totalPaid, $currentPayment, $planPrice, $currency);
        $payment->syncStatusFromTransactions();

        return $payment;
    }

    protected function updateExistingPayment(
        MemberPayment $payment,
        PaymentStatus $paymentStatus,
        float $totalPaid,
        float $currentPayment,
        ?Carbon $membershipStart,
        ?Carbon $membershipEnd
    ): void {
        $payment->update([
            'status' => $paymentStatus,
            'notes' => 'Updated via Excel import',
            'payment_date' => now(),
            'membership_start' => $membershipStart ?? $payment->membership_start,
            'membership_end' => $membershipEnd ?? $payment->membership_end,
        ]);

        if (in_array($paymentStatus, [PaymentStatus::PARTIAL, PaymentStatus::COMPLETED], true) && $currentPayment > 0) {
            $payment->transactions()->create([
                'member_payment_id' => $payment->id,
                'amount' => min($currentPayment, $payment->remaining_amount),
                'currency' => $payment->currency,
                'payment_method' => PaymentMethod::CASH,
                'transaction_date' => now(),
                'status' => 'completed',
                'notes' => 'Imported via Excel - additional payment',
            ]);

            $payment->syncStatusFromTransactions();
        }

        if ($payment->member) {
            $payment->member->update([
                'payment_status' => $payment->status?->value,
                'latest_payment_at' => $payment->payment_date,
            ]);
        }
    }

    protected function createPaymentTransactions(
        MemberPayment $payment,
        PaymentStatus $paymentStatus,
        float $totalPaid,
        float $currentPayment,
        float $planPrice,
        Currency $currency
    ): void {
        match ($paymentStatus) {
            PaymentStatus::COMPLETED => $this->createCompletedTransaction($payment, $totalPaid, $planPrice, $currency),
            PaymentStatus::PARTIAL => $this->createPartialTransactions($payment, $totalPaid, $currentPayment, $planPrice, $currency),
            default => null,
        };
    }

    private function createCompletedTransaction(
        MemberPayment $payment,
        float $totalPaid,
        float $planPrice,
        Currency $currency
    ): void {
        $amountToRecord = $totalPaid > 0 ? min($totalPaid, $planPrice) : $planPrice;

        $payment->transactions()->create([
            'member_payment_id' => $payment->id,
            'amount' => $amountToRecord,
            'currency' => $currency,
            'payment_method' => PaymentMethod::CASH,
            'transaction_date' => now(),
            'status' => 'completed',
            'notes' => 'Imported via Excel - full payment',
        ]);
    }

    private function createPartialTransactions(
        MemberPayment $payment,
        float $totalPaid,
        float $currentPayment,
        float $planPrice,
        Currency $currency
    ): void {
        if ($totalPaid > 0) {
            $payment->transactions()->create([
                'member_payment_id' => $payment->id,
                'amount' => min($totalPaid, $planPrice),
                'currency' => $currency,
                'payment_method' => PaymentMethod::CASH,
                'transaction_date' => now()->subDays(1),
                'status' => 'completed',
                'notes' => 'Imported via Excel - historical payments',
            ]);
        }

        if ($currentPayment > 0) {
            $payment->transactions()->create([
                'member_payment_id' => $payment->id,
                'amount' => min($currentPayment, $payment->remaining_amount),
                'currency' => $currency,
                'payment_method' => PaymentMethod::CASH,
                'transaction_date' => now(),
                'status' => 'completed',
                'notes' => 'Imported via Excel - current payment',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function handleAddress(GymMember $gymMember, array $row): void
    {
        $city = null;
        if (isset($row['city']) && filled($row['city'])) {
            $city = City::with('state')->where('slug', Str::slug($row['city']))->first();
        }

        $addressData = [
            'street' => $row['address'],
            'city' => $city?->id ?? null,
            'state' => $city?->state_id ?? null,
            'country' => $city?->state?->country_id ?? null,
            'postal_code' => $row['zip_code'] ?? null,
        ];

        if ($gymMember->address) {
            $gymMember->address()->update($addressData);
        } else {
            $gymMember->address()->create($addressData);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['required', 'max:20', Rule::unique('gym_members', 'phone')->where('gym_id', $this->tenantId)],
            'address' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'age' => ['nullable', 'integer'],
            'dob' => ['nullable', 'date'],
            'city' => ['nullable', 'string', 'exists:cities,slug'],
            'zip_code' => ['nullable', 'max:20'],
            'membership_start' => ['nullable', 'date'],
            'membership_end' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', Rule::enum(PaymentStatus::class)],
            'total_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_amount_paid' => ['nullable', 'numeric', 'min:0'],
            'pending_payment' => ['nullable', 'numeric', 'min:0'],
            'membership_plan' => ['nullable', 'string'],
            'trainer_email' => ['nullable', 'string', 'exists:trainers,email'],
        ];
    }
}
