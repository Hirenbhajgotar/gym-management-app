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
use App\Models\User;
use App\Services\AvatarService;
use App\Services\MemberArchiveAuthorization;
use App\Services\MemberArchiveService;
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

    private function archiveService(): MemberArchiveService
    {
        return resolve(MemberArchiveService::class);
    }

    private function archiveAuthorization(): MemberArchiveAuthorization
    {
        return resolve(MemberArchiveAuthorization::class);
    }

    /**
     * The operator who dispatched this import, if there is one.
     *
     * This importer is queued, and a queue worker has no session — so the
     * gate cannot fall back to Auth::user() here. BaseImporter records
     * `auth()->id()` against the import row at construction time, which is
     * exactly the attribution the gate needs. The operator is resolved and
     * passed to the gate explicitly rather than written onto the global Auth
     * guard: setUser() is process-global state a worker shares with every
     * other job, is never restored, and would authorise later rows of other
     * imports against whichever operator happened to run first.
     *
     * Anything that genuinely cannot be attributed resolves to null and the
     * gate fails closed, which is the correct outcome: an import nobody can
     * be held responsible for must not be able to retire or revive a member.
     */
    private function resolveOperator(): ?User
    {
        $rawOperatorId = $this->importRecord?->user_id;

        // Accept the id however the driver hands it back: a numeric string from
        // a driver that stringifies fetches is still the same operator. The
        // previous is_int() check tied correctness to a PDO default
        // (ATTR_STRINGIFY_FETCHES=false), so a driver change would have turned
        // every import-driven transition into a silent refusal.
        $operatorId = filter_var($rawOperatorId, FILTER_VALIDATE_INT);

        if ($operatorId === false) {
            return null;
        }

        $operator = User::query()->find($operatorId);

        return $operator instanceof User ? $operator : null;
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

        // Spatie team context for the whole row. A queue worker has no
        // TeamsPermission middleware, so without this the operator's
        // gym-scoped roles are invisible to the archive gate below and it
        // would refuse every import-driven transition in production —
        // inert-strict rather than merely strict. Scoped to the tenant being
        // imported INTO deliberately: importing into a gym requires the
        // permission in that gym, which is also what the gate's per-record
        // tenant check asserts. Restored in the finally because a worker
        // process is shared with every other job.
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($this->tenantId);

            DB::transaction(function () use ($row): void {
                /** @var array<string, mixed> $row */
                $this->processRowInTransaction($row);
            }, 3);
        } finally {
            setPermissionsTeamId($previousTeamId);

            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function processRowInTransaction(array $row): void
    {
        $operator = $this->resolveOperator();

        $membershipPlan = $this->resolveMembershipPlan($row);
        $trainer = $this->resolveTrainer($row);
        $archiveService = $this->archiveService();

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

        // Three-valued on purpose. null means the row states NO expiry decision
        // at all — no plan and no membership_end cell — so an existing member's
        // archive state must be left exactly as it is. Defaulting to "not
        // expired" here is what silently revived an archived member on any
        // re-import whose row omitted the end date: the lookup matches them by
        // phone, the row claims they are live, and the member is restored —
        // gated on `unarchive` for a row that never asked to revive anyone.
        $wantsArchived = null;
        $archivedAt = null;
        $allowMarketing = true;
        $marketingSnapshot = null;
        $explicitMarketingConsent = null;

        if (isset($row['allow_whatsapp_marketing'])) {
            // $allowMarketing = (bool) $row['allow_whatsapp_marketing'];
            $parsed = filter_var(
                $row['allow_whatsapp_marketing'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            // Privacy-safe default: unparseable consent values are treated as opt-OUT.
            $explicitMarketingConsent = $parsed === null ? false : $parsed;
            $allowMarketing = $explicitMarketingConsent;
        }

        if ($calculatedEnd instanceof DateTimeInterface) {
            $endDate = Date::parse($calculatedEnd);
        } elseif (is_string($calculatedEnd) || is_numeric($calculatedEnd)) {
            $endDate = Date::parse((string) $calculatedEnd);
        } else {
            $endDate = null;
        }

        if ($endDate) {
            // Both cutoff VALUES come from MemberArchiveService so all three
            // derivations agree to the day; each still applies that cutoff to its
            // own input — this importer to the row's end date, the Filament
            // importer to its row's resolved term — so the shared cutoff is what
            // keeps them from drifting, not a shared notion of "the" end date.
            $archiveCutoff = $archiveService->cutoffDate()->toDateTime();
            $marketingCutoff = $archiveService->cutoffDate(1)->toDateTime();

            $wantsArchived = $endDate->lt($archiveCutoff);

            if ($wantsArchived) {
                $marketingSnapshot = $allowMarketing;
                $archivedAt = now();
                $allowMarketing = false;
            } elseif ($endDate->lt($marketingCutoff) && ! isset($row['allow_whatsapp_marketing'])) {
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
            'membership_end' => $calculatedEnd,
            'gym_membership_plan_id' => $membershipPlan->id ?? null,
            'trainer_id' => $trainer?->id ?? null,
            'user_id' => Gym::query()->find($this->tenantId)?->owner()->id ?? null,
            'is_archived' => $wantsArchived ?? false,
            'archived_at' => $archivedAt,
            'allow_whatsapp_marketing' => $allowMarketing,
            'whatsapp_marketing_snapshot' => $marketingSnapshot,
        ];

        $existingMember = GymMember::query()
            ->where('gym_id', $this->tenantId)
            ->where('phone', $memberData['phone'])
            ->lockForUpdate()
            ->first();

        if ($existingMember) {
            $gymMember = $this->updateExistingMember($existingMember, $memberData, $row, $wantsArchived, $explicitMarketingConsent, $operator);
            $isNewMember = false;
        } else {
            if ($wantsArchived === true) {
                $this->archiveAuthorization()->assertCanArchive($operator);
            }

            $gymMember = new GymMember();
            $gymMember->fill($memberData);

            // The four archive/consent columns are guarded on GymMember (they are
            // owned by MemberArchiveService), so the create path — the one
            // documented exception to that ownership — sets them explicitly.
            $gymMember->forceFill([
                'is_archived' => $wantsArchived ?? false,
                'archived_at' => $archivedAt,
                'allow_whatsapp_marketing' => $allowMarketing,
                'whatsapp_marketing_snapshot' => $marketingSnapshot,
            ]);

            $gymMember->save();
            $isNewMember = true;
        }

        if ($membershipPlan) {
            $this->handleMemberPayment($gymMember, $membershipPlan, $row, $membershipStart, $membershipEnd, $isNewMember);
        }

        if (isset($row['address'])) {
            $this->handleAddress($gymMember, $row);
        }
    }

    /**
     * Apply a re-imported row to the member it resolves to.
     *
     * A phone number that already belongs to an archived member has to reach
     * here: rules() lets it through precisely so the row can revive the member
     * instead of colliding with the unique index on (gym_id, phone). The lookup
     * that found the member is keyed on phone alone, because an archived
     * member's email may have moved on since they were retired.
     *
     * Both transitions are gated, and the revive gate is not optional. Import is
     * a permission a receptionist and a trainer hold; unarchive is not. Without
     * the gate below, either of them could return a deliberately revoked member
     * to the roster — restoring their WhatsApp consent from the archive
     * snapshot and handing door access back — purely by uploading a
     * spreadsheet.
     *
     * The permission is decided BEFORE any write. The DB transaction would roll
     * the row back, but model events are not transactional —
     * GymMemberObserver clears the biometric summary cache off every save —
     * so an update that precedes a refusal leaves side effects the rollback
     * never touches.
     *
     * @param  array<string, mixed>  $memberData
     * @param  array<string, mixed>  $row
     */
    private function updateExistingMember(
        GymMember $gymMember,
        array $memberData,
        array $row,
        ?bool $wantsArchived,
        ?bool $explicitMarketingConsent,
        ?User $operator
    ): GymMember {
        $archiveService = $this->archiveService();

        // The persisted flag, read before anything is written. The payload
        // below never carries the archive columns, so this is the member's
        // state in the database however the row found them.
        $wasArchived = $gymMember->is_archived;

        // Gate both real transitions before any write (see docblock). The
        // record is passed to the check so the policy's per-record tenant
        // branch runs on the import paths too, not only in the panel. A null
        // $wantsArchived means the row expressed no expiry decision, so there
        // is no transition to gate and the archive state is left untouched.
        if ($wantsArchived === true && ! $wasArchived) {
            $this->archiveAuthorization()->assertCanArchive($operator, $gymMember);
        } elseif ($wantsArchived === false && $wasArchived) {
            $this->archiveAuthorization()->assertCanUnarchive($operator, $gymMember);
        }

        $payload = $this->buildUpdatePayload($memberData, $row);

        // A revive decided from the row's plan — no end cell, so the end came
        // from the plan's duration — has to persist the end it was revived
        // against. Without it the restored member comes back still expired:
        // canAccessGym() keeps denying them and the next 23:00 archive sweep
        // re-archives them, silently undoing the operator's instruction.
        if ($wantsArchived === false && $wasArchived && ! array_key_exists('membership_end', $payload)) {
            $payload['membership_end'] = $memberData['membership_end'];
        }

        $gymMember->update($payload);

        // Both transitions go through MemberArchiveService, which locks and
        // re-reads the row — so the consent snapshot it writes is the one
        // actually persisted, archived_at is never restamped by a re-import,
        // and a concurrent writer cannot be double-counted.
        if ($wantsArchived === true) {
            if (! $wasArchived) {
                $archiveService->archive($gymMember, $operator);
            }
        } elseif ($wantsArchived === false) {
            if ($wasArchived) {
                $archiveService->unarchive($gymMember, $operator);
            }
        }

        // The row's explicit consent is the operator's statement of the
        // member's decision. Where the member ends up archived the flag is
        // forced false and the snapshot is its only legitimate home — so an
        // eventual unarchive restores the decision the operator last stated
        // rather than silently discarding it. Where they end up (or stay) live
        // it is an ordinary write on a live member that outranks the snapshot.
        if ($explicitMarketingConsent !== null) {
            $stillArchived = $wantsArchived ?? $wasArchived;

            if ($stillArchived) {
                $archiveService->recordConsentWhileArchived($gymMember, $explicitMarketingConsent);
            } else {
                $gymMember->forceFill(['allow_whatsapp_marketing' => $explicitMarketingConsent])->save();
            }
        }

        return $gymMember->refresh();
    }

    /**
     * Build the column updates a re-imported row is allowed to write.
     *
     * Each column is written only when the row carried the matching key, so a
     * sparse CSV cannot blank out fields it never supplied. The four archive
     * columns are deliberately absent — is_archived, archived_at,
     * allow_whatsapp_marketing and whatsapp_marketing_snapshot belong to
     * MemberArchiveService alone, which is what makes the consent snapshot
     * round-trip safely.
     *
     * membership_end is present and is what makes the revive path hold. The
     * decision to revive is taken from the row's end date, so the end date has
     * to be persisted with it: without it a revived member comes back still
     * expired, canAccessGym() keeps denying them, and the next 23:00 archive
     * sweep re-archives the member the import just restored — silently undoing
     * the operator's explicit instruction on the very next run.
     *
     * @param  array<string, mixed>  $memberData
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildUpdatePayload(array $memberData, array $row): array
    {
        // gym_membership_plan_id and trainer_id are named by the CSV keys that
        // feed them rather than by their own column names.
        $rowKeysByColumn = [
            'name' => 'name',
            'email' => 'email',
            'weight' => 'weight',
            'height' => 'height',
            'age' => 'age',
            'dob' => 'dob',
            'membership_start' => 'membership_start',
            'membership_end' => 'membership_end',
            'gym_membership_plan_id' => 'membership_plan',
            'trainer_id' => 'trainer_email',
        ];

        $payload = [];

        foreach ($rowKeysByColumn as $column => $rowKey) {
            if (array_key_exists($rowKey, $row)) {
                // $memberData carries every column this map can name, so a
                // silent ?? null here would only ever hide a future key typo
                // by writing null over a column the row never spoke about.
                $payload[$column] = $memberData[$column];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveMembershipPlan(array $row): ?MembershipPlan
    {
        if (! isset($row['membership_plan'])) {
            return null;
        }

        // Scoped to the tenant: matching on name alone lets a row bind another
        // gym's plan, and its duration and price feed this member's payment
        // ledger — the same cross-tenant leak resolveTrainer() already closes.
        $membershipPlan = MembershipPlan::query()
            ->where('gym_id', $this->tenantId)
            ->where('name', $row['membership_plan'])
            ->first();

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

        // Scoped to the tenant: without the gym_id predicate a row could
        // assign a trainer belonging to another gym to this gym's member.
        return Trainer::query()
            ->where('gym_id', $this->tenantId)
            ->where('email', $row['trainer_email'])
            ->first();
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

    /**
     * Validation rules applied to every CSV row before it is processed.
     *
     * The phone rule only rejects a number still held by a member on the
     * current roster. An archived member keeps the number reserved through the
     * unique index on (gym_id, phone), so failing the row here as a duplicate
     * would make that member impossible to re-import — and re-importing them
     * with a current membership_end is exactly how a retired member is revived.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => [
                'required',
                'max:20',
                Rule::unique('gym_members', 'phone')
                    ->where('gym_id', $this->tenantId)
                    ->where('is_archived', false),
            ],
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
            'trainer_email' => ['nullable', 'string', Rule::exists('trainers', 'email')->where('gym_id', $this->tenantId)],
        ];
    }
}
