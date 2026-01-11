<?php

namespace App\Forms\Components;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;

class AddressForm extends Field
{
    protected string $view = 'filament-forms::components.group';

    /** @var string|callable|null */
    public $relationship = null;

    public function relationship(string|callable $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function saveRelationships(): void
    {
        $state = $this->getState();
        $record = $this->getRecord();
        $relationship = $record?->{$this->getRelationship()}();

        if ($relationship === null) {
            return;
        }

        // Prepare the address data
        $addressData = [
            'street' => $state['street'] ?? null,
            // Ensure empty strings are converted to null to prevent integer casting errors
            'city_id' => ! empty($state['city_id']) ? $state['city_id'] : null,
            'state_id' => ! empty($state['state_id']) ? $state['state_id'] : null,
            'country_id' => ! empty($state['country_id']) ? $state['country_id'] : null,
            'postal_code' => $state['postal_code'] ?? null,
            'latitude' => isset($state['latitude']) && ! empty($state['latitude']) ? $state['latitude'] : null,
            'longitude' => isset($state['longitude']) && ! empty($state['longitude']) ? $state['longitude'] : null,
        ];

        if ($address = $relationship->first()) {
            $address->update($addressData);
        } else {
            $relationship->create($addressData);
        }

        $record?->touch();
    }

    public function getChildComponents(): array
    {
        // Get India's ID (assuming you have India in your countries table)
        $indiaId = Country::whereRaw('LOWER(name) = ?', ['india'])->first()?->id;

        $isMarkedAsRequired = $this->isRequired();

        return [
            Forms\Components\TextInput::make('street')
                ->label('Street Address')
                ->placeholder('e.g., 123 Main St')
                ->maxLength(255)
                ->required($isMarkedAsRequired),
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\Select::make('country_id')
                        ->label('Country')
                        ->options(fn () => \Illuminate\Support\Facades\Cache::flexible(Country::COUNTRIES_LIST_CACHE_KEY, [now()->addDays(7), now()->addDays(14)], function () {
                            return Country::active()->orderBy('name')->pluck('name', 'id');
                        }))
                        ->default($indiaId) // Set India as default
                        ->live()
                        ->afterStateUpdated(function (callable $set, $state) {
                            $set('state_id', null);
                            $set('city_id', null);
                        })
                        ->required($isMarkedAsRequired),

                    Forms\Components\Select::make('state_id')
                        ->label('State / Province')
                        ->options(function (callable $get) {
                            $countryId = $get('country_id');
                            if (! $countryId) {
                                return [];
                            }

                            return \Illuminate\Support\Facades\Cache::flexible(State::STATES_OF_COUNTRY_CACHE_KEY . $countryId, [now()->addDays(7), now()->addDays(14)], function () use ($countryId) {
                                return State::where('country_id', $countryId)
                                    ->active()
                                    ->orderBy('name', 'asc')
                                    ->pluck('name', 'id');
                            });
                        })
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('city_id', null))
                        ->disabled(fn (callable $get) => ! $get('country_id'))
                        ->required($isMarkedAsRequired),

                    Forms\Components\Select::make('city_id')
                        ->label('City')
                        ->options(function (callable $get) {
                            $stateId = $get('state_id');
                            if (! $stateId) {
                                return [];
                            }

                            return \Illuminate\Support\Facades\Cache::flexible(City::CITIES_OF_STATE_CACHE_KEY . $stateId, [now()->addDays(7), now()->addDays(14)], function () use ($stateId) {
                                return City::where('state_id', $stateId)
                                    ->active()
                                    ->orderBy('name', 'asc')
                                    ->pluck('name', 'id');
                            });
                        })
                        ->searchable()
                        ->live()
                        ->disabled(fn (callable $get) => ! $get('state_id'))
                        ->required($isMarkedAsRequired),
                ]),
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('postal_code')
                        ->label('Zip / Postal code')
                        ->maxLength(20)
                        ->required($isMarkedAsRequired),
                ]),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (AddressForm $component, ?Model $record) {
            $relationship = $this->getRelationship();
            $address = $record?->getRelationValue($relationship);

            // $component->state($address ? $address->toArray() : [
            //     'street' => null,
            //     'city_id' => null,
            //     'state_id' => null,
            //     'country_id' => null,
            //     'postal_code' => null,
            //     'latitude' => null,
            //     'longitude' => null,
            // ]);

            if ($address) {
                // If address exists, use its data
                $component->state($address->toArray());
            } else {
                // If no address exists, set defaults including India
                $indiaId = Country::whereRaw('LOWER(name) = ?', ['india'])->first()?->id;

                $component->state([
                    'street' => null,
                    'city_id' => null,
                    'state_id' => null,
                    'country_id' => $indiaId, // Set India as default
                    'postal_code' => null,
                    'latitude' => null,
                    'longitude' => null,
                ]);
            }
        });

        $this->dehydrated(false);
    }

    public function getRelationship(): string
    {
        return $this->evaluate($this->relationship) ?? $this->getName();
    }
}
