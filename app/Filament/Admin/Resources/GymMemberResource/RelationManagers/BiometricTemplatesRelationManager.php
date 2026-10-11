<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricTemplate;
use App\Traits\HasTenantAccess;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BiometricTemplatesRelationManager extends RelationManager
{
    use HasTenantAccess;

    protected static string $relationship = 'biometricTemplates';

    protected static ?string $icon = 'heroicon-o-finger-print';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['enrolledByDevice', 'gymMember']))
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

                TextColumn::make('enrolled_at')
                    ->label('Enrolled At')
                    ->dateTime(),
            ])
            ->filters([
                Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true)),
            ])
            ->actions([
                Action::make('deprovision')
                    ->label('De-provision')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('De-provision this template')
                    ->modalDescription('This requests erasure of the biometric credential from the hardware device. The record is retained and moves to "Pending Hardware Deletion" until the terminal confirms removal.')
                    ->authorize(fn (BiometricTemplate $record): bool => (bool) auth()->user()?->can('enrollBiometric', $record->gymMember))
                    ->action(function (BiometricTemplate $record) {
                        $success = resolve(BiometricDataDeletionService::class)->deleteSingleTemplate($record);

                        if (! $success) {
                            Notification::make()
                                ->title('De-provisioning Failed')
                                ->body('The provider did not confirm the template deletion.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->refresh();

                        $confirmed = $record->sync_status === BiometricTemplateSyncStatusEnum::Deleted;

                        Notification::make()
                            ->title($confirmed ? 'Template De-provisioned' : 'De-provision Requested')
                            ->body(
                                $confirmed
                                    ? 'The credential was never written to the device, or the device confirmed its removal.'
                                    : 'Hardware erasure is pending confirmation from the device. The record is retained for audit.'
                            )
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([]);
    }
}
