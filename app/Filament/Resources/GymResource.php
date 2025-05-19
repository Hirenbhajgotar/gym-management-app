<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GymResource\Pages;
use App\Forms\Components\AddressForm;
use App\Models\Gym;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GymResource extends Resource
{
    protected static ?string $model = Gym::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Gyms';

    protected static ?string $navigationGroup = 'Gym Management';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()->hasRole('gym_owner') || auth()->user()->hasRole('super_admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gym Details')
                    ->icon('heroicon-o-building-office')
                    ->description('Enter details for the gym')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Fitness Hub')
                            ->autofocus()
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                        AddressForm::make('address')
                            ->columnSpan('full'),

                        Forms\Components\Hidden::make('owner_id')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address.fullAddress')
                    ->limit(50)
                    ->tooltip(fn($state) => $state)
                    ->wrap(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->searchable()
                    ->visible(fn() => auth()->user()->hasRole('super_admin'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('members_count')
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
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->successNotification(null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListGyms::route('/'),
            // 'create' => Pages\CreateGym::route('/create'),
            // 'edit' => Pages\EditGym::route('/{record}/edit'),
        ];
    }
}
