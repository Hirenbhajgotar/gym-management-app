<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\State>
 */
class StateFactory extends Factory
{
    protected $model = State::class;

    // Predefined states for each country
    public $statesByCountry = [
        'India' => [
            'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat',
            'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra',
            'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim',
            'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
            // Union Territories
            'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
            'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
        ],
        'United States' => [
            'California', 'Texas', 'Florida', 'New York', 'Illinois', 'Pennsylvania', 'Ohio', 'Georgia',
            'North Carolina', 'Michigan', 'New Jersey', 'Virginia', 'Washington', 'Arizona', 'Massachusetts',
            'Tennessee', 'Indiana', 'Missouri', 'Maryland', 'Wisconsin', 'Colorado', 'Minnesota', 'South Carolina',
            'Alabama', 'Louisiana', 'Kentucky', 'Oregon', 'Oklahoma', 'Connecticut', 'Utah', 'Iowa', 'Nevada',
            'Arkansas', 'Mississippi', 'Kansas', 'New Mexico', 'Nebraska', 'West Virginia', 'Idaho', 'Hawaii',
            'New Hampshire', 'Maine', 'Montana', 'Rhode Island', 'Delaware', 'South Dakota', 'North Dakota',
            'Alaska', 'Vermont', 'Wyoming',
        ],
        'Canada' => [
            'Ontario', 'Quebec', 'British Columbia', 'Alberta', 'Manitoba',
        ],
        'Australia' => [
            'New South Wales', 'Victoria', 'Queensland', 'Western Australia', 'South Australia',
        ],
        'United Kingdom' => [
            'England', 'Scotland', 'Wales', 'Northern Ireland', 'Greater London',
        ],
        'Japan' => [
            'Tokyo', 'Osaka', 'Hokkaido', 'Kyoto', 'Fukuoka',
        ],
        'Germany' => [
            'Bavaria', 'North Rhine-Westphalia', 'Baden-Württemberg', 'Hesse', 'Saxony',
        ],
    ];

    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => $this->faker->state(),
            'status' => $this->faker->boolean(90), // 90% chance of being active (1)
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // Define a method to set the country_id and use real state names
    public function forCountry($countryId, $countryName)
    {
        $states = $this->statesByCountry[$countryName] ?? [];

        return $this->state(function () use ($countryId, $states) {
            return [
                'country_id' => $countryId,
                'name' => $this->faker->randomElement($states) ?? $this->faker->state(),
                'status' => 1,
            ];
        });
    }
}
