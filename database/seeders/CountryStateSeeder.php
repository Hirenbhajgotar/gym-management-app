<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Database\Factories\CityFactory;
use Database\Factories\StateFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CountryStateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed Countries and their states
        $countries = [
            ['name' => 'India', 'code' => 'IN', 'factory' => 'india'],
            ['name' => 'United States', 'code' => 'US', 'factory' => 'usa'],
            ['name' => 'Canada', 'code' => 'CA', 'factory' => 'canada'],
            ['name' => 'Australia', 'code' => 'AU', 'factory' => 'australia'],
            ['name' => 'United Kingdom', 'code' => 'GB', 'factory' => 'uk'],
            ['name' => 'Japan', 'code' => 'JP', 'factory' => 'japan'],
            ['name' => 'Germany', 'code' => 'DE', 'factory' => 'germany'],
        ];

        foreach ($countries as $countryData) {
            // Create the country
            $country = Country::factory()->{$countryData['factory']}()->create([
                // 'id' => $index + 1,
                'name' => $countryData['name'],
                'code' => $countryData['code'],
                'status' => 1,
            ]);

            // Get the predefined states for this country from the StateFactory
            $stateFactory = new StateFactory;
            $states = $stateFactory->statesByCountry[$countryData['name']] ?? [];

            // For India and USA, create all states; for others, create 5
            // $stateCount = ($countryData['name'] === 'India' || $countryData['name'] === 'United States')
            //     ? count($states)
            //     : min(5, count($states));

            $stateCount = count($states);

            // dd($states, $stateCount);
            // Create states
            $stateIds = [];
            for ($i = 0; $i < $stateCount; $i++) {
                $state = State::factory()
                    ->forCountry($country->id, $countryData['name'])
                    ->create([
                        'name' => $states[$i],
                        'slug' => Str::slug($states[$i]),
                        'status' => 1,
                    ]);
                $stateIds[] = ['id' => $state->id, 'name' => $state->name];
            }

            // Create 5 cities for each state
            foreach ($stateIds as $stateData) {
                // Get the predefined cities for this state from the CityFactory
                $cityFactory = new CityFactory;
                $cities = $cityFactory->citiesByState[$stateData['name']] ?? [];
                $cityCount = count($cities);

                for ($j = 0; $j < $cityCount; $j++) {
                    City::factory()
                        ->forState($stateData['id'], $stateData['name'])
                        ->create([
                            'status' => 1,
                        ]);
                }
            }
        }
    }
}
