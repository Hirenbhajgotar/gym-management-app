<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Clusters\Location;
use App\Filament\SuperAdmin\Resources\StateResource\Pages;
use App\Models\Country;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class StateResource extends BaseResource
{
    protected static ?string $model = State::class;

    protected static ?string $cluster = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function getEloquentQuery(): Builder
    {
        // Fetch active country's record
        return parent::getEloquentQuery()->whereHas('country', function ($country) {
            $country->where('status', 1);
        });

        // return parent::getEloquentQuery()
        //     ->select('states.*') // Select only states columns to avoid ambiguity
        //     ->join('countries', function ($join) {
        //         $join->on('states.country_id', '=', 'countries.id')
        //             ->where('countries.status', 1); // Only active countries
        //     })
        //     ->with('country'); // Eager load country to avoid N+1 queries
    }

    public static function getFormSchema()
    {
        return [
            Forms\Components\Select::make('country_id')
                ->options(Country::active()->get()->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->createOptionForm(CountryResource::getFormSchema())
                ->createOptionUsing(function (array $data) {
                    // Create the new country and return its ID
                    $country = Country::create($data);

                    return $country->id;
                })
                ->placeholder('Select or create a country'),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->default(null)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('slug', Str::slug($state));
                }),
            Forms\Components\TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->default(null)
                ->unique(State::class, 'slug', ignoreRecord: true)
                ->afterStateHydrated(function ($state, callable $set, $record) {
                    if (! $state && $record) {
                        $set('slug', Str::slug($record->name));
                    }
                }),
            Forms\Components\Toggle::make('states.status')
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
                Tables\Columns\TextColumn::make('country.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('states.status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country_id')
                    ->relationship('country', 'name')
                    ->label('Country'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListStates::route('/'),
            // 'create' => Pages\CreateState::route('/create'),
            // 'edit' => Pages\EditState::route('/{record}/edit'),
        ];
    }
}
