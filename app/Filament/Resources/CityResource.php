<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Location';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user->hasRole('super_admin');
    }

    public static function getEloquentQuery(): Builder
    {
        // Fetch active state's record
        return parent::getEloquentQuery()->whereHas('state', function ($state) {
            $state->active();
        });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('state_id')
                    ->relationship('state', 'name')
                    ->options(State::active()->get()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->createOptionForm(StateResource::getFormSchema())
                    ->createOptionUsing(function (array $data) {
                        // Create the new state and return its ID
                        $state = State::create($data);

                        return $state->id;
                    })
                    ->placeholder('Select or create a state'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('slug', Str::slug($state));
                    })
                    ->default(null),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(City::class, 'slug', ignoreRecord: true)
                    ->afterStateHydrated(function ($state, callable $set, $record) {
                        if (! $state && $record) {
                            $set('slug', Str::slug($record->name));
                        }
                    })
                    ->default(null),
                Forms\Components\Toggle::make('status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('state.name')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('status')
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
                Filter::make('location')
                    ->label('Filter by Country & State')
                    ->form([
                        Select::make('country_id')
                            ->label('Country')
                            ->options(Country::all()->pluck('name', 'id'))
                            ->reactive()
                            ->afterStateUpdated(fn(callable $set) => $set('state_id', null)),

                        Select::make('state_id')
                            ->label('State')
                            ->options(function (callable $get) {
                                $countryId = $get('country_id');

                                if (!$countryId) {
                                    return [];
                                }

                                return State::where('country_id', $countryId)->pluck('name', 'id');
                            }),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['country_id'] ?? null,
                                fn($q, $countryId) =>
                                $q->whereHas('state.country', fn($q) => $q->where('id', $countryId))
                            )
                            ->when(
                                $data['state_id'] ?? null,
                                fn($q, $stateId) =>
                                $q->where('state_id', $stateId)
                            );
                    }),
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
            'index' => Pages\ListCities::route('/'),
            // 'create' => Pages\CreateCity::route('/create'),
            // 'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
