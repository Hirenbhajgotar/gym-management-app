<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use App\Models\User;
use App\Traits\AssertsTransactionalAuditSink;
use App\Traits\GuardsArchivedOwner;
use App\Traits\HasTenantAccess;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Biometric templates enrolled by a gym member.
 *
 * Archiving a member is an access revocation (see MemberArchiveService for the
 * single statement of that policy), so the credential that still opens the door
 * has to be erased with the record — and de-provisioning has to remain possible
 * afterwards, because the credential may already be on the hardware and the
 * pending-deletion record is what an auditor cannot otherwise explain. Reads
 * stay open because template history is the audit trail of what was enrolled
 * and when it was erased; {@see GuardsArchivedOwner} closes mutation only.
 */
class BiometricTemplatesRelationManager extends RelationManager
{
    use AssertsTransactionalAuditSink;
    use GuardsArchivedOwner;
    use HasTenantAccess;

    protected static string $relationship = 'biometricTemplates';

    protected static ?string $icon = 'heroicon-o-finger-print';

    protected function archivedOwnerSubject(): string
    {
        return 'Biometric templates';
    }

    protected function archivedOwnerNoticeBody(): string
    {
        return 'Biometric templates stay visible as the record of what was enrolled. De-provisioning remains available because it only removes access, but no template can be created until the member is restored.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['enrolledByDevice', 'gymMember']))
            ->header(fn () => $this->archivedOwnerHeader())
            ->columns([
                TextColumn::make('biometric_type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('sync_status')
                    ->label('Sync Status')
                    ->badge()
                    ->formatStateUsing(fn ($state, BiometricTemplate $record): string => $record->getSyncStatusBadgeLabel())
                    ->color(fn ($state, BiometricTemplate $record): string => $record->getSyncStatusBadgeColor()),

                TextColumn::make('enrolledByDevice.device_name')
                    ->label('Enrolled By Device')
                    ->placeholder('Device Native / ADMS'),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Inactive'),

                TextColumn::make('erasure_attempts')
                    ->label('Erasure Attempts')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->placeholder('0')
                    ->tooltip('Provider-side erase attempts recorded against this row.'),

                TextColumn::make('enrolled_at')
                    ->label('Enrolled At')
                    ->dateTime(),
            ])
            ->filters([
                Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true)),

                // The operator surface for an erasure the system cannot discharge by itself.
                // It covers BOTH producers: a terminal `DeletionUnresolvable` row (no provider
                // reference, or a provider that outlasted its cooldown) and a hardware row whose
                // `user_delete` answered and rejected the delivery cap — the latter is a state
                // the template does not record, so the predicate reads the command log. The PIN
                // is only resolvable in PHP (the reference is encrypted), so the filter selects
                // the candidates and then narrows by the row's own predicate.
                Filter::make('needs_operator')
                    ->label('Needs Operator')
                    ->query(function (Builder $query): Builder {
                        /** @var array<int, int> $ids */
                        $ids = [];

                        $candidates = (clone $query)
                            ->where(function (Builder $query): void {
                                // State candidates plus any row carrying the durable
                                // obligation flag: a flag that survives into a state the
                                // candidate set does not name must still be visible, or the
                                // obligation outlives the operator surface.
                                $query->whereIn('sync_status', BiometricTemplateSyncStatusEnum::operatorAttentionCandidateStates())
                                    ->orWhere('is_erasure_obligation', true);
                            })
                            ->get();

                        foreach ($candidates as $candidate) {
                            if (! $candidate instanceof BiometricTemplate) {
                                continue;
                            }

                            if (! $candidate->requiresOperatorAttention()) {
                                continue;
                            }

                            $id = $candidate->getKey();

                            if (is_numeric($id)) {
                                $ids[] = (int) $id;
                            }
                        }

                        return $query->whereIn('id', $ids === [] ? [-1] : $ids);
                    }),
            ])
            ->actions([
                Action::make('deprovision')
                    ->label('De-provision')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('De-provision this template')
                    ->modalDescription('This requests erasure of the biometric credential from the hardware device, and records the erasure as an explicit request so a later re-enrolment is auditable as an override of that request. The record is retained and moves to "Pending Hardware Deletion" until the terminal confirms removal.')
                    // Deliberately NOT gated on isOwnerArchived(): de-provisioning only
                    // removes access, so it stays available for an archived member. That
                    // is the operator's only manual escape hatch for a template the
                    // automated paths cannot erase (a provider outage, a terminal that
                    // never ACKs, an unresolvable obligation), and the class docblock
                    // promises it remains possible. The member relation is required
                    // rather than degraded to the class-name fallback: the table query
                    // eager-loads it, and the fallback would skip the per-member tenant
                    // branch of `enrollBiometric` entirely.
                    ->authorize(function (BiometricTemplate $record): bool {
                        $actor = auth()->user();

                        return $actor !== null
                            && $record->gymMember !== null
                            && $actor->can('enrollBiometric', $record->gymMember);
                    })
                    ->action(function (BiometricTemplate $record) {
                        $success = resolve(BiometricDataDeletionService::class)->deleteSingleTemplate($record);

                        if (! $success) {
                            Notification::make()
                                ->title('De-provisioning Failed')
                                ->body('The provider did not confirm the template deletion. Try again, or contact support if it keeps failing.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->refresh();

                        match ($record->sync_status) {
                            BiometricTemplateSyncStatusEnum::Deleted => Notification::make()
                                ->title('Template De-provisioned')
                                ->body('The credential was never written to the device, or the device/provider confirmed its removal.')
                                ->success()
                                ->send(),
                            BiometricTemplateSyncStatusEnum::DeletionUnresolvable => Notification::make()
                                ->title('Erasure Requires an Operator')
                                ->body($this->deletionUnresolvableNoticeBody($record))
                                ->warning()
                                ->send(),
                            default => Notification::make()
                                ->title('De-provision Requested')
                                ->body('Erasure is pending confirmation from the device or provider. The record is retained for audit.')
                                ->success()
                                ->send(),
                        };
                    }),

                // The exit for a dead-lettered credential whose terminal is still registered.
                // The discharge is deliberately limited to detached rows, so the operator
                // must first retire the terminal through its audited path; that parks the
                // credential as a detached obligation and makes the discharge available.
                // Erasure must remain possible after a member is archived, so this action is
                // explicitly exempt from GuardsArchivedOwner, like De-provision.
                Action::make('retire_terminal')
                    ->label('Retire Terminal')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Retire the enrolling terminal')
                    ->modalDescription(fn (BiometricTemplate $record): string => 'Deleting the terminal is the audited path that parks its credentials as detached erasure obligations. This terminal will park '
                        . ($record->enrolledByDevice?->parkedTemplateCount() ?? 0)
                        . ' enrolled credential(s) across all members, not only this one. Once it is retired, "Confirm Physical Erasure" becomes available for this row.')
                    ->visible(fn (BiometricTemplate $record): bool => $record->is_erasure_obligation
                        && ! $record->hasCloudProvider()
                        && $record->enrolled_by_device_id !== null)
                    // Deletion of a terminal is the vehicle, but the compliance decision is the
                    // erasure attestation this action enables. Gate it on the discharge authority
                    // (MANAGER+) rather than the device-delete permission: a GYM_OWNER may attest
                    // an erasure by role but has no device-delete permission by default, so the
                    // old gate left the owner without the only exit for a bound obligation.
                    ->authorize(function (BiometricTemplate $record): bool {
                        $actor = auth()->user();

                        return $actor !== null
                            && $record->gymMember !== null
                            && $actor->can('dischargeBiometricErasure', $record->gymMember);
                    })
                    ->action(function (BiometricTemplate $record): void {
                        $device = $record->enrolledByDevice;

                        if ($device === null) {
                            Notification::make()
                                ->title('Terminal Not Found')
                                ->body('The enrolling terminal no longer exists. Refresh the table and confirm the physical erasure instead.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            if ($device->delete() !== true) {
                                Notification::make()
                                    ->title('Terminal Was Not Retired')
                                    ->body('The deletion was refused. Refresh the table and retry, or contact support if it keeps failing.')
                                    ->danger()
                                    ->send();

                                return;
                            }
                        } catch (Throwable $exception) {
                            Log::error('BiometricTemplatesRelationManager: terminal retirement failed', [
                                'template_id' => $record->getKey(),
                                'device_id' => $device->getKey(),
                                'error_type' => $exception::class,
                            ]);

                            Notification::make()
                                ->title('Terminal Was Not Retired')
                                ->body('The terminal could not be deleted, so no credentials were parked. Refresh the table and retry; if it keeps failing, ask an administrator to check the audit sink and the terminal record.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->refresh();

                        Notification::make()
                            ->title('Terminal Retired')
                            ->body('The credential is now a detached erasure obligation. Confirm the physical wipe once it is done.')
                            ->success()
                            ->send();
                    }),

                // The audited discharge for a credential the operator has physically
                // wiped. Without it `is_erasure_obligation` has no exit: the row is
                // parked forever and a later enrolment is blocked from superseding it.
                //
                // Scope and authority are deliberately wider than the neighbouring
                // actions' gate on purpose. A discharge writes a terminal `Deleted` and
                // clears the only marker that the credential may still be live, so it is
                // limited to rows no automated path can reach (hardware-backed, no bound
                // terminal — see BiometricTemplate::canBeDischargedByOperator()) and to
                // MANAGER+ (GymMemberPolicy::dischargeBiometricErasure()). A receptionist
                // may enrol, but may not attest an erasure.
                //
                // Erasure must remain possible after a member is archived, so this action is
                // explicitly exempt from GuardsArchivedOwner, like De-provision.
                Action::make('confirm_physical_erasure')
                    ->label('Confirm Physical Erasure')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm physical erasure')
                    ->modalDescription('Only confirm after the biometric credential has been physically wiped from the terminal. This is irreversible: it clears the erasure obligation, converges the record to Deleted, and records the discharge for audit.')
                    ->visible(fn (BiometricTemplate $record): bool => $record->canBeDischargedByOperator())
                    ->authorize(function (BiometricTemplate $record): bool {
                        $actor = auth()->user();

                        return $actor !== null
                            && $record->gymMember !== null
                            && $actor->can('dischargeBiometricErasure', $record->gymMember);
                    })
                    ->action(function (BiometricTemplate $record): void {
                        $actor = auth()->user();

                        if ($actor === null) {
                            Notification::make()
                                ->title('Erasure Cannot Be Confirmed')
                                ->body('You are no longer signed in. Sign in again and retry.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Re-checked inside the action: visibility is a render-time hint, and
                        // the claim this writes is irreversible.
                        if (! $record->canBeDischargedByOperator()
                            || $record->gymMember === null
                            || ! $actor->can('dischargeBiometricErasure', $record->gymMember)) {
                            Notification::make()
                                ->title('Erasure Cannot Be Confirmed')
                                ->body('This record is not an outstanding hardware erasure you are authorised to discharge. Cloud-backed rows are erased through their provider, and a row still bound to a terminal is erased through the terminal.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            // The compare-and-set lives in the model, so the panel and a direct
                            // test exercise the same guard: a stale render or a second click
                            // affects zero rows and writes no second audit entry.
                            $discharged = $record->dischargeIfFlagged($actor);
                        } catch (Throwable $exception) {
                            Log::error('BiometricTemplatesRelationManager: physical erasure discharge rolled back', [
                                'template_id' => $record->getKey(),
                                'error_type' => $exception::class,
                            ]);

                            Notification::make()
                                ->title('Erasure Cannot Be Confirmed')
                                ->body('The erasure was not recorded; no change was committed. Try again once the audit sink is available.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $discharged) {
                            Notification::make()
                                ->title('Already Discharged')
                                ->body('The erasure obligation had already been cleared; no further audit entry was written.')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Physical Erasure Confirmed')
                            ->body('The erasure obligation was discharged and the record converged to Deleted.')
                            ->success()
                            ->send();
                    }),

                // The only exit for a cloud credential the provider may still hold but that no
                // reference can address. The provider API cannot erase it and the reconciler
                // never selects it, so the honest route is an audited human attestation — not a
                // green "retry" for an erase that cannot run and not a permanent gym-delete veto.
                Action::make('attest_remote_erasure')
                    ->label('Attest Remote Erasure')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Attest that the provider credential is gone')
                    ->modalDescription('Use this only after confirming in the provider console that this member\'s biometric credential no longer exists. It clears the erasure obligation and records your attestation; it does not call the provider.')
                    ->visible(fn (BiometricTemplate $record): bool => $record->canAttestCloudErasure())
                    ->authorize(function (BiometricTemplate $record): bool {
                        $actor = auth()->user();

                        return $actor !== null
                            && $record->gymMember !== null
                            && $actor->can('dischargeBiometricErasure', $record->gymMember);
                    })
                    ->action(function (BiometricTemplate $record): void {
                        $actor = auth()->user();

                        if ($actor === null) {
                            Notification::make()
                                ->title('Erasure Cannot Be Attested')
                                ->body('You are no longer signed in. Sign in again and retry.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Visibility is a render-time hint; the claim is irreversible.
                        if (! $record->canAttestCloudErasure()
                            || $record->gymMember === null
                            || ! $actor->can('dischargeBiometricErasure', $record->gymMember)) {
                            Notification::make()
                                ->title('Erasure Cannot Be Attested')
                                ->body('This record is not a cloud erasure with no provider reference that you are authorised to attest. Repair the provider credential and use "Retry Erasure" when a reference exists.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $discharged = $record->dischargeIfFlagged(
                                $actor,
                                'Biometric cloud erasure attested by operator (no provider reference)'
                            );
                        } catch (Throwable $exception) {
                            Log::error('BiometricTemplatesRelationManager: cloud erasure attestation rolled back', [
                                'template_id' => $record->getKey(),
                                'error_type' => $exception::class,
                            ]);

                            Notification::make()
                                ->title('Erasure Cannot Be Attested')
                                ->body('The attestation was not recorded; no change was committed. Try again once the audit sink is available.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $discharged) {
                            Notification::make()
                                ->title('Already Discharged')
                                ->body('The erasure obligation had already been cleared; no further audit entry was written.')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Remote Erasure Attested')
                            ->body('The obligation was discharged and the record converged to Deleted. Your attestation is in the audit log.')
                            ->success()
                            ->send();
                    }),

                // The exit for a terminal state. Without it a cap-exhausted row is permanent:
                // the cloud cooldown has to be cleared, and a hardware row's delivery counter
                // lives on its `user_delete`, so no single template write re-arms it.
                //
                // Erasure must remain possible after a member is archived, so this action is
                // explicitly exempt from GuardsArchivedOwner, like De-provision.
                Action::make('retry_erasure')
                    ->label('Retry Erasure')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry this erasure')
                    ->modalDescription('Clears the recorded cooldown so the credential is driven through its provider or terminal again. Use it after repairing the provider credential or the device firmware.')
                    // A blank-reference cloud row has nothing to erase with, so a retry could
                    // only report a green success for an erasure it cannot attempt.
                    ->visible(fn (BiometricTemplate $record): bool => $record->requiresOperatorAttention() && ! ($record->hasCloudProvider() && blank($record->template_ref)))
                    // Re-driving a dead-lettered ledger row is the same authority as the
                    // ledger's own resend action, so a hardware row must not be reachable by a
                    // role the ledger policy would refuse. A cloud row has no ADMS ledger row,
                    // so that permission is unrelated and must not gate its retry.
                    ->authorize(function (BiometricTemplate $record): bool {
                        $actor = auth()->user();

                        if ($actor === null
                            || $record->gymMember === null
                            || ! $actor->can('enrollBiometric', $record->gymMember)) {
                            return false;
                        }

                        return $record->hasCloudProvider() || $actor->can('resend', AdmsDeviceCommand::class);
                    })
                    ->action(function (BiometricTemplate $record): void {
                        $actor = auth()->user();
                        $previousAttempts = $record->erasure_attempts;
                        $previousFirstAttemptedAt = $record->erasure_first_attempted_at?->toIso8601String();
                        $commandRequeued = false;

                        try {
                            DB::transaction(function () use ($record, $actor, $previousAttempts, $previousFirstAttemptedAt, &$commandRequeued): void {
                                $this->assertAuditSinkIsTransactional();

                                $record->forceFill([
                                    'erasure_attempts' => 0,
                                    'erasure_first_attempted_at' => null,
                                ])->save();

                                if ($record->enrolled_by_device_id !== null) {
                                    // Scoped to this row's gym as well as its device: device ids
                                    // are not guaranteed globally unique across a restored or
                                    // sharded database, and a cross-tenant command must never
                                    // be re-armed here.
                                    $command = AdmsDeviceCommand::query()
                                        ->where('gym_id', $record->gym_id)
                                        ->where('device_id', $record->enrolled_by_device_id)
                                        ->where('command_type', 'user_delete')
                                        ->where('command_payload->Pin', $record->resolvedCommandPin())
                                        ->where('status', AdmsCommandStatusEnum::FAILED)
                                        ->first();

                                    // The reset is a compare-and-set: a concurrent ACK can move
                                    // the row out of FAILED between the read and the write, and
                                    // the boolean says whether the re-arm actually happened.
                                    $commandRequeued = $command?->requeueForOperator($actor) ?? false;
                                }

                                $activityLogger = activity('biometric')
                                    ->performedOn($record)
                                    ->withProperties([
                                        'template_id' => $record->id,
                                        'actor_id' => $actor?->getKey(),
                                        'sync_status' => $record->sync_status?->value,
                                        'previous_erasure_attempts' => $previousAttempts,
                                        'previous_erasure_first_attempted_at' => $previousFirstAttemptedAt,
                                        'hardware_command_requeued' => $commandRequeued,
                                    ]);

                                if ($actor !== null) {
                                    $activityLogger->causedBy($actor);
                                }

                                // The intent is audited before the re-drive; the outcome gets
                                // its own entry below, because the re-drive can throw or re-park
                                // and the previous copy recorded only the optimistic intent.
                                if ($activityLogger->log('Biometric erasure re-drive requested by operator') === null) {
                                    throw new RuntimeException(
                                        'The erasure was not re-driven because its audit entry could not be written.'
                                    );
                                }
                            });
                        } catch (Throwable $exception) {
                            Log::error('BiometricTemplatesRelationManager: retry erasure rolled back', [
                                'template_id' => $record->getKey(),
                                'error_type' => $exception::class,
                            ]);

                            Notification::make()
                                ->title('Erasure Was Not Re-driven')
                                ->body('The reset was rolled back because it could not be audited. Try again once the audit sink is available.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $failure = null;

                        try {
                            $success = resolve(BiometricDataDeletionService::class)->deleteSingleTemplate($record);
                        } catch (Throwable $exception) {
                            $success = false;
                            $failure = $exception;

                            Log::error('BiometricTemplatesRelationManager: retry erasure threw after the intent audit', [
                                'template_id' => $record->getKey(),
                                'error_type' => $exception::class,
                            ]);
                        }

                        $record->refresh();

                        // The outcome is a separate audited fact: the re-drive may throw or
                        // re-park after a green intent entry.
                        $this->recordRetryOutcome($record, $actor, $success, $commandRequeued, $failure);

                        // The refreshed state, not the boolean, decides the outcome: a
                        // reference-present row whose provider exhausted its cooldown re-parks
                        // at `DeletionUnresolvable` even though `deleteSingleTemplate()` reports
                        // the obligation as durably recorded.
                        if ($record->sync_status === BiometricTemplateSyncStatusEnum::DeletionUnresolvable) {
                            Notification::make()
                                ->title('Erasure Still Unresolvable')
                                ->body($this->deletionUnresolvableNoticeBody($record))
                                ->warning()
                                ->send();

                            return;
                        }

                        if (! $success) {
                            Notification::make()
                                ->title('Erasure Still Failing')
                                ->body('The provider still did not confirm the erase. Check the provider credential before retrying again.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Erasure Re-driven')
                            ->body('The erasure cooldown was cleared and the obligation re-driven. It is now ' . ($record->sync_status?->getLabel() ?? 'in progress') . '.')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([]);
    }

    /**
     * Record the outcome of an operator re-drive separately from its intent.
     *
     * The intent entry is fail-closed inside the reset transaction. The outcome cannot be:
     * by the time the provider or terminal has answered, the re-drive has happened. A sink
     * failure here is logged rather than presented as a rolled-back action, so the operator
     * still sees what the credential's state actually became.
     */
    protected function recordRetryOutcome(
        BiometricTemplate $record,
        ?User $actor,
        bool $success,
        bool $commandRequeued,
        ?Throwable $failure = null
    ): void {
        try {
            $this->assertAuditSinkIsTransactional();

            $activityLogger = activity('biometric')
                ->performedOn($record)
                ->withProperties([
                    'template_id' => $record->id,
                    'actor_id' => $actor?->getKey(),
                    'result_status' => $record->sync_status?->value,
                    'provider_confirmed' => $success,
                    'hardware_command_requeued' => $commandRequeued,
                    'error_type' => $failure !== null ? $failure::class : null,
                ]);

            if ($actor !== null) {
                $activityLogger->causedBy($actor);
            }

            if ($activityLogger->log('Biometric erasure re-driven by operator') === null) {
                throw new RuntimeException('The erasure outcome could not be audited.');
            }
        } catch (Throwable $exception) {
            Log::error('BiometricTemplatesRelationManager: erasure re-drive outcome could not be audited', [
                'template_id' => $record->getKey(),
                'error_type' => $exception::class,
            ]);
        }
    }

    /**
     * The operator's next step for a terminal `DeletionUnresolvable` row.
     *
     * The producers reach that state for different reasons and the remedy differs: a
     * hardware row whose terminal was deleted must be erased on the physical terminal, a
     * hardware row whose terminal is still registered must be retired first, and a cloud
     * row is fixable by repairing the provider credential (or has nothing left to erase
     * with). Shared by the De-provision and Retry Erasure actions so both read the same way.
     */
    protected function deletionUnresolvableNoticeBody(BiometricTemplate $record): string
    {
        if (! $record->hasCloudProvider()) {
            if ($record->enrolled_by_device_id === null) {
                return 'The terminal that enrolled this credential is no longer registered, so it cannot be erased from here. Erase the credential on the physical terminal, then use "Confirm Physical Erasure" to discharge the obligation.';
            }

            return 'The terminal rejected this erase at the delivery cap, or the PIN cannot be addressed on the wire. Use "Retire Terminal" to park the credential as a detached obligation, then confirm the physical wipe; alternatively wipe it on the terminal and retire it.';
        }

        if (filled($record->template_ref)) {
            return 'The provider kept refusing the erase past its cloud erasure cooldown. Repair the provider credential before using "Retry Erasure".';
        }

        if (! $record->is_erasure_obligation) {
            return 'This record holds no provider reference, so it cannot be erased automatically, and it is not yet marked as an erasure obligation. Re-route it through the park writer (or contact support) before deleting the member or the tenant.';
        }

        return 'This record holds no provider reference, so it cannot be erased automatically. Confirm on the provider console that the credential is gone, then use "Attest Remote Erasure" to discharge the obligation.';
    }
}
