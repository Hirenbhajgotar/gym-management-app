<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Helpers\ParserHelper;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Services\AvatarService;
use Date;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class GymMemberImporter extends Importer
{
    protected static ?string $model = GymMember::class;

    /**
     * Stashed per-row state. Filament instantiates a fresh Importer
     * for every row, so it's safe to keep these as instance properties
     * instead of threading them through every method signature.
     */
    protected ?MembershipPlan $membershipPlan = null;

    protected ?Carbon $membershipEndInput = null;

    protected ?PaymentStatus $paymentStatusInput = null;

    protected ?float $pendingPaymentInput = null;

    protected bool $isNewRecord = false;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('phone')
                ->requiredMapping()
                ->rules(['required', 'max:20'])
                ->castStateUsing(fn (?string $state): string => ParserHelper::formatPhoneNumberWithCountryCode((string) $state)),

            ImportColumn::make('membership_plan')
                ->label('Membership Plan')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->guess(['plan', 'membership plan', 'plan name'])
                // We resolve + stash the plan ourselves (instead of using
                // relationship()) because we also need its price/duration/
                // currency later on to build the payment ledger entry.
                ->fillRecordUsing(function (GymMember $record, string $state, array $options): void {
                    $plan = MembershipPlan::query()
                        ->where('gym_id', $options['tenantId'])
                        ->where('name', $state)
                        ->first();

                    throw_unless($plan, RowImportFailedException::class, "Membership plan [{$state}] not found.");

                    /** @var int<0, max> $planId */
                    $planId = $plan->id;
                    $record->gym_membership_plan_id = $planId;
                }),

            ImportColumn::make('membership_end')
                ->label('Membership End')
                ->rules(['nullable', 'date'])
                ->castStateUsing(fn (?string $state): ?Carbon => blank($state) ? null : Date::parse($state)),

            ImportColumn::make('payment_status')
                ->label('Payment Status')
                ->rules(['nullable', 'string', Rule::enum(PaymentStatus::class)])
                ->fillRecordUsing(function (): void {
                    // Intentionally empty — prevents the default
                    // $record->payment_status = $state, which would persist
                    // the raw CSV string ('partial', 'completed') into an
                    // integer column. The actual PaymentStatus enum is
                    // reconstructed in afterSave() from $this->data.
                }),

            ImportColumn::make('pending_payment')
                ->label('Pending Payment')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0'])
                ->fillRecordUsing(function (): void {
                    // Intentionally empty — pending_payment is not a DB
                    // column; it only drives partial-payment calculations in
                    // afterSave(). The value is read from $this->data.
                }),

            ImportColumn::make('trainer_email')
                ->label('Trainer Email')
                ->rules(['nullable', 'string', 'exists:trainers,email'])
                ->guess(['trainer email', 'trainer e-mail', 'trainer'])
                ->fillRecordUsing(function (GymMember $record, ?string $state, array $options): void {
                    if (blank($state)) {
                        return;
                    }

                    $trainer = Trainer::query()
                        ->where('gym_id', $options['tenantId'])
                        ->where('email', $state)
                        ->first();

                    /** @var int<0, max>|null $trainerId */
                    $trainerId = $trainer?->id;
                    $record->trainer_id = $trainerId;
                }),

            ImportColumn::make('allow_whatsapp_marketing')
                ->label('Allow WhatsApp Marketing')
                ->boolean()
                ->rules(['nullable', 'boolean'])
                ->guess(['whatsapp_marketing', 'allow_whatsapp_marketing', 'whatsapp marketing']),

            ImportColumn::make('is_archived')
                ->label('Is Archived')
                ->boolean()
                ->rules(['nullable', 'boolean'])
                ->guess(['archived', 'is_archived']),
        ];
    }

    /**
     * Resolve (or start) the GymMember record being imported, scoped to the
     * gym passed in via ImportAction::make()->options(['tenantId' => ...]).
     *
     * A short cache lock guards against two chunk-processing workers racing
     * to create the same phone number at the same time; the unique index on
     * (gym_id, phone) is the final safety net if that ever happens anyway.
     */
    public function resolveRecord(): GymMember
    {
        $rawTenantId = $this->options['tenantId'] ?? null;

        throw_unless($rawTenantId, RowImportFailedException::class, 'No tenant/gym was provided to the importer.');

        /** @var int<0, max> $tenantId */
        $tenantId = is_numeric($rawTenantId) ? (int) $rawTenantId : 0;

        $rawPhone = $this->data['phone'] ?? '';
        $phone = ParserHelper::formatPhoneNumberWithCountryCode(is_string($rawPhone) || is_numeric($rawPhone) ? (string) $rawPhone : '');

        $lock = Cache::lock("gym_member_import:{$tenantId}:{$phone}", 30);

        throw_unless($lock->block(15), RowImportFailedException::class, 'Could not acquire import lock for this member. Please retry.');

        // Released in afterSave()/afterFail equivalents isn't available on
        // Importer, so we release right away — the row is processed inside
        // a single synchronous call, and the DB unique constraint covers
        // the remaining race window.
        $lock->release();

        /** @var GymMember|null $record */
        $record = GymMember::query()
            ->where('gym_id', $tenantId)
            ->where('phone', $phone)
            ->lockForUpdate()
            ->first();

        $this->isNewRecord = is_null($record);

        if ($record) {
            return $record;
        }

        $record = new GymMember();
        $record->gym_id = $tenantId;

        return $record;
    }

    protected function beforeSave(): void
    {
        /** @var GymMember $record */
        $record = $this->record;
        $rawTenantId = $this->options['tenantId'] ?? null;
        $tenantId = is_numeric($rawTenantId) ? (int) $rawTenantId : 0;

        $nameVal = $this->data['name'] ?? null;
        if (is_string($nameVal) && filled($nameVal)) {
            $avatar = resolve(AvatarService::class)->generate($nameVal);
            $record->profile_photo = is_string($avatar) ? $avatar : null;
        }

        $gym = Gym::query()->where('id', $tenantId)->first();
        if ($gym) {
            $owner = $gym->owner();
            if ($owner) {
                /** @var int<0, max> $ownerId */
                $ownerId = $owner->id;
                $record->user_id = $ownerId;
            }
        }

        // The fillRecordUsing closures cannot use $this because getColumns() is
        // static, so we reconstruct stashed values here from data already on the
        // record and in $this->data (which was populated by castData()).
        $endInput = $this->data['membership_end'] ?? null;
        $this->membershipEndInput = $endInput instanceof Carbon ? $endInput : (is_string($endInput) && filled($endInput) ? Date::parse($endInput) : null);

        if ($record->gym_membership_plan_id) {
            $this->membershipPlan = MembershipPlan::query()
                ->where('gym_id', $tenantId)
                ->where('id', $record->gym_membership_plan_id)
                ->first();
        }

        if ($this->membershipPlan) {
            [$start, $end] = $this->calculateMembershipDates($this->membershipPlan);
            $record->membership_start = $start->toDateString();
            $record->membership_end = $end->toDateString();
        }

        // Auto-calculate archiving and WhatsApp marketing permissions based on membership_end
        $rawEnd = $record->membership_end;
        $membershipEnd = $rawEnd ? Date::parse($rawEnd) : null;

        if ($membershipEnd) {
            $twoYearsAgo = now()->subYears(2);
            $oneYearAgo = now()->subYear();

            if ($membershipEnd->lt($twoYearsAgo)) {
                $record->is_archived = true;
                $record->archived_at = now()->toDateTimeString();
                $record->allow_whatsapp_marketing = false;
            } elseif ($membershipEnd->lt($oneYearAgo)) {
                $record->is_archived = false;
                $record->archived_at = null;
                if (! isset($this->data['allow_whatsapp_marketing'])) {
                    $record->allow_whatsapp_marketing = false;
                }
            } else {
                $record->is_archived = false;
                $record->archived_at = null;
                if (! isset($this->data['allow_whatsapp_marketing'])) {
                    $record->allow_whatsapp_marketing = true;
                }
            }
        } else {
            $record->is_archived = false;
            $record->archived_at = null;
            if (! isset($this->data['allow_whatsapp_marketing'])) {
                $record->allow_whatsapp_marketing = true;
            }
        }
    }

    protected function afterSave(): void
    {
        /** @var GymMember $gymMember */
        $gymMember = $this->record;

        // membershipPlan was already reconstructed in beforeSave().
        if (! $this->membershipPlan) {
            return;
        }

        // Reconstruct payment data from $this->data. The closures cannot
        // use $this, so we process the cast raw values here instead.
        $rawStatus = $this->data['payment_status'] ?? null;
        $this->paymentStatusInput = (is_string($rawStatus) || is_int($rawStatus))
            ? PaymentStatus::tryFrom((string) $rawStatus)
            : null;
        $rawPending = $this->data['pending_payment'] ?? null;
        $this->pendingPaymentInput = is_numeric($rawPending) ? (float) $rawPending : null;

        // Handle expired membership — skip payment creation when the CSV
        // explicitly marks the member as expired and the membership end
        // date is already in the past.
        $rawEnd = $gymMember->membership_end;
        $membershipEnd = $rawEnd ? Date::parse($rawEnd) : null;

        if (
            $this->paymentStatusInput === PaymentStatus::EXPIRED
            && $membershipEnd
            && $membershipEnd->isPast()
        ) {
            $gymMember->update([
                'payment_status' => PaymentStatus::EXPIRED->value,
                'latest_payment_at' => null,
            ]);

            return;
        }

        $this->handleMemberPayment();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function calculateMembershipDates(MembershipPlan $plan): array
    {
        if ($this->membershipEndInput) {
            $end = $this->membershipEndInput;
            $start = $end->copy()->subMonths($plan->duration ?? 0);

            return [$start, $end];
        }

        $start = now();
        $end = $start->copy()->addMonths($plan->duration ?? 0);

        return [$start, $end];
    }

    /**
     * @return array{0: float, 1: PaymentStatus}
     */
    protected function calculatePaymentAmounts(float $planPrice): array
    {
        if ($this->pendingPaymentInput !== null) {
            $pendingPayment = max(0.0, $this->pendingPaymentInput);
            $totalPaid = max(0.0, $planPrice - $pendingPayment);

            $paymentStatus = $this->paymentStatusInput ?? match (true) {
                $pendingPayment <= 0 => PaymentStatus::COMPLETED,
                $pendingPayment >= $planPrice => PaymentStatus::PENDING,
                default => PaymentStatus::PARTIAL,
            };

            return [$totalPaid, $paymentStatus];
        }

        return [0.0, $this->paymentStatusInput ?? PaymentStatus::PENDING];
    }

    protected function handleMemberPayment(): void
    {
        /** @var GymMember $gymMember */
        $gymMember = $this->record;

        $plan = $this->membershipPlan;
        if (! $plan) {
            return;
        }

        $planPrice = (float) $plan->price;

        [$totalPaid, $paymentStatus] = $this->calculatePaymentAmounts($planPrice);

        /** @var MemberPayment|null $existingPayment */
        $existingPayment = null;

        if (! $this->isNewRecord) {
            $existingPayment = $gymMember->payments()
                ->where('membership_plan_id', $plan->id)
                ->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::PARTIAL, PaymentStatus::COMPLETED])
                ->lockForUpdate()
                ->latest()
                ->first();
        }

        /** @var Currency $currency */
        $currency = $plan->currency ?? Currency::INR;

        if ($existingPayment) {
            $this->updateExistingPayment($existingPayment, $paymentStatus);

            return;
        }

        $payment = $this->createNewPayment($gymMember, $plan, $paymentStatus, $totalPaid, $planPrice, $currency);

        $gymMember->update([
            'payment_status' => $payment->status?->value,
            'latest_payment_at' => $payment->payment_date,
        ]);
    }

    protected function createNewPayment(
        GymMember $gymMember,
        MembershipPlan $plan,
        PaymentStatus $paymentStatus,
        float $totalPaid,
        float $planPrice,
        Currency $currency
    ): MemberPayment {
        $payment = $gymMember->payments()->create([
            'gym_id' => $this->options['tenantId'],
            'membership_plan_id' => $plan->id,
            'amount' => $planPrice,
            'currency' => $currency,
            'payment_method' => PaymentMethod::CASH,
            'status' => $paymentStatus,
            'notes' => 'Imported via Filament',
            'payment_date' => now(),
            'membership_start' => $gymMember->membership_start,
            'membership_end' => $gymMember->membership_end,
        ]);

        if ($paymentStatus === PaymentStatus::COMPLETED) {
            $payment->transactions()->create([
                'member_payment_id' => $payment->id,
                'amount' => $totalPaid > 0 ? min($totalPaid, $planPrice) : $planPrice,
                'currency' => $currency,
                'payment_method' => PaymentMethod::CASH,
                'transaction_date' => now(),
                'status' => 'completed',
                'notes' => 'Imported via Filament - full payment',
            ]);
        } elseif ($paymentStatus === PaymentStatus::PARTIAL && $totalPaid > 0) {
            $payment->transactions()->create([
                'member_payment_id' => $payment->id,
                'amount' => min($totalPaid, $planPrice),
                'currency' => $currency,
                'payment_method' => PaymentMethod::CASH,
                'transaction_date' => now(),
                'status' => 'completed',
                'notes' => 'Imported via Filament - partial payment',
            ]);
        }

        $payment->syncStatusFromTransactions();

        // Generate invoice number — same format as InvoiceService::ensureInvoiceNumber().
        if (! $payment->invoice_number) {
            $payment->invoice_number = 'INV-' . now()->format('Ymd') . '-' . $payment->gym_id . '-' . $payment->id;
            $payment->saveQuietly();
        }

        return $payment;
    }

    protected function updateExistingPayment(MemberPayment $payment, PaymentStatus $paymentStatus): void
    {
        /** @var GymMember $gymMember */
        $gymMember = $this->record;

        $payment->update([
            'status' => $paymentStatus,
            'notes' => 'Updated via Filament import',
            'payment_date' => now(),
            'membership_start' => $gymMember->membership_start ?? $payment->membership_start,
            'membership_end' => $gymMember->membership_end ?? $payment->membership_end,
        ]);

        $payment->syncStatusFromTransactions();

        $gymMember->update([
            'payment_status' => $payment->status?->value,
            'latest_payment_at' => $payment->payment_date,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your member import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
