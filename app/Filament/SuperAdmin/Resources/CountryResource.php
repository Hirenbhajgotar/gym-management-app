<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Clusters\Location;
use App\Filament\SuperAdmin\Resources\CountryResource\Pages\ListCountries;
use App\Models\Country;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CountryResource extends BaseResource
{
    protected static ?string $model = Country::class;

    protected static ?string $cluster = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function getFormSchema()
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->default(null),
            TextInput::make('code')
                ->label('Country Code')
                ->maxLength(255)
                ->default(null)
                ->unique(Country::class, 'code', ignoreRecord: true),
            Toggle::make('status')
                ->default(true),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                ToggleColumn::make('status'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListCountries::route('/'),
            // 'create' => Pages\CreateCountry::route('/create'),
            // 'edit' => Pages\EditCountry::route('/{record}/edit'),
        ];
    }
}
