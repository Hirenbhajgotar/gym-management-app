<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Biometrics\Services\BiometricLogRedactor;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\AdmsDeviceCommandResource\Pages\ListAdmsDeviceCommands;
use App\Filament\BaseResources\BaseResource;
use App\Models\AdmsDeviceCommand;
use Filament\Facades\Filament;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AdmsDeviceCommandResource extends BaseResource
{
    protected static ?string $model = AdmsDeviceCommand::class;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationLabel = 'Device Commands';

    protected static ?string $modelLabel = 'Device Command';

    protected static ?string $pluralModelLabel = 'Device Commands';

    protected static ?string $navigationGroup = 'Device Management';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::SUPER_ADMIN->value => [
                'view',
                'view_any',
                'delete',
                'delete_any',
            ],
            DefaultRole::GYM_OWNER->value => [
                'view',
                'view_any',
            ],
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
            ],
            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    /**
     * @return Builder<AdmsDeviceCommand>
     */
    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            /** @var Builder<AdmsDeviceCommand> $query */
            $query = parent::getEloquentQuery();

            $user = auth()->user();
            if ($user && ! $user->hasRole(DefaultRole::SUPER_ADMIN->value)) {
                $userGymIds = $user->relationLoaded('gyms')
                    ? $user->gyms->pluck('id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all()
                    : $user->gyms()->pluck('gyms.id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();

                return $query->whereIn('gym_id', $userGymIds);
            }

            return $query;
        }

        /** @var Builder<AdmsDeviceCommand> $query */
        $query = parent::getEloquentQuery()->where('gym_id', $tenant->getKey());

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Queued At')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),

                TextColumn::make('device.device_name')
                    ->label('Device')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('command_type')
                    ->label('Command')
                    ->badge()
                    ->colors([
                        'danger' => 'user_delete',
                        'success' => 'user_add',
                        'warning' => 'user_update',
                        'info' => fn ($state): bool => in_array($state, ['reboot', 'clear_log'], true),
                    ]),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('command_payload')
                    ->label('Payload')
                    ->formatStateUsing(function ($state) {
                        if (! is_array($state)) {
                            return $state;
                        }

                        $redacted = BiometricLogRedactor::redactForPresentation($state, auth()->user());

                        return json_encode($redacted, JSON_UNESCAPED_SLASHES);
                    })
                    ->limit(40)
                    ->tooltip(function (?AdmsDeviceCommand $record) {
                        if (! is_array($record?->command_payload)) {
                            return null;
                        }

                        $redacted = BiometricLogRedactor::redactForPresentation($record->command_payload, auth()->user());

                        return json_encode($redacted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    })
                    ->placeholder('—'),

                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('M d, Y H:i:s')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('acknowledged_at')
                    ->label('Acked At')
                    ->dateTime('M d, Y H:i:s')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('device_return_code')
                    ->label('Return Code')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AdmsCommandStatusEnum::class),

                SelectFilter::make('command_type')
                    ->label('Command Type')
                    ->options([
                        'user_add' => 'User Add',
                        'user_delete' => 'User Delete',
                        'user_update' => 'User Update',
                        'reboot' => 'Reboot',
                        'clear_log' => 'Clear Log',
                    ]),

                SelectFilter::make('device_id')
                    ->label('Device')
                    ->relationship('device', 'device_name', fn (Builder $query) => $query->where('gym_id', Filament::getTenant()?->getKey())),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->authorize(fn (AdmsDeviceCommand $record): bool => self::canResend($record))
                    ->visible(fn (AdmsDeviceCommand $record): bool => $record->status === AdmsCommandStatusEnum::FAILED || ($record->status === AdmsCommandStatusEnum::SENT && $record->sent_at?->diffInHours(now()) >= 1))
                    ->requiresConfirmation()
                    ->modalHeading('Resend Command to Device')
                    ->modalDescription('This will reset the command status to pending so the device fetches it on the next poll.')
                    ->action(function (AdmsDeviceCommand $record): void {
                        if (! self::canResend($record)) {
                            self::refuseResend($record);

                            return;
                        }

                        $record->update([
                            'status' => AdmsCommandStatusEnum::PENDING,
                            'attempt_count' => 0,
                            'sent_at' => null,
                            'leased_at' => null,
                            'expires_at' => null,
                            'acknowledged_at' => null,
                            'device_return_code' => null,
                        ]);

                        Notification::make()
                            ->title('Command re-queued')
                            ->body('Command will be sent to device on next poll.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('resendSelected')
                        ->label('Resend Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->authorize(fn (): bool => auth()->user()?->can('resend', AdmsDeviceCommand::class) ?? false)
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = 0;
                            $consentBlocked = 0;
                            /** @var AdmsDeviceCommand $record */
                            foreach ($records as $record) {
                                // Per-record authorization stays: the bulk action must never
                                // re-queue a command from another gym.
                                if (! self::isAuthorizedToResend($record)) {
                                    continue;
                                }

                                if ($record->status !== AdmsCommandStatusEnum::FAILED
                                    && ! ($record->status === AdmsCommandStatusEnum::SENT && $record->sent_at?->diffInHours(now()) >= 1)) {
                                    continue;
                                }

                                if (! $record->isConsentPermitted()) {
                                    $consentBlocked++;
                                    self::refuseResend($record);

                                    continue;
                                }

                                $record->update([
                                    'status' => AdmsCommandStatusEnum::PENDING,
                                    'attempt_count' => 0,
                                    'sent_at' => null,
                                    'leased_at' => null,
                                    'expires_at' => null,
                                    'acknowledged_at' => null,
                                    'device_return_code' => null,
                                ]);
                                $count++;
                            }

                            if ($consentBlocked > 0) {
                                Notification::make()
                                    ->title("{$consentBlocked} command(s) blocked")
                                    ->body('Re-queue refused: the member no longer has active biometric consent, or the target member could not be resolved. Enrolment requires consent on file (ADR-003).')
                                    ->danger()
                                    ->send();
                            }

                            Notification::make()
                                ->title("{$count} commands re-queued")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => auth()->user()?->can('deleteAny', AdmsDeviceCommand::class) ?? false),
                ]),
            ]);
    }

    /**
     * Determine whether a single command may be re-queued.
     *
     * A `user_add` or `user_update` pushes the member's PIN and name back onto a terminal,
     * so it must not be re-queued once biometric consent is absent or revoked
     * (ADR-003 / OpenSpec §4). An unresolvable target member is refused too, because
     * consent cannot be proven for a member we cannot identify.
     */
    public static function canResend(AdmsDeviceCommand $record): bool
    {
        return self::isAuthorizedToResend($record) && $record->isConsentPermitted();
    }

    /**
     * Determine whether the signed-in user may re-queue this specific command.
     *
     * Kept separate from the consent gate so the bulk action can skip a command that belongs
     * to another tenant silently, while still telling the operator about a command that was
     * authorized but refused by the consent gate.
     */
    public static function isAuthorizedToResend(AdmsDeviceCommand $record): bool
    {
        return auth()->user()?->can('resend', $record) ?? false;
    }

    protected static function refuseResend(AdmsDeviceCommand $record): void
    {
        Log::warning('AdmsDeviceCommandResource: resend refused by biometric consent gate', [
            'command_id' => $record->id,
            'gym_id' => $record->gym_id,
            'device_id' => $record->device_id,
            'command_type' => $record->command_type,
        ]);

        Notification::make()
            ->title('Resend Refused')
            ->body('This command provisions biometric data on a device, but the member no longer has active biometric consent on file, or the target member could not be resolved. Renew consent before re-queueing.')
            ->danger()
            ->send();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Command Details')
                    ->schema([
                        TextEntry::make('device.device_name')->label('Device'),
                        TextEntry::make('device.device_serial')->label('Device Serial (SN)')->placeholder('—'),
                        TextEntry::make('command_type')->label('Command Type')->badge(),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('created_at')->label('Created At')->dateTime(),
                        TextEntry::make('sent_at')->label('Sent At')->dateTime()->placeholder('Not yet sent'),
                        TextEntry::make('acknowledged_at')->label('Acknowledged At')->dateTime()->placeholder('Not yet acknowledged'),
                        TextEntry::make('device_return_code')->label('Device Return Code')->placeholder('—'),
                    ])->columns(2),

                Section::make('Payload')
                    ->schema([
                        KeyValueEntry::make('command_payload')
                            ->label('Payload Data (Masked)')
                            ->getStateUsing(fn (?AdmsDeviceCommand $record): array => is_array($record?->command_payload) ? BiometricLogRedactor::redactForPresentation($record->command_payload, auth()->user()) : [])
                            ->visible(fn (?AdmsDeviceCommand $record): bool => is_array($record?->command_payload)),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmsDeviceCommands::route('/'),
        ];
    }
}
