<?php

namespace Database\Seeders;

use App\Models\Gym;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GenerateGymSlug extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gyms = Gym::whereNull('slug')->get();

        foreach ($gyms as $gym) {
            $baseSlug = Str::slug($gym->name);
            $slug = $baseSlug;
            $counter = 1;

            // Check for uniqueness and append a number if needed
            while (Gym::where('slug', $slug)->where('id', '!=', $gym->id)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $gym->update(['slug' => $slug]);
        }
    }
}
