<?php

namespace App\Forms\Components;

use Filament\Forms;
use Filament\Forms\Components\Field;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use Illuminate\Database\Eloquent\Model;

class AddressForm extends Field
{
    protected string $view = 'filament-forms::components.group';

    /** @var string|callable|null */
    public $relationship = null;

    public function relationship(string | callable $relationship): static
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
            'city_id' => $state['city_id'] ?? null,
            'state_id' => $state['state_id'] ?? null,
            'country_id' => $state['country_id'] ?? null,
            'postal_code' => $state['postal_code'] ?? null,
            'latitude' => $state['latitude'] ?? null,
            'longitude' => $state['longitude'] ?? null,
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

        return [
            Forms\Components\TextInput::make('street')
                ->label('Street Address')
                ->required()
                ->placeholder('e.g., 123 Main St')
                ->maxLength(255),
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\Select::make('country_id')
                        ->label('Country')
                        ->options(Country::active()->pluck('name', 'id'))
                        ->default($indiaId) // Set India as default
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (callable $set, $state) {
                            $set('state_id', null);
                            $set('city_id', null);
                        }),

                    Forms\Components\Select::make('state_id')
                        ->label('State / Province')
                        ->options(function (callable $get) {
                            $countryId = $get('country_id');
                            if (!$countryId) {
                                return [];
                            }
                            return State::where('country_id', $countryId)
                                ->active()
                                ->pluck('name', 'id');
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn(callable $set) => $set('city_id', null))
                        ->disabled(fn(callable $get) => !$get('country_id')),

                    Forms\Components\Select::make('city_id')
                        ->label('City')
                        ->options(function (callable $get) {
                            $stateId = $get('state_id');
                            if (!$stateId) {
                                return [];
                            }
                            return City::where('state_id', $stateId)
                                ->active()
                                ->pluck('name', 'id');
                        })
                        ->required()
                        ->live()
                        ->disabled(fn(callable $get) => !$get('state_id')),
                ]),
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('postal_code')
                        ->label('Zip / Postal code')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('latitude')
                        ->label('Latitude')
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90),

                    Forms\Components\TextInput::make('longitude')
                        ->label('Longitude')
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180),
                ])
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
