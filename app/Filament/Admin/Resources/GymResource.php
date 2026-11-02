<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\GymResource\Pages\ListGyms;
use App\Filament\BaseResources\BaseResource;
use App\Forms\Components\AddressForm;
use App\Models\Gym;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class GymResource extends BaseResource
{
    protected static ?string $model = Gym::class;

    protected static ?string $navigationLabel = 'Gyms';

    protected static ?string $navigationGroup = 'Gym Management';

    protected static bool $isDiscovered = false;

    // Only access super_admin, admin and gym_owner
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user?->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
        ]) ?? false;
    }

    public function defaultRolePermissions(): array
    {
        return [];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Gym Details')
                    ->icon('heroicon-o-building-office')
                    ->description('Enter details for the gym')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Fitness Hub')
                            ->autofocus()
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                        AddressForm::make('address')
                            ->columnSpan('full'),

                        Hidden::make('owner_id')
                            ->default(auth()->id()),
                    ])
                    ->collapsible()
                    ->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address.fullAddress')
                    ->limit(50)
                    ->tooltip(fn ($state) => $state)
                    ->wrap(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->searchable()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->successNotification(null)
                    ->action(function (Gym $record, DeleteAction $action): void {
                        try {
                            if ($record->delete() !== true) {
                                Notification::make()
                                    ->danger()
                                    ->title('Gym Was Not Deleted')
                                    ->body('The deletion was refused. Refresh the table and retry, or contact support if it keeps failing.')
                                    ->send();

                                return;
                            }
                        } catch (Throwable $exception) {
                            // A policy pass is not a guarantee: a terminal or an obligation can
                            // be created between the check and the delete. Surface it as a
                            // legible red notification, not a Livewire error page.
                            Log::error('GymResource: row delete failed', [
                                'gym_id' => $record->getKey(),
                                'error_type' => $exception::class,
                            ]);

                            Notification::make()
                                ->danger()
                                ->title('Gym Was Not Deleted')
                                ->body('The deletion failed before it could complete, so the gym is unchanged. Retry after checking that no biometric terminal or erasure obligation remains; if it keeps failing, contact support.')
                                ->send();

                            return;
                        }

                        $action->success();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Filament's DeleteBulkAction authorises with deleteAny() only, which
                    // carries no record predicate, so the per-gym guard in GymPolicy::delete()
                    // would be bypassed. Re-check it per record and report the refusals.
                    DeleteBulkAction::make()
                        ->action(function (Collection $records, DeleteBulkAction $action): void {
                            $deletedCount = 0;
                            $refusedCount = 0;
                            $failedCount = 0;

                            /** @var Gym $record */
                            foreach ($records as $record) {
                                if (! Gate::allows('delete', $record)) {
                                    $refusedCount++;

                                    continue;
                                }

                                // A policy pass is not a guarantee: a device can be created
                                // between the check and the delete, and any future listener
                                // can throw. Count the failure per record instead of letting
                                // it escape as a 500 after earlier gyms have committed.
                                try {
                                    if ($record->delete() === true) {
                                        $deletedCount++;
                                    } else {
                                        $failedCount++;
                                    }
                                } catch (Throwable $exception) {
                                    $failedCount++;

                                    Log::error('GymResource: bulk delete failed for one gym', [
                                        'gym_id' => $record->getKey(),
                                        'error_type' => $exception::class,
                                    ]);
                                }
                            }

                            if ($deletedCount > 0) {
                                $action->success();
                            }

                            if ($refusedCount > 0) {
                                Notification::make()
                                    ->danger()
                                    ->title("{$refusedCount} gym(s) refused")
                                    ->body("{$refusedCount} selected gym(s) could not be deleted because they still have biometric terminals or outstanding erasure obligations, or your policy does not permit it. Retire each terminal and discharge its obligations first. {$deletedCount} gym(s) were deleted.")
                                    ->persistent()
                                    ->send();
                            }

                            if ($failedCount > 0) {
                                Notification::make()
                                    ->danger()
                                    ->title("{$failedCount} gym(s) failed")
                                    ->body("{$failedCount} selected gym(s) could not be deleted because the deletion failed. They are unchanged. Check the application log for the failure and retry; {$deletedCount} gym(s) were deleted.")
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                if (auth()->user()->hasRole('gym_owner')) {
                    $query->where('owner_id', auth()->id());
                }

                return $query;
            })
            ->defaultSort('name')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGyms::route('/'),
            // 'create' => Pages\CreateGym::route('/create'),
            // 'edit' => Pages\EditGym::route('/{record}/edit'),
        ];
    }
}
