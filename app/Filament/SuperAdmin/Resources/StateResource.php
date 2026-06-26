<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Clusters\Location;
use App\Filament\SuperAdmin\Resources\StateResource\Pages\ListStates;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
        return parent::getEloquentQuery()->whereHas('country', function (\Illuminate\Contracts\Database\Query\Builder $country) {
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
            Select::make('country_id')
                ->options(Country::query()->active()->get()->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->createOptionForm(CountryResource::getFormSchema())
                ->createOptionUsing(function (array $data) {
                    // Create the new country and return its ID
                    $country = Country::query()->create($data);

                    return $country->id;
                })
                ->placeholder('Select or create a country'),
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->default(null)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('slug', Str::slug($state));
                }),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->default(null)
                ->unique(State::class, 'slug', ignoreRecord: true)
                ->afterStateHydrated(function ($state, callable $set, $record) {
                    if (! $state && $record) {
                        $set('slug', Str::slug($record->name));
                    }
                }),
            Toggle::make('states.status')
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
                TextColumn::make('country.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('states.status')
                    ->boolean(),
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
                SelectFilter::make('country_id')
                    ->relationship('country', 'name')
                    ->label('Country'),
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
            'index' => ListStates::route('/'),
            // 'create' => Pages\CreateState::route('/create'),
            // 'edit' => Pages\EditState::route('/{record}/edit'),
        ];
    }
}
