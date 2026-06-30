<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Clusters\Location;
use App\Filament\SuperAdmin\Resources\CityResource\Pages\ListCities;
use App\Models\City;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CityResource extends BaseResource
{
    protected static ?string $model = City::class;

    protected static ?string $cluster = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    /**
     * @return Builder<City>
     */
    public static function getEloquentQuery(): Builder
    {
        // Fetch active state's record
        return parent::getEloquentQuery()->whereHas('state', function (Builder $state) {
            $state->where('status', 1);
        });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('state_id')
                    ->relationship('state', 'name')
                    ->options(State::query()->active()->get()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->createOptionForm(StateResource::getFormSchema())
                    ->createOptionUsing(function (array $data) {
                        // Create the new state and return its ID
                        $state = State::query()->create($data);

                        return $state->id;
                    })
                    ->placeholder('Select or create a state'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('slug', Str::slug($state));
                    })
                    ->default(null),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(City::class, 'slug', ignoreRecord: true)
                    ->afterStateHydrated(function ($state, callable $set, $record) {
                        if (! $state && $record) {
                            $set('slug', Str::slug($record->name));
                        }
                    })
                    ->default(null),
                Toggle::make('status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('state.name')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
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
                Filter::make('location')
                    ->label('Filter by Country & State')
                    ->form([
                        Select::make('country_id')
                            ->label('Country')
                            ->options(Country::all()->pluck('name', 'id'))
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('state_id', null)),

                        Select::make('state_id')
                            ->label('State')
                            ->options(function (callable $get) {
                                $countryId = $get('country_id');

                                if (! $countryId) {
                                    return [];
                                }

                                return State::query()->where('country_id', $countryId)->pluck('name', 'id');
                            }),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['country_id'] ?? null,
                                fn ($q, $countryId) => $q->whereHas('state.country', fn (Builder $q) => $q->where('id', $countryId))
                            )
                            ->when(
                                $data['state_id'] ?? null,
                                fn ($q, $stateId) => $q->where('state_id', $stateId)
                            );
                    }),
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
            'index' => ListCities::route('/'),
            // 'create' => Pages\CreateCity::route('/create'),
            // 'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
