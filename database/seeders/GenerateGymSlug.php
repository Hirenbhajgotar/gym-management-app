<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Gym;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GenerateGymSlug extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gyms = Gym::query()->whereNull('slug')->get();

        foreach ($gyms as $gym) {
            $baseSlug = Str::slug($gym->name);
            $slug = $baseSlug;
            $counter = 1;

            // Check for uniqueness and append a number if needed
            while (Gym::query()->where('slug', $slug)->where('id', '!=', $gym->id)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $gym->update(['slug' => $slug]);
        }
    }
}
