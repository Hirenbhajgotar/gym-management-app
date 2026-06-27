<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\GymResource\Pages\ListGyms;
use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Forms\Components\AddressForm;
use App\Models\Gym;
use Filament\Forms\Form;
use Filament\Tables\Table;

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
                    ->successNotification(null),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
