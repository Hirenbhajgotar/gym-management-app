<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use App\Traits\GuardsArchivedOwner;
use App\Traits\HasTenantAccess;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                            ->whereIn('sync_status', BiometricTemplateSyncStatusEnum::operatorAttentionCandidateStates())
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
                    ->authorize(fn (BiometricTemplate $record): bool => $record->gymMember !== null && (bool) auth()->user()?->can('enrollBiometric', $record->gymMember))
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

                // The exit for a terminal state. Without it a cap-exhausted row is permanent:
                // the cloud cooldown has to be cleared, and a hardware row's delivery counter
                // lives on its `user_delete`, so no single template write re-arms it.
                Action::make('retry_erasure')
                    ->label('Retry Erasure')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry this erasure')
                    ->modalDescription('Clears the recorded cooldown so the credential is driven through its provider or terminal again. Use it after repairing the provider credential or the device firmware.')
                    // A blank-reference cloud row has nothing to erase with, so a retry could
                    // only report a green success for an erasure it cannot attempt.
                    ->visible(fn (BiometricTemplate $record): bool => $record->requiresOperatorAttention() && ! ($record->hasCloudProvider() && blank($record->template_ref)))
                    ->authorize(fn (BiometricTemplate $record): bool => $record->gymMember !== null && (bool) auth()->user()?->can('enrollBiometric', $record->gymMember))
                    ->action(function (BiometricTemplate $record): void {
                        $record->forceFill([
                            'erasure_attempts' => 0,
                            'erasure_first_attempted_at' => null,
                        ])->save();

                        if ($record->enrolled_by_device_id !== null) {
                            // Scoped to this row's gym as well as its device: device ids are not
                            // guaranteed globally unique across a restored or sharded database,
                            // and a cross-tenant command must never be re-armed here.
                            AdmsDeviceCommand::query()
                                ->where('gym_id', $record->gym_id)
                                ->where('device_id', $record->enrolled_by_device_id)
                                ->where('command_type', 'user_delete')
                                ->where('command_payload->Pin', $record->resolvedCommandPin())
                                ->where('status', AdmsCommandStatusEnum::FAILED)
                                ->update(['attempt_count' => 0]);
                        }

                        $success = resolve(BiometricDataDeletionService::class)->deleteSingleTemplate($record);

                        $record->refresh();

                        activity('biometric')
                            ->performedOn($record)
                            ->withProperties([
                                'template_id' => $record->id,
                                'sync_status' => $record->sync_status?->value,
                            ])
                            ->log('Biometric erasure re-driven by operator');

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
     * The operator's next step for a terminal `DeletionUnresolvable` row.
     *
     * Two producers reach that state and the operator's remedy differs: a cap-exhausted row
     * still holds its provider reference and is fixable by repairing the credential, while a
     * reference-less row has nothing left to erase with. Shared by the De-provision and Retry
     * Erasure actions so both read the same way.
     */
    protected function deletionUnresolvableNoticeBody(BiometricTemplate $record): string
    {
        return filled($record->template_ref)
            ? 'The provider kept refusing the erase past its cloud erasure cooldown. Repair the provider credential before using "Retry Erasure".'
            : 'This record holds no provider reference, so the credential cannot be erased automatically. It has been flagged for manual follow-up.';
    }
}
