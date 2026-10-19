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
use App\Models\User;
use App\Services\AvatarService;
use App\Services\InvoiceService;
use App\Services\MemberArchiveAuthorization;
use App\Services\MemberArchiveService;
use Date;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Imports gym members from a spreadsheet and derives their archive state.
 *
 * Archive state is DERIVED here, from the row's membership term (the plan's
 * duration or an explicit Membership End cell), and is not a column an operator
 * supplies. That is deliberate: an `Is Archived` column would be a second way
 * to flip the flag, on a path authorised by `import_gym_member` rather than by
 * `archive`/`unarchive`, which is the exact escalation the gate below exists to
 * prevent. If you need to archive or revive a member, the panel bulk actions and
 * MemberArchiveService own it.
 *
 * Existing records transition through MemberArchiveService, which stays the
 * single writer of the four archive columns: it locks and re-reads the row,
 * snapshots the persisted consent, and refuses a double transition — so a
 * re-import can neither restamp archived_at nor overwrite the stored
 * pre-archive consent. A row that creates a member cannot transition (there
 * is no stored row to lock), so a member born archived is written in memory
 * and persisted by Filament's own save, exactly like the CSV importer's
 * create path.
 *
 * Both transitions are gated on the operator's own permission, in both
 * directions. The operator is the user who started this import — the import's
 * own user_id, resolved explicitly rather than read off the ambient guard.
 * beforeSave() also establishes the Spatie team context for the gym being
 * imported into: Filament's ImportCsv job logs the operator in, but no
 * TeamsPermission middleware runs on a worker, so without it the operator's
 * gym-scoped roles are invisible and the gate would refuse every
 * import-driven transition in production.
 */
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

    /**
     * Whether the member was already archived in the database when this row
     * was resolved.
     *
     * Captured at resolve time — the last moment the record is exactly what the
     * database stored, before beforeSave() can begin a transition on it — so the
     * "was this member archived before this row?" question the revive gate
     * depends on has one unambiguous answer. Archive state is derived, not
     * imported (there is no is_archived column), so the row cannot overwrite the
     * flag; reading it here keeps that precondition explicit rather than relying
     * on the column's continued absence.
     */
    protected bool $wasArchived = false;

    private function archiveService(): MemberArchiveService
    {
        return resolve(MemberArchiveService::class);
    }

    private function archiveAuthorization(): MemberArchiveAuthorization
    {
        return resolve(MemberArchiveAuthorization::class);
    }

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

            // ignoreBlankState() keeps a present-but-blank cell from being
            // assigned onto the record at all, and beforeSave() only recomputes
            // the membership dates for a new record or a row that supplies an
            // end date. Both halves are load-bearing: without them a blank cell
            // used to be overwritten with a recomputed now()..now()+duration —
            // which for an archived member also flipped the row onto the live
            // branch below, a silent revive gated on `unarchive` rather than the
            // no-op a blank cell means.
            ImportColumn::make('membership_end')
                ->label('Membership End')
                ->ignoreBlankState()
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

            // ignoreBlankState() on the consent column stops a blank cell from
            // clearing a flag the row never spoke about.
            ImportColumn::make('allow_whatsapp_marketing')
                ->label('Allow WhatsApp Marketing')
                ->boolean()
                ->ignoreBlankState()
                ->rules(['nullable', 'boolean'])
                ->guess(['whatsapp_marketing', 'allow_whatsapp_marketing', 'whatsapp marketing']),
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
            // Capture the persisted flag at the last moment the record still
            // reflects exactly what the database stored — resolveRecord() runs
            // before fillRecord()/beforeSave() do any of their work. It is read
            // here rather than in beforeSave() so the answer does not depend on
            // the current absence of an `is_archived` import column: archive
            // state is derived, and deriving it may not silently reintroduce the
            // "the row overwrote the flag" assumption the snapshot guard needs to
            // be free of.
            $this->wasArchived = $record->is_archived;

            return $record;
        }

        $this->wasArchived = false;

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

        // Recompute the record's own dates only when the row actually asks for
        // it: a NEW record has no stored dates to keep, and a supplied end date
        // is the row's own decision. A blank or unmapped Membership End cell on
        // an EXISTING record keeps the stored dates, which is the contract the
        // column's ignoreBlankState() states.
        if ($this->membershipPlan && ($this->isNewRecord || $this->membershipEndInput)) {
            [$start, $end] = $this->calculateMembershipDates($this->membershipPlan);
            $record->membership_start = $start;
            $record->membership_end = $end;
        }

        // The archive DECISION is taken from the ROW, never from the stored end.
        // The row is authoritative: a plan names a term (its duration) and an
        // end cell names one. This importer's plan column is a required mapping,
        // so a successful row always names a term and $rowEnd is always a date;
        // the null guard below is defensive, not a reachable row shape here.
        // Deriving the decision from the stored end instead — as this importer
        // used to — made the two importers disagree on the same row shape: a
        // plan-only row archived a live member whose stored end was stale here
        // while reviving them on the CSV importer. See MemberArchiveService for
        // the single statement of the row-authority rule this now shares with
        // the CSV path.
        $rowEnd = $this->membershipPlan
            ? $this->calculateMembershipDates($this->membershipPlan)[1]
            : $this->membershipEndInput;

        // The cutoff comes from MemberArchiveService so this importer and the
        // batch command agree to the day, not to the second: cutoffDate() starts
        // the comparison at midnight, which a local now()->subYears(2) would not.
        $archiveCutoff = $this->archiveService()->cutoffDate()->toDateTime();

        $rowConsent = $this->rowSuppliedConsentValue();
        $wantsArchived = $rowEnd instanceof Carbon ? $rowEnd->lt($archiveCutoff) : null;

        // Spatie team context for the gated section. Filament's ImportCsv job
        // logs the operator in, but no TeamsPermission middleware runs on a
        // worker, so without this their gym-scoped roles are invisible and the
        // gate refuses every transition — inert-strict rather than merely
        // strict. Restored in the finally because the importer instance is
        // reused across every row of a queued job.
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($tenantId);

            if ($wantsArchived === true) {
                $this->applyArchivedState($record, $rowConsent);
            } elseif ($wantsArchived === false) {
                // A revive decided from the row's plan — no end cell, so the term
                // came from the plan's duration — has to persist the end it was
                // revived against. Without it the restored member comes back
                // expired: canAccessGym() keeps denying them and the next 23:00 archive
                // sweep re-archives them, silently undoing the operator's
                // instruction. This mirrors the CSV importer's revive path, and
                // only fires on a real transition (a live member's stored dates
                // are left alone, matching the ignoreBlankState() contract above).
                if ($this->wasArchived && $this->membershipEndInput === null && $this->membershipPlan) {
                    [$rowStart, $rowEndValue] = $this->calculateMembershipDates($this->membershipPlan);
                    $record->membership_start = $rowStart;
                    $record->membership_end = $rowEndValue;
                }

                $this->restoreArchivedConsent($record, $rowConsent);
            }
        } catch (AuthorizationException $exception) {
            // Filament's ImportCsv job records a RowImportFailedException with
            // its message on the failed row. A bare AuthorizationException
            // falls through to its generic Throwable branch, which records an
            // empty validation_error and then fails the whole queued chunk.
            // Translating here is what puts the refusal reason in front of the
            // operator in the failed-rows report.
            throw new RowImportFailedException($exception->getMessage());
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * The row's own marketing-consent decision, or null when it states none.
     *
     * A blank cell casts to null and `false`/`0` cast to a real boolean, so
     * only a filled value counts as the row stating a decision of its own.
     */
    protected function rowSuppliedConsentValue(): ?bool
    {
        if (! array_key_exists('allow_whatsapp_marketing', $this->data)) {
            return null;
        }

        $state = $this->data['allow_whatsapp_marketing'];

        return blank($state) ? null : (bool) $state;
    }

    /**
     * The operator who started this import, resolved explicitly.
     *
     * Filament's ImportCsv job does `auth()->login($this->import->user)` before
     * any row is processed, so Auth::user() happens to hold the same person on a
     * real run. The import's own user_id is still the authoritative attribution
     * and the ambient guard is deliberately NOT consulted: the guard is per-job
     * process state a row-level importer has no business depending on, and on a
     * shared worker a leaked identity would authorise a later, less privileged
     * import against the previous operator. An import that cannot be attributed
     * fails closed in the gate rather than borrowing whoever happens to be
     * logged in.
     */
    protected function importOperator(): ?User
    {
        $operator = $this->import->user;

        return $operator instanceof User ? $operator : null;
    }

    /**
     * A detached re-read of the record for MemberArchiveService to act on.
     *
     * archive()/unarchive() synchronise the instance they are handed through
     * setRawAttributes(), which replaces the whole attribute bag — the right
     * contract for the batch action, and the wrong one here: beforeSave() runs
     * BEFORE Filament saves the record, so the attributes the import row filled
     * are exactly what must survive to that save. Handing the service its own
     * copy keeps it the single writer of the archive columns while this
     * importer's record keeps carrying the row; and because the archive columns
     * are not dirty on that record, Filament's own save cannot write them back
     * over the transition the service just made.
     */
    protected function serviceTarget(GymMember $record): GymMember
    {
        /** @var GymMember $target */
        $target = GymMember::query()->whereKey($record->getKey())->firstOrFail();

        return $target;
    }

    /**
     * Put the member into the archived state, snapshotting consent first.
     *
     * Both halves of the "only on the transition" rule are inherited from
     * MemberArchiveService: re-importing a member who is already archived must
     * not overwrite the stored pre-archive consent (archiving already forced it
     * false, so re-snapshotting would permanently lose the real decision and
     * leave unarchive() nothing to restore), and must not restamp archived_at —
     * that timestamp answers "when was this member retired", and a re-import is
     * not a retirement.
     *
     * For the same reason the permission is required only when a member is
     * actually being archived for the first time. Re-confirming a state the row
     * is already in writes nothing new, and demanding archive of anyone
     * maintaining a spreadsheet of already-archived members would make the
     * ordinary case impossible for the roles that legitimately do it.
     *
     * @param  ?bool  $rowConsent  The row's explicit consent decision, when it states one
     *
     * @throws RowImportFailedException When the operator may not archive gym members
     */
    protected function applyArchivedState(GymMember $record, ?bool $rowConsent = null): void
    {
        if ($this->isNewRecord) {
            // Nothing to transition: the row does not exist yet, so there is no
            // stored consent to snapshot and no archived_at to restamp. This is
            // a member born archived, and Filament's save persists the four
            // columns set here — the CSV importer's create path is the same
            // shape.
            $this->archiveAuthorization()->assertCanArchive($this->importOperator());

            // Mirrors the column default: a row that never carried the column
            // has no in-memory value yet, and reading that null would snapshot
            // "opted out" for a member who never did.
            $record->whatsapp_marketing_snapshot = $rowConsent ?? ($record->allow_whatsapp_marketing ?? true);
            $record->archived_at = now();
            $record->is_archived = true;
            $record->allow_whatsapp_marketing = false;

            return;
        }

        if (! $this->wasArchived) {
            $this->archiveAuthorization()->assertCanArchive($this->importOperator(), $record);

            $target = $this->serviceTarget($record);

            $this->archiveService()->archive($target, $this->importOperator());

            // The row's own consent decision is the operator's statement of what
            // the member decided. While the member stays archived the flag is
            // forced false, so the snapshot the service owns is the only
            // legitimate home for it — without this the stated decision would be
            // silently discarded and lost for good at unarchive.
            if ($rowConsent !== null) {
                $this->archiveService()->recordConsentWhileArchived($target, $rowConsent);
            }

            $this->mirrorArchivedState($record, $target);
        } elseif ($rowConsent !== null) {
            $target = $this->serviceTarget($record);

            $this->archiveService()->recordConsentWhileArchived($target, $rowConsent);

            $this->mirrorArchivedState($record, $target);
        }
    }

    /**
     * Copy the service's persisted archive state onto the row's own instance.
     *
     * fillRecord() has already assigned the import row's values — including an
     * explicit marketing consent — so those attributes are DIRTY, and Filament
     * saves the record immediately after beforeSave() returns. Leaving them
     * alone means the save writes the row's opt-in straight back over the
     * forced-false flag the service just persisted: is_archived = true together
     * with allow_whatsapp_marketing = true, the exact state the service declares
     * impossible. Mirroring writes the same values the service stored, so the
     * save is a no-op for these four columns.
     */
    private function mirrorArchivedState(GymMember $record, GymMember $persisted): void
    {
        $record->is_archived = $persisted->is_archived;
        $record->archived_at = $persisted->archived_at;
        $record->allow_whatsapp_marketing = $persisted->allow_whatsapp_marketing;
        $record->whatsapp_marketing_snapshot = $persisted->whatsapp_marketing_snapshot;
    }

    /**
     * Bring a member this import keeps live out of the archived state.
     *
     * The transition goes through MemberArchiveService::unarchive(), which
     * restores the consent from the snapshot under a row lock — the same round
     * trip the panel's own unarchive performs, rather than this importer
     * reimplementing it column by column.
     *
     * The row's own consent decision outranks the snapshot: the member is live
     * again once the service returns, so writing the flag is an ordinary save
     * Filament is about to make, not a write into the window while which the
     * service owns the column. Consent is never invented in either direction —
     * a row carrying no decision keeps whatever the snapshot recorded.
     *
     * Nothing happens when the member was never archived: there is no
     * transition to make, and demanding unarchive for re-confirming a live
     * member would fail ordinary imports of live members for anyone without it.
     *
     * @param  ?bool  $rowConsent  The row's explicit consent decision, when it states one
     *
     * @throws RowImportFailedException When the operator may not restore archived gym members
     */
    protected function restoreArchivedConsent(GymMember $record, ?bool $rowConsent = null): void
    {
        if (! $this->wasArchived) {
            return;
        }

        $this->archiveAuthorization()->assertCanUnarchive($this->importOperator(), $record);

        $this->archiveService()->unarchive($this->serviceTarget($record), $this->importOperator());

        if ($rowConsent !== null) {
            $record->allow_whatsapp_marketing = $rowConsent;
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

        // Generate invoice number via InvoiceService for guaranteed parity.
        resolve(InvoiceService::class)->ensureInvoiceNumber($payment);

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
