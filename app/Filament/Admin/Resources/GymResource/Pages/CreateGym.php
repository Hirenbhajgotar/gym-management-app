<?php

namespace App\Filament\Admin\Resources\GymResource\Pages;

use App\Filament\Admin\Resources\GymResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateGym extends CreateRecord
{
    protected static string $resource = GymResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return null; // Disable default success notification
    }

    protected function beforeCreate(): void
    {
        $user = auth()->user();

        $user?->getMaxGyms() ?? 0;

        // dd($user->gyms()->count());

        // if ($user->gyms()->count() >= $maxGyms) {
        //     throw ValidationException::withMessages([
        //         'gym' => "You have reached the maximum number of gyms ({$maxGyms}) for your plan.",
        //     ]);
        // }
    }

    protected function getRedirectUrl(): string
    {
        return GymResource::getUrl('index'); // Redirect to index page after create
    }
}
