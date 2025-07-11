<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businessTypes = [
            [
                'name' => 'Gym',
                'slug' => 'gym',
                'is_active' => true,
            ],
            [
                'name' => 'CrossFit Box',
                'slug' => 'crossfit-box',
                'is_active' => true,
            ],
            [
                'name' => 'Yoga Studio',
                'slug' => 'yoga-studio',
                'is_active' => true,
            ],
            [
                'name' => 'Pilates Studio',
                'slug' => 'pilates-studio',
                'is_active' => true,
            ],
            [
                'name' => 'Martial Arts',
                'slug' => 'martial-arts',
                'is_active' => true,
            ],
            [
                'name' => 'Boutique Fitness',
                'slug' => 'boutique-fitness',
                'is_active' => true,
            ],
            [
                'name' => 'Health Club',
                'slug' => 'health-club',
                'is_active' => true,
            ],
            [
                'name' => 'Fitness Studio',
                'slug' => 'fitness-studio',
                'is_active' => true,
            ],
            [
                'name' => 'Dance Studio',
                'slug' => 'dance-studio',
                'is_active' => true,
            ],
            [
                'name' => 'Wellness Center',
                'slug' => 'wellness-center',
                'is_active' => true,
            ],
            [
                'name' => 'Functional Training',
                'slug' => 'functional-training',
                'is_active' => true,
            ],

            [
                'name' => 'Dance Fitness',
                'slug' => 'dance-fitness',
                'is_active' => true,
            ],
            [
                'name' => 'Personal Training Studio',
                'slug' => 'personal-training-studio',
                'is_active' => true,
            ],
            [
                'name' => 'Boxing Gym',
                'slug' => 'boxing-gym',
                'is_active' => true,
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'is_active' => true,
            ]
        ];

        foreach ($businessTypes as $businessType) {
            BusinessType::updateOrCreate(
                ['slug' => $businessType['slug']],
                $businessType
            );
        }
    }
}
