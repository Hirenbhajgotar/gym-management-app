<?php

namespace App\Filament\Resources\GymResource\Pages;

use App\Filament\Resources\GymResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateGym extends CreateRecord
{
    protected static string $resource = GymResource::class;

    protected function beforeCreate(): void
    {
        $user = auth()->user();
        $maxGyms = $user?->subscription?->plan?->features()
            ->where('feature_name', 'max_gyms')
            ->first()
            ->value ?? 0;

        // dd($user->gyms()->count());

        // if ($user->gyms()->count() >= $maxGyms) {
        //     throw ValidationException::withMessages([
        //         'gym' => "You have reached the maximum number of gyms ({$maxGyms}) for your plan.",
        //     ]);
        // }
    }
}
