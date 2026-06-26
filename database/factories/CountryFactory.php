<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->country(),
            'code' => $this->faker->unique()->countryCode(),
            'status' => $this->faker->boolean(90), // 90% chance of being active (1)
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // Define specific countries using states
    public function india()
    {
        return $this->state([
            'name' => 'India',
            'code' => 'IN',
            'status' => 1,
        ]);
    }

    public function usa()
    {
        return $this->state([
            'name' => 'United States',
            'code' => 'US',
            'status' => 1,
        ]);
    }

    public function canada()
    {
        return $this->state([
            'name' => 'Canada',
            'code' => 'CA',
            'status' => 1,
        ]);
    }

    public function australia()
    {
        return $this->state([
            'name' => 'Australia',
            'code' => 'AU',
            'status' => 1,
        ]);
    }

    public function uk()
    {
        return $this->state([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'status' => 1,
        ]);
    }

    public function japan()
    {
        return $this->state([
            'name' => 'Japan',
            'code' => 'JP',
            'status' => 1,
        ]);
    }

    public function germany()
    {
        return $this->state([
            'name' => 'Germany',
            'code' => 'DE',
            'status' => 1,
        ]);
    }
}
